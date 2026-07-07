<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Runs every 5 minutes. Build in Roadmap Stage 4.
 *
 * TODO:
 *  - Find GeneratedArticle rows with status = 'approved'.
 *  - For each site, respect max_posts_per_day and site timezone — the oldest
 *    approved article per site gets the next available publish slot.
 *  - Assign scheduled_at, transition status to 'scheduled', and dispatch
 *    PublishArticleJob at (or near) that time.
 */
class PublishingSchedulerCommand extends Command
{
    protected $signature = 'atlas:schedule-publishing';
    protected $description = 'Assign publish datetimes to approved articles, respecting per-site daily limits.';

    public function handle(): void
    {
        // TODO: implement per docstring above.
    }
}
