<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stage 9 — Publisher. Runs on the "publishing" queue channel.
 * Build in Roadmap Stage 4.
 *
 * TODO (Stage 4):
 *  - Load the GeneratedArticle (must be status='scheduled').
 *  - Decrypt credentials via CredentialService (never handle raw secrets here).
 *  - Resolve the PublishingAdapterInterface implementation via a factory keyed
 *    on $article->site->stack_type. Never call a CMS API / FTP client directly.
 *  - Call publish(). On success: store external_id/external_url, transition
 *    to 'published', update site.deployment_state (last_published_at, total_published).
 *  - On failure: exponential back-off retry; a credential-type failure should
 *    halt publishing for the site immediately (see CredentialHealthCheckCommand).
 *  - Log outcome to system_logs regardless of result.
 */
class PublishArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly GeneratedArticle $article,
    ) {
        $this->onQueue('publishing');
    }

    public function handle(): void
    {
        // TODO: implement per docstring above.
    }
}
