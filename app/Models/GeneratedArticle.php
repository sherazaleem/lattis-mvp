<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'rss_item_id', 'site_id', 'author_identifier', 'status',
        'title', 'slug', 'body', 'meta_description', 'focus_keyword',
        'quality_score', 'quality_flags', 'prompt_version', 'author_version',
        'model_used', 'provider', 'tokens_input', 'tokens_output', 'generation_ms',
        'approved_by', 'approved_at', 'reject_reason', 'scheduled_at',
        'published_at', 'external_id', 'external_url',
    ];

    protected $casts = [
        'quality_flags' => 'array',
        'approved_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    /**
     * Valid states and transitions — see docs/MVP_ROADMAP.md and the original
     * Dev Plan Section 6. Do not invent new states or skip steps.
     */
    public const STATES = [
        'queued', 'generating', 'generated', 'review', 'approved',
        'scheduled', 'publishing', 'published', 'failed', 'rejected', 'skipped',
    ];

    public const TRANSITIONS = [
        'queued' => ['generating'],
        'generating' => ['generated', 'failed'],
        // A hard FAIL from the quality gate goes straight to 'rejected' — no
        // reviewer time wasted on content that failed an objective check.
        // A HOLD, or a site without auto-publish, goes to 'review' instead.
        'generated' => ['review', 'approved', 'rejected'],
        'review' => ['approved', 'rejected'],
        'approved' => ['scheduled'],
        'scheduled' => ['publishing'],
        'publishing' => ['published', 'failed'],
        'failed' => ['queued'], // manual retry only
        'published' => [],
        'rejected' => [],
        'skipped' => [],
    ];

    public function rssItem(): BelongsTo
    {
        return $this->belongsTo(RssItem::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function transitionTo(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \RuntimeException("Invalid state transition for GeneratedArticle #{$this->id}: {$this->status} -> {$newStatus}");
        }

        $this->update(['status' => $newStatus]);
    }

    /** True if any quality flag is a HOLD — forces review regardless of auto_publish. See .cursorrules. */
    public function hasHoldFlag(): bool
    {
        foreach ((array) $this->quality_flags as $flag) {
            if (is_array($flag) && ($flag['outcome'] ?? null) === 'hold') {
                return true;
            }
        }

        return false;
    }
}
