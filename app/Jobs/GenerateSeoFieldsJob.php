<?php

namespace App\Jobs;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\PromptPayload;
use App\Models\GeneratedArticle;
use App\Models\SystemLog;
use App\Services\AI\AIProviderFactory;
use App\Services\ContentFilters\SeoFieldsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Follow-on job dispatched by GenerateArticleJob once a generated article
 * passes the core quality gate. Runs on the "generation" channel.
 *
 * Generates title/slug/meta_description only (no FAQ schema/image alt for
 * MVP — see MVP_ROADMAP.md Stage 3), checks they're non-empty, then makes
 * the final review/approved/rejected decision now that SEO fields exist.
 */
class GenerateSeoFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly GeneratedArticle $article,
    ) {
        $this->onQueue('generation');
    }

    public function handle(AIProviderFactory $providerFactory): void
    {
        $article = $this->article;

        if ($article->status !== 'generated') {
            return;
        }

        $seo = $this->generateSeoFields($article, $providerFactory);
        $slug = Str::slug($seo['title'] !== '' ? $seo['title'] : $article->rssItem->title);

        $article->update([
            'title' => $seo['title'],
            'slug' => $slug,
            'meta_description' => $seo['meta_description'],
        ]);

        $seoResult = (new SeoFieldsFilter())->check(
            new ArticlePayload(
                generatedArticleId: $article->id,
                title: $article->title,
                bodyHtml: $article->body,
                slug: $article->slug,
                metaDescription: $article->meta_description,
            ),
            $article->site,
        );

        $flags = (array) $article->quality_flags;
        $flags[] = ['filter' => $seoResult->filterName, 'outcome' => $seoResult->outcome, 'reason' => $seoResult->reason];
        $article->update(['quality_flags' => $flags]);

        if ($seoResult->isFail()) {
            $article->transitionTo('rejected');
            $article->update(['reject_reason' => $seoResult->reason]);

            SystemLog::create([
                'job_type' => self::class,
                'entity_type' => GeneratedArticle::class,
                'entity_id' => $article->id,
                'status' => 'rejected',
                'message' => $seoResult->reason,
            ]);

            return;
        }

        $finalStatus = ($article->hasHoldFlag() || !$article->site->effectiveAutoPublish()) ? 'review' : 'approved';
        $article->transitionTo($finalStatus);

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => GeneratedArticle::class,
            'entity_id' => $article->id,
            'status' => 'success',
            'message' => "SEO fields generated; final status: {$finalStatus}.",
            'payload' => ['title' => $article->title, 'slug' => $article->slug],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->article->status === 'generated') {
            $this->article->update(['status' => 'failed']);
        }

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => GeneratedArticle::class,
            'entity_id' => $this->article->id,
            'status' => 'failed',
            'message' => $exception->getMessage(),
            'payload' => ['exception' => get_class($exception)],
        ]);
    }

    /** @return array{title: string, meta_description: string} */
    private function generateSeoFields(GeneratedArticle $article, AIProviderFactory $providerFactory): array
    {
        $provider = $providerFactory->make();

        $prompt = new PromptPayload(
            systemPrompt: "You write concise, accurate SEO metadata for articles. Respond in exactly this format and nothing else:\nTITLE: <title, max 60 characters>\nMETA: <meta description, 140-160 characters>",
            userPrompt: "Article body:\n{$article->body}",
        );

        $result = $provider->generate($prompt);

        if (!$result->success) {
            // Same tradeoff as GenerateArticleJob: let the queue's own retry
            // (tries=3) handle this rather than distinguishing retryable vs
            // not — a non-retryable error retries identically 3x first.
            throw new RuntimeException("SEO field generation failed ({$result->errorType}): {$result->errorMessage}");
        }

        preg_match('/TITLE:\s*(.+)/i', $result->bodyHtml, $titleMatch);
        preg_match('/META:\s*(.+)/i', $result->bodyHtml, $metaMatch);

        return [
            'title' => trim($titleMatch[1] ?? ''),
            'meta_description' => trim($metaMatch[1] ?? ''),
        ];
    }
}
