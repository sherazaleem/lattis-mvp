<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NicheCluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'review_level', 'default_max_posts_per_day', 'content_filter_config',
    ];

    protected $casts = [
        'content_filter_config' => 'array',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'cluster_id');
    }

    public function rssSources(): HasMany
    {
        return $this->hasMany(RssSource::class, 'cluster_id');
    }

    /** review_level = 'mandatory' means auto_publish is force-disabled — see HOLD rule in .cursorrules */
    public function requiresMandatoryReview(): bool
    {
        return $this->review_level === 'mandatory';
    }
}
