<?php

namespace App\Console\Commands;

use App\Jobs\FetchRssFeedJob;
use App\Models\RssSource;
use Illuminate\Console\Command;

/**
 * Runs every minute (register in routes/console.php or the scheduler).
 * Dispatches FetchRssFeedJob for every active RssSource whose
 * fetch_frequency_minutes interval has elapsed — see RssSource::isDueForFetch().
 *
 * Deliberately does NOT filter out status='errored' sources — a source only
 * reaches 'errored' after 3 failed fetch attempts (see FetchRssFeedJob),
 * which is very often a transient issue (feed host hiccup, momentary
 * network blip). FetchRssFeedJob resets status back to 'active' on its next
 * successful fetch, so excluding 'errored' sources here would turn a
 * transient failure into a permanent, silent stop with no automatic
 * recovery path — exactly the "silent publish/feed failure" risk Stage 6
 * calls out. isDueForFetch() already throttles retries to the source's own
 * fetch_frequency_minutes, so this doesn't hammer a genuinely broken feed.
 */
class DispatchDueRssFeedsCommand extends Command
{
    protected $signature = 'atlas:dispatch-due-feeds';
    protected $description = 'Dispatch FetchRssFeedJob for every RSS source that is due.';

    public function handle(): void
    {
        RssSource::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->filter(fn (RssSource $source) => $source->isDueForFetch())
            ->each(fn (RssSource $source) => FetchRssFeedJob::dispatch($source));
    }
}
