<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\Site;

/**
 * Only meaningful once GenerateSeoFieldsJob has populated title/slug/meta —
 * not part of the core OutputValidator filter list (which runs before those
 * fields exist). Invoked directly by GenerateSeoFieldsJob instead.
 */
class SeoFieldsFilter implements ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        $missing = array_filter([
            trim((string) $article->title) === '' ? 'title' : null,
            trim((string) $article->slug) === '' ? 'slug' : null,
            trim((string) $article->metaDescription) === '' ? 'meta_description' : null,
        ]);

        if (!empty($missing)) {
            return new FilterResult(
                'fail',
                $this->getFilterName(),
                'Missing SEO field(s): '.implode(', ', $missing).'.',
            );
        }

        return new FilterResult('pass', $this->getFilterName());
    }

    public function getFilterName(): string
    {
        return 'seo_fields_non_empty';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        return true;
    }
}
