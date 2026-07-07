<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_url', 'cluster_id', 'site_id', 'fetch_frequency_minutes',
        'priority', 'language', 'is_active', 'status', 'last_fetched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(NicheCluster::class, 'cluster_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RssItem::class, 'source_id');
    }

    public function isDueForFetch(): bool
    {
        if (!$this->last_fetched_at) {
            return true;
        }

        return $this->last_fetched_at->addMinutes($this->fetch_frequency_minutes)->isPast();
    }
}
