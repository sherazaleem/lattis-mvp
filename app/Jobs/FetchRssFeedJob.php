<?php

namespace App\Jobs;

use App\Models\RssSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stage 1 — RSS Fetcher. Runs on the "ingestion" queue channel.
 * Build in Roadmap Stage 2.
 *
 * INPUT: an RssSource record.
 * OUTPUT: RssItem rows written to MariaDB (title, body_html, content_hash,
 *         source_word_count, is_processed=false, is_duplicate=false).
 * ON FAILURE: 3 attempts, exponential back-off (30s, 90s, 270s). On final
 *         failure: mark rss_sources.status=errored, log to system_logs.
 *
 * TODO (Stage 2):
 *  - Fetch and parse the feed XML.
 *  - For each item: compute RssItem::computeContentHash($url, $title).
 *    Skip if content_hash already exists (dedup — non-negotiable).
 *  - Count words in body_html; if < RssItem::MIN_SOURCE_WORD_COUNT, store
 *    the item with is_processed = true (skipped, not sent to generation).
 *  - Update rss_sources.last_fetched_at and status on success.
 *  - Log outcome (counts fetched/duplicate/skipped) to system_logs.
 */
class FetchRssFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 90, 270];

    public function __construct(
        public readonly RssSource $rssSource,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(): void
    {
        // TODO: implement per docstring above.
    }

    public function failed(\Throwable $exception): void
    {
        // TODO: mark $this->rssSource->status = 'errored'; log to system_logs.
    }
}
