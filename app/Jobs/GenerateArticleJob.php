<?php

namespace App\Jobs;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Models\GeneratedArticle;
use App\Models\RssItem;
use App\Models\Site;
use App\Models\SystemLog;
use App\Services\AI\AIProviderFactory;
use App\Services\OutputValidator;
use App\Services\PromptBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * AI Generator. Runs on the "generation" queue channel.
 *
 * Flow: PromptBuilderService::build() -> AIProviderInterface (resolved via
 * AIProviderFactory, never called directly) -> store body + metadata in
 * MariaDB (generated_articles) -> run the core OutputValidator checks
 * inline -> hard FAIL rejects immediately, otherwise hand off to
 * GenerateSeoFieldsJob, which makes the final review/approved decision
 * once SEO fields exist.
 */
class GenerateArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly RssItem $rssItem,
        public readonly Site $site,
    ) {
        $this->onQueue('generation');
    }

    public function handle(
        PromptBuilderService $promptBuilder,
        AIProviderFactory $providerFactory,
        OutputValidator $validator,
    ): void {
        $article = GeneratedArticle::firstOrCreate(
            ['rss_item_id' => $this->rssItem->id, 'site_id' => $this->site->id],
            ['status' => 'queued'],
        );

        // 'generating' is included here (not just 'queued'/'failed') because a
        // queue retry re-enters handle() for a job whose *first* attempt
        // already moved the row to 'generating' — that must NOT be treated
        // as "already processed", or a retryable failure would silently
        // no-op forever instead of ever reaching 'failed'.
        if (!in_array($article->status, ['queued', 'failed', 'generating'], true)) {
            Log::info('GenerateArticleJob: skipping, already processed', [
                'generated_article_id' => $article->id,
                'status' => $article->status,
            ]);

            return;
        }

        if ($article->status === 'failed') {
            $article->transitionTo('queued'); // manual-retry step per state machine
        }

        if ($article->status === 'queued') {
            $article->transitionTo('generating');
        }

        $prompt = $promptBuilder->build($this->rssItem, $this->site);
        $provider = $providerFactory->make();
        $result = $provider->generate($prompt);

        if (!$result->success) {
            // Laravel's own retry (tries=3) handles transient failures. Like
            // FetchRssFeedJob's malformed-feed case: a non-retryable error
            // (e.g. bad API key) will retry identically 3x before failing —
            // accepted inefficiency, not fixed here, same call as Stage 2.
            throw new RuntimeException("AI generation failed ({$result->errorType}): {$result->errorMessage}");
        }

        $article->update([
            'body' => $result->bodyHtml,
            'author_identifier' => $prompt->metadata['author_identifier'] ?? null,
            'prompt_version' => $prompt->metadata['prompt_version'] ?? null,
            'model_used' => $result->modelUsed,
            'provider' => $result->providerName,
            'tokens_input' => $result->tokensInput,
            'tokens_output' => $result->tokensOutput,
            'generation_ms' => $result->generationMs,
        ]);

        $article->transitionTo('generated');

        $articlePayload = new ArticlePayload(
            generatedArticleId: $article->id,
            title: $this->rssItem->title, // placeholder until GenerateSeoFieldsJob sets the real title
            bodyHtml: $result->bodyHtml,
        );

        $filterResults = $validator->validate($articlePayload, $this->site);
        $article->update(['quality_flags' => $this->serializeResults($filterResults)]);

        if ($validator->anyFail($filterResults)) {
            $article->transitionTo('rejected');
            $article->update(['reject_reason' => $this->firstFailReason($filterResults)]);

            SystemLog::create([
                'job_type' => self::class,
                'entity_type' => GeneratedArticle::class,
                'entity_id' => $article->id,
                'status' => 'rejected',
                'message' => 'Failed core quality gate: '.$this->firstFailReason($filterResults),
                'payload' => ['filters' => $this->serializeResults($filterResults)],
            ]);

            return;
        }

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => GeneratedArticle::class,
            'entity_id' => $article->id,
            'status' => 'success',
            'message' => 'Generated and passed core quality gate; dispatching SEO field generation.',
            'payload' => [
                'provider' => $result->providerName,
                'model' => $result->modelUsed,
                'tokens_input' => $result->tokensInput,
                'tokens_output' => $result->tokensOutput,
                'filters' => $this->serializeResults($filterResults),
            ],
        ]);

        GenerateSeoFieldsJob::dispatch($article->fresh());
    }

    public function failed(\Throwable $exception): void
    {
        $article = GeneratedArticle::where('rss_item_id', $this->rssItem->id)
            ->where('site_id', $this->site->id)
            ->first();

        if ($article && in_array($article->status, ['queued', 'generating'], true)) {
            $article->update(['status' => 'failed']);
        }

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => GeneratedArticle::class,
            'entity_id' => $article?->id,
            'status' => 'failed',
            'message' => $exception->getMessage(),
            'payload' => [
                'exception' => get_class($exception),
                'rss_item_id' => $this->rssItem->id,
                'site_id' => $this->site->id,
            ],
        ]);
    }

    /** @param FilterResult[] $results */
    private function serializeResults(array $results): array
    {
        return array_map(fn (FilterResult $r) => [
            'filter' => $r->filterName,
            'outcome' => $r->outcome,
            'reason' => $r->reason,
        ], $results);
    }

    /** @param FilterResult[] $results */
    private function firstFailReason(array $results): ?string
    {
        foreach ($results as $result) {
            if ($result->isFail()) {
                return $result->reason ?? $result->filterName;
            }
        }

        return null;
    }
}
