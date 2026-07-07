<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDna extends Model
{
    use HasFactory;

    protected $table = 'site_dna';

    protected $fillable = [
        'site_id', 'niche', 'angle', 'audience', 'format_rules', 'forbidden_topics',
        'cta_style', 'ai_aggressiveness', 'seo_posture', 'monetisation_rules',
        'prompt_fragment', 'min_word_count', 'version',
    ];

    protected $casts = [
        'format_rules' => 'array',
        'forbidden_topics' => 'array',
        'seo_posture' => 'array',
        'monetisation_rules' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Increment on every change; generated_articles stores the version that produced them. */
    protected static function booted(): void
    {
        static::updating(function (SiteDna $dna) {
            if ($dna->isDirty() && !$dna->isDirty('version')) {
                $dna->version = $dna->version + 1;
            }
        });
    }
}
