<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\NicheCluster;
use App\Models\Site;

/**
 * HOLD is absolute: applies to any cluster with review_level = 'mandatory'
 * (Health, Satire, and any other legal-sensitive niche flagged that way).
 * Forces human review regardless of the site's auto_publish setting — see
 * .cursorrules rule 3. This filter must run FIRST in OutputValidator's
 * filter list so a later FAIL can never short-circuit past it.
 */
class HealthSatireHoldFilter implements ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        return new FilterResult(
            'hold',
            $this->getFilterName(),
            'Site cluster requires mandatory human review (Health/Satire/legal-sensitive) — absolute, regardless of auto_publish.',
        );
    }

    public function getFilterName(): string
    {
        return 'health_satire_hold';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        if ($clusterSlug === '') {
            return false;
        }

        return NicheCluster::where('slug', $clusterSlug)->value('review_level') === 'mandatory';
    }
}
