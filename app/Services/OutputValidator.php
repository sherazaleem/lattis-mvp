<?php

namespace App\Services;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\Site;

/**
 * Stage 5 — Output Validator. Runs the ContentFilterInterface checks that
 * apply to a site's cluster, in sequence. Build in Roadmap Stage 3.
 *
 * MVP filter set (see MVP_ROADMAP.md Stage 3) — only build these to start:
 *   WordCountFilter, DuplicateParagraphFilter, SourceSimilarityFilter,
 *   ForbiddenTopicFilter, SeoFieldsFilter (once SEO fields exist).
 * Add MedicalClaimFilter / SatireReviewFilter only if a pilot site needs them.
 *
 * This class must never contain filter rule logic itself — only orchestration.
 * A HOLD result from any filter is absolute: it must force review regardless
 * of site.effectiveAutoPublish(). Never let a FAIL short-circuit past a HOLD
 * check that hasn't run yet, and never let calling code skip a HOLD.
 */
class OutputValidator
{
    /** @param ContentFilterInterface[] $filters */
    public function __construct(
        protected array $filters = [],
    ) {}

    /** @return FilterResult[] */
    public function validate(ArticlePayload $article, Site $site): array
    {
        $clusterSlug = $site->cluster?->slug ?? '';
        $results = [];

        foreach ($this->filters as $filter) {
            if (!$filter->appliesTo($clusterSlug)) {
                continue;
            }

            $result = $filter->check($article, $site);
            $results[] = $result;

            if ($result->isFail()) {
                // TODO: stop here — article should be rejected. Still log
                // remaining filters as "not run" if that matters for audit.
                break;
            }
        }

        return $results;
    }

    public function anyHold(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->isHold()) {
                return true;
            }
        }

        return false;
    }

    public function anyFail(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->isFail()) {
                return true;
            }
        }

        return false;
    }
}
