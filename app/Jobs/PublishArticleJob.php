<?php

namespace App\Jobs;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\PublishResult;
use App\Interfaces\PublishingAdapterInterface;
use App\Models\GeneratedArticle;
use App\Models\Site;
use App\Models\SystemLog;
use App\Services\Publishing\PublishingAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Publisher. Runs on the "publishing" queue channel.
 *
 * Loads a 'scheduled' GeneratedArticle, resolves the PublishingAdapterInterface
 * implementation via PublishingAdapterFactory (never called directly), and
 * publishes. A credential-type failure halts publishing for the site
 * immediately (no retry) — any other failure uses the normal 3x retry.
 */
class PublishArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 90, 270];

    public function __construct(
        public readonly GeneratedArticle $article,
    ) {
        $this->onQueue('publishing');
    }

    public function handle(PublishingAdapterFactory $adapterFactory): void
    {
        $article = $this->article;

        // 'publishing' included alongside 'scheduled' for the same reason as
        // GenerateArticleJob: a queue retry re-enters handle() with the row
        // already moved to 'publishing' by the first attempt.
        if (!in_array($article->status, ['scheduled', 'publishing'], true)) {
            return;
        }

        if ($article->status === 'scheduled') {
            $article->transitionTo('publishing');
        }

        $site = $article->site;
        $adapter = $adapterFactory->make($site->stack_type);

        $payload = new ArticlePayload(
            generatedArticleId: $article->id,
            title: (string) $article->title,
            bodyHtml: (string) $article->body,
            slug: $article->slug,
            metaDescription: $article->meta_description,
            focusKeyword: $article->focus_keyword,
        );

        $result = $adapter->publish($payload, $site);

        if (!$result->success) {
            if ($result->errorType === 'auth') {
                $this->haltSiteCredential($site);
                $this->fail(new RuntimeException(
                    "Publishing credential failure, halting site [{$site->id}]: {$result->errorMessage}",
                ));

                return;
            }

            throw new RuntimeException("Publishing failed ({$result->errorType}): {$result->errorMessage}");
        }

        $article->update([
            'external_id' => $result->externalId,
            'external_url' => $result->externalUrl,
            'published_at' => now(),
        ]);

        $article->transitionTo('published');

        $site->update([
            'deployment_state' => array_merge((array) $site->deployment_state, [
                'last_published_at' => now()->toIso8601String(),
                'total_published' => ((int) data_get($site->deployment_state, 'total_published', 0)) + 1,
            ]),
        ]);

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => GeneratedArticle::class,
            'entity_id' => $article->id,
            'status' => 'success',
            'message' => "Published to {$result->externalUrl}",
            'payload' => ['external_id' => $result->externalId, 'external_url' => $result->externalUrl],
        ]);

        $this->verifyLive($adapter, $result, $article, $site);
    }

    /**
     * The adapter reporting success only means the upload/API call didn't
     * error — it doesn't guarantee the page is actually reachable (wrong
     * path, permissions, propagation delay). This is a soft check: log a
     * warning, don't fail the job or change article status, since a false
     * negative here (page just needs a moment) shouldn't undo a real publish.
     */
    private function verifyLive(
        PublishingAdapterInterface $adapter,
        PublishResult $result,
        GeneratedArticle $article,
        Site $site,
    ): void {
        $status = $adapter->getStatus((string) $result->externalId, $site);

        if ($status->status !== 'live') {
            SystemLog::create([
                'job_type' => self::class,
                'entity_type' => GeneratedArticle::class,
                'entity_id' => $article->id,
                'status' => 'warning',
                'message' => "Publish reported success but live-check returned '{$status->status}' for {$result->externalUrl} — verify manually.",
                'payload' => ['external_url' => $result->externalUrl, 'checked_status' => $status->status],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        if (in_array($this->article->status, ['scheduled', 'publishing'], true)) {
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

    private function haltSiteCredential(Site $site): void
    {
        $site->credentials()->where('adapter_type', $site->stack_type)->update([
            'credential_status' => 'failed',
        ]);
    }
}
