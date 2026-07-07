<?php

namespace App\Console\Commands;

use App\Jobs\FetchRssFeedJob;
use App\Models\RssSource;
use Illuminate\Console\Command;

/**
 * Runs every minute (register in routes/console.php or the scheduler).
 * Build in Roadmap Stage 2.
 * Dispatches FetchRssFeedJob for every active RssSource whose
 * fetch_frequency_minutes interval has elapsed — see RssSource::isDueForFetch().
 */
class DispatchDueRssFeedsCommand extends Command
{
    protected $signature = 'atlas:dispatch-due-feeds';
    protected $description = 'Dispatch FetchRssFeedJob for every RSS source that is due.';

    public function handle(): void
    {
        RssSource::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('priority')
            ->get()
            ->filter(fn (RssSource $source) => $source->isDueForFetch())
            ->each(fn (RssSource $source) => FetchRssFeedJob::dispatch($source));
    }
}
