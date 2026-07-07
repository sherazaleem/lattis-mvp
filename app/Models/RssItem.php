<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssItem extends Model
{
    use HasFactory;

    public $timestamps = false; // only created_at exists, per schema

    protected $fillable = [
        'source_id', 'url', 'title', 'body_html', 'published_at', 'fetched_at',
        'content_hash', 'source_word_count', 'is_processed', 'is_duplicate',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
        'is_processed' => 'boolean',
        'is_duplicate' => 'boolean',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(RssSource::class, 'source_id');
    }

    /** SHA-256(url + title) — the non-negotiable dedup key. See .cursorrules rule on dedup. */
    public static function computeContentHash(string $url, string $title): string
    {
        return hash('sha256', $url.$title);
    }

    public const MIN_SOURCE_WORD_COUNT = 300;

    public function meetsMinimumLength(): bool
    {
        return $this->source_word_count >= self::MIN_SOURCE_WORD_COUNT;
    }

    public static function countWords(?string $html): int
    {
        $text = trim(strip_tags((string) $html));

        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $text));
    }
}
