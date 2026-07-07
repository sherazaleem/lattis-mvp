<?php

namespace App\Console\Commands;

use App\Jobs\PublishArticleJob;
use App\Models\GeneratedArticle;
use App\Models\Site;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Runs every 5 minutes. Finds approved articles, respects each site's
 * max_posts_per_day (computed in the site's own timezone) and skips sites
 * whose credential has been halted (see PublishArticleJob / CredentialHealthCheckCommand).
 * The oldest approved article per site gets the next available slot.
 */
class PublishingSchedulerCommand extends Command
{
    protected $signature = 'atlas:schedule-publishing';
    protected $description = 'Assign publish datetimes to approved articles, respecting per-site daily limits.';

    public function handle(): void
    {
        $siteIds = GeneratedArticle::where('status', 'approved')
            ->whereHas('site', fn ($q) => $q->where('is_active', true))
            ->distinct()
            ->pluck('site_id');

        foreach (Site::whereIn('id', $siteIds)->get() as $site) {
            $this->scheduleForSite($site);
        }
    }

    private function scheduleForSite(Site $site): void
    {
        // Logged to system_logs (not just console output) — this command
        // runs unattended via the scheduler, so console-only messages are
        // invisible; a site silently never getting scheduled is exactly the
        // "silent failure" Stage 6 calls out.
        if ($this->credentialHalted($site)) {
            $this->logSkip($site, "Credential halted for site [{$site->id}] {$site->domain}, skipping scheduling.");

            return;
        }

        $remainingSlots = $this->remainingSlotsToday($site);

        if ($remainingSlots <= 0) {
            $this->logSkip($site, "Daily quota already reached for site [{$site->id}] {$site->domain}, skipping.");

            return;
        }

        $articles = GeneratedArticle::where('site_id', $site->id)
            ->where('status', 'approved')
            ->orderBy('created_at')
            ->limit($remainingSlots)
            ->get();

        foreach ($articles as $article) {
            $article->update(['scheduled_at' => now()]);
            $article->transitionTo('scheduled');

            PublishArticleJob::dispatch($article);

            $this->info("Scheduled GeneratedArticle #{$article->id} for site [{$site->id}].");
        }
    }

    private function logSkip(Site $site, string $message): void
    {
        $this->info($message);

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => Site::class,
            'entity_id' => $site->id,
            'status' => 'skipped',
            'message' => $message,
        ]);
    }

    private function credentialHalted(Site $site): bool
    {
        return $site->credentials()
            ->where('adapter_type', $site->stack_type)
            ->where('credential_status', 'failed')
            ->exists();
    }

    private function remainingSlotsToday(Site $site): int
    {
        $todayStart = Carbon::now($site->timezone)->startOfDay();
        $todayEnd = Carbon::now($site->timezone)->endOfDay();

        $alreadyCounted = GeneratedArticle::where('site_id', $site->id)
            ->whereIn('status', ['scheduled', 'publishing', 'published'])
            ->whereBetween('scheduled_at', [$todayStart, $todayEnd])
            ->count();

        return max(0, $site->max_posts_per_day - $alreadyCounted);
    }
}
