<?php

namespace App\Jobs;

use App\Models\RssItem;
use App\Models\RssSource;
use App\Models\SystemLog;
use App\Services\RssFeedParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RSS/Atom fetcher. Runs on the "ingestion" queue channel.
 *
 * INPUT: an RssSource record.
 * OUTPUT: RssItem rows written to MariaDB.
 * ON FAILURE: 3 attempts, exponential back-off (30s, 90s, 270s). On final
 *         failure: mark rss_sources.status=errored, log to system_logs.
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

    public function handle(RssFeedParser $parser): void
    {
        $response = Http::timeout(15)
            ->withHeaders(['User-Agent' => 'ATLAS-RSS-Fetcher/1.0 (+https://lattis.one)'])
            ->get($this->rssSource->feed_url);
        $response->throw();

        $items = $parser->parse($response->body());
        $inserted = 0;
        $duplicates = 0;
        $skippedShort = 0;

        foreach ($items as $item) {
            if ($item['url'] === '' || $item['title'] === '') {
                continue;
            }

            $hash = RssItem::computeContentHash($item['url'], $item['title']);

            if (RssItem::where('content_hash', $hash)->exists()) {
                $duplicates++;
                Log::debug('FetchRssFeedJob: skipping duplicate item', [
                    'source_id' => $this->rssSource->id,
                    'content_hash' => $hash,
                    'url' => $item['url'],
                ]);

                continue;
            }

            $wordCount = RssItem::countWords($item['body_html']);
            $isTooShort = $wordCount < RssItem::MIN_SOURCE_WORD_COUNT;

            try {
                RssItem::create([
                    'source_id' => $this->rssSource->id,
                    'url' => $item['url'],
                    'title' => $item['title'],
                    'body_html' => $item['body_html'],
                    'published_at' => $item['published_at'],
                    'fetched_at' => now(),
                    'content_hash' => $hash,
                    'source_word_count' => $wordCount,
                    // Under the minimum word count: mark processed immediately so it's
                    // excluded from generation without ever being queued for it.
                    'is_processed' => $isTooShort,
                    'is_duplicate' => false,
                ]);

                $inserted++;
                if ($isTooShort) {
                    $skippedShort++;
                }
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Lost a race against another run inserting the same hash first — treat as duplicate, not a failure.
                $duplicates++;
            }
        }

        $this->rssSource->update([
            'last_fetched_at' => now(),
            'status' => 'active',
        ]);

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => RssSource::class,
            'entity_id' => $this->rssSource->id,
            'status' => 'success',
            'message' => "Fetched {$this->rssSource->feed_url}: {$inserted} inserted ({$skippedShort} under word-count minimum), {$duplicates} duplicates skipped, ".count($items).' total items in feed.',
            'payload' => [
                'fetched' => count($items),
                'inserted' => $inserted,
                'duplicates' => $duplicates,
                'skipped_short' => $skippedShort,
            ],
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->rssSource->update(['status' => 'errored']);

        SystemLog::create([
            'job_type' => self::class,
            'entity_type' => RssSource::class,
            'entity_id' => $this->rssSource->id,
            'status' => 'failed',
            'message' => $exception->getMessage(),
            'payload' => ['exception' => get_class($exception)],
        ]);
    }
}
