# ATLAS Operator Runbook

Practical, tinker-driven procedures for running the pipeline day to day. There's
no admin dashboard yet (deferred to Phase 1 per `docs/MVP_ROADMAP.md`) — every
procedure below uses `php artisan tinker` or a raw DB browser.

## Keeping the pipeline running

Windows has no cron. Two options:

- **`php artisan schedule:work`** — a long-running foreground process that
  checks the schedule every minute. Simplest for local testing, but stops if
  the terminal/session closes or the machine restarts.
- **Windows Task Scheduler** (persists across reboots — use this for anything
  beyond ad-hoc local testing):
  ```
  schtasks /Create /SC MINUTE /MO 1 /TN "AtlasScheduler" /TR "E:\wamp64\bin\php\php8.4.0\php.exe E:\wamp64\www\lattis-mvp\artisan schedule:run" /F
  ```
  This runs `schedule:run` every minute, which is what actually dispatches
  `atlas:dispatch-due-feeds` (every minute), `atlas:dispatch-article-generation`
  and `atlas:schedule-publishing` (every 5 minutes), and `atlas:check-credentials`
  (daily) per their own schedules in `routes/console.php`.

You also need at least one running queue worker per channel, since jobs don't
process themselves:
```
php artisan queue:work --queue=ingestion
php artisan queue:work --queue=generation
php artisan queue:work --queue=publishing
```
Run these as three separate persistent processes (or Task Scheduler entries) —
deliberately separate per `.cursorrules` rule on queue channel separation, so a
stuck channel doesn't block the others.

## Retry a failed article generation

`generated_articles.status = 'failed'` after `GenerateArticleJob` or
`GenerateSeoFieldsJob` exhausts 3 retries. Check `system_logs` first
(`job_type` = one of those two, `status = 'failed'`) to see why.

```php
$article = App\Models\GeneratedArticle::find(123);
App\Jobs\GenerateArticleJob::dispatch($article->rssItem, $article->site);
```

If the article already has a `body` (i.e. it was `GenerateSeoFieldsJob` that
failed, not `GenerateArticleJob`), re-dispatch that one instead:
```php
App\Jobs\GenerateSeoFieldsJob::dispatch($article);
```

## Retry a failed publish

`generated_articles.status = 'failed'` after `PublishArticleJob` exhausts
retries, or immediately if it was a credential/auth failure (no retry — see
below). Check `credentials.credential_status` for the site first; if it's
`failed`, fix credentials before retrying or it'll just halt again.

```php
$article = App\Models\GeneratedArticle::find(123);
$article->update(['status' => 'scheduled']); // manual override back to a retryable state
App\Jobs\PublishArticleJob::dispatch($article);
```

## RSS feed errors

`rss_sources.status = 'errored'` after 3 failed fetch attempts. This
**self-heals automatically** — `atlas:dispatch-due-feeds` still dispatches
errored sources (throttled by `fetch_frequency_minutes`), and a successful
fetch resets `status` back to `active`. No manual action needed unless the
feed is permanently broken — check `system_logs` (`job_type =
App\Jobs\FetchRssFeedJob`) for the actual error; if the URL itself is dead,
fix `rss_sources.feed_url` directly.

## Disable a site

```php
App\Models\Site::where('domain', 'example.com')->update(['is_active' => false]);
```
Stops new article generation (the dispatcher only fans out to active sites)
and stops publish scheduling. Does **not** retroactively stop articles already
`scheduled`/`publishing` — check those manually for a hard stop. To disable
just one bad RSS feed instead of a whole site, set `rss_sources.is_active =
false` on that source.

## Update credentials

Always via the model's mutators (auto-encrypts via `Crypt::encryptString`) —
never write credentials via raw SQL.

```php
$credential = App\Models\Credential::where('site_id', $siteId)
    ->where('adapter_type', 'ftp_html')->first();

$credential->update([
    'username' => 'new-ftp-user',
    'secret' => 'new-ftp-password',
    'credential_status' => 'unverified',
]);

// Re-verify immediately instead of waiting for the daily check — un-halts
// the site right away if the new credentials work.
app(App\Services\CredentialService::class)->verifyCredentials($credential->site);
```

## Reading system_logs

Every job/command logs its outcome here: `job_type`, `entity_type`/`entity_id`,
`status` (`success` / `failed` / `rejected` / `skipped` / `warning`), `message`,
`payload`.

- `status = 'warning'` (currently only from `PublishArticleJob`'s post-publish
  live-check) means the publish call itself reported success, but a follow-up
  HTTP check didn't find the page live — could be a genuine silent failure
  (wrong path, permissions) or just propagation delay. Verify the URL by hand.
- `status = 'skipped'` from `PublishingSchedulerCommand` means a site was
  skipped this run (credential halted, or daily quota already reached) —
  expected to repeat every 5 minutes while the underlying condition holds, not
  a bug.

Quick health check:
```php
App\Models\SystemLog::where('status', 'failed')
    ->latest()->take(20)->get(['job_type', 'entity_id', 'message', 'created_at']);
```

## Quality gate false positives/negatives

The filters live in `app/Services/ContentFilters/` (`WordCountFilter`,
`DuplicateParagraphFilter`, `SourceSimilarityFilter`, `ForbiddenTopicFilter`,
`SeoFieldsFilter`, plus the absolute `HealthSatireHoldFilter`). If real content
gets wrongly rejected or bad content sails through:

- Check `generated_articles.quality_flags` for the exact filter and reason.
- `SourceSimilarityFilter`'s threshold (0.85 cosine similarity, a constant in
  the class) and `WordCountFilter`'s minimum (per-site `site_dna.min_word_count`)
  are the two most likely to need tuning as real content comes in.
- Adjust the specific filter's threshold/logic, never `OutputValidator` itself
  — it only orchestrates, per its own docstring.
- This is expected ongoing tuning as real data arrives, not a one-time fix.

## Known MVP limitations (by design, not bugs)

- No admin dashboard — everything above is tinker/DB-browser driven.
- Single admin account, no roles.
- One AI provider (Anthropic/Claude), one publishing target (FTP/HTML).
- Article bodies live in MariaDB (`generated_articles.body`), not MongoDB —
  the two-database split was dropped for this environment (no MongoDB
  available), a deliberate deviation from `docs/MVP_ROADMAP.md`.
- No Horizon/Redis — queues run on Laravel's `database` driver (also a
  deliberate deviation; channel separation is still enforced per-job).
