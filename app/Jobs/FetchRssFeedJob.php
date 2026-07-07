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
        $response = Http::timeout(15)->get($this->rssSource->feed_url);
        $response->throw();

        $items = $parser->parse($response->body());
        $inserted = 0;

        foreach ($items as $item) {
            if ($item['url'] === '' || $item['title'] === '') {
                continue;
            }

            RssItem::create([
                'source_id' => $this->rssSource->id,
                'url' => $item['url'],
                'title' => $item['title'],
                'body_html' => $item['body_html'],
                'published_at' => $item['published_at'],
                'fetched_at' => now(),
                'content_hash' => RssItem::computeContentHash($item['url'], $item['title']),
                'source_word_count' => RssItem::countWords($item['body_html']),
                'is_processed' => false,
                'is_duplicate' => false,
            ]);

            $inserted++;
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
            'message' => "Fetched {$this->rssSource->feed_url}: {$inserted} of ".count($items).' items stored.',
            'payload' => ['fetched' => count($items), 'inserted' => $inserted],
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
