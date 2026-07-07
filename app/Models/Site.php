<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain', 'stack_type', 'cluster_id', 'max_posts_per_day', 'timezone',
        'auto_publish', 'cms_api_url', 'language', 'deployment_state', 'is_active',
    ];

    protected $casts = [
        'auto_publish' => 'boolean',
        'is_active' => 'boolean',
        'deployment_state' => 'array',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(NicheCluster::class, 'cluster_id');
    }

    public function dna(): HasOne
    {
        return $this->hasOne(SiteDna::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    public function generatedArticles(): HasMany
    {
        return $this->hasMany(GeneratedArticle::class);
    }

    /**
     * Effective auto-publish setting for this site. A mandatory-review cluster
     * always forces this to false, regardless of the site's own flag — see
     * .cursorrules rule 3 (the HOLD rule) and Sprint 5.6 in the original plan.
     */
    public function effectiveAutoPublish(): bool
    {
        if ($this->cluster && $this->cluster->requiresMandatoryReview()) {
            return false;
        }

        return (bool) $this->auto_publish;
    }
}
