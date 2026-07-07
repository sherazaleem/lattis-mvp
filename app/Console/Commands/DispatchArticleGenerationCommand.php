<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleJob;
use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Runs every 5 minutes. Dispatches GenerateArticleJob for every rss_item
 * that's ready for generation (is_processed=false — short items are already
 * excluded, see RssItem::countWords() / FetchRssFeedJob) fanned out to the
 * relevant site(s):
 *   - a source tied directly to one site (source.site_id) generates for
 *     just that site;
 *   - a source tied only to a niche_cluster (source.cluster_id, no direct
 *     site) fans out to every active site in that cluster.
 *
 * This is the piece that makes the pipeline actually automatic — without
 * it, generation only ever happens via manual dispatch. Found missing
 * during the Stage 6 audit.
 */
class DispatchArticleGenerationCommand extends Command
{
    protected $signature = 'atlas:dispatch-article-generation';
    protected $description = "Dispatch GenerateArticleJob for every unprocessed rss_item, fanned out to the relevant site(s).";

    public function handle(): void
    {
        RssItem::where('is_processed', false)
            ->with('source')
            ->chunkById(50, function (Collection $items) {
                foreach ($items as $item) {
                    $this->dispatchForItem($item);
                }
            });
    }

    private function dispatchForItem(RssItem $item): void
    {
        $sites = $this->resolveSites($item->source);

        if ($sites->isEmpty()) {
            $this->warn("rss_item #{$item->id}: no active site resolved for its source, skipping.");

            return;
        }

        foreach ($sites as $site) {
            GenerateArticleJob::dispatch($item, $site);
        }

        $item->update(['is_processed' => true]);
    }

    /** @return Collection<int, Site> */
    private function resolveSites(?RssSource $source): Collection
    {
        if (!$source) {
            return collect();
        }

        if ($source->site_id) {
            $site = $source->site;

            return ($site && $site->is_active) ? collect([$site]) : collect();
        }

        if ($source->cluster_id) {
            return Site::where('cluster_id', $source->cluster_id)->where('is_active', true)->get();
        }

        return collect();
    }
}
