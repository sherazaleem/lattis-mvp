<?php

namespace App\Jobs;

use App\Models\RssItem;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stage 4 — AI Generator. Runs on the "generation" queue channel.
 * Build in Roadmap Stage 3.
 *
 * Flow: load Site DNA -> PromptBuilderService::build() -> AIProviderInterface
 * (resolved via a provider factory, never called directly) -> store body in
 * MongoDB, metadata in generated_articles -> hand off to OutputValidator.
 *
 * TODO (Stage 3):
 *  - Create GeneratedArticle row with status = 'generating'.
 *  - Build PromptPayload via PromptBuilderService (site DNA + author fragment
 *    + source facts from the RssItem). No external calls in the builder.
 *  - Resolve the configured AIProviderInterface implementation and call
 *    generate(). Never call an SDK directly here.
 *  - On success: write article body to MongoDB (articles collection),
 *    store mongo_id + model_used + provider + token counts on the
 *    GeneratedArticle row, transition status to 'generated'.
 *  - On failure: transition to 'failed', log full error to system_logs.
 *  - Dispatch OutputValidator (inline, same job, per original Stage 5 design)
 *    then hand off to GenerateSeoFieldsJob if it passes.
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

    public function handle(): void
    {
        // TODO: implement per docstring above.
    }
}
