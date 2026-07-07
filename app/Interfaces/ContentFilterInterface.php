<?php

namespace App\Interfaces;

use App\Models\Site;
use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;

/**
 * Every quality-gate check (word count, duplicate paragraph, source similarity,
 * forbidden topic, medical claim, satire moderation, ...) implements this.
 * OutputValidator runs the filters that apply to a site's cluster in sequence
 * and never contains the rule logic itself. Adding a new rule = one new class,
 * zero changes to OutputValidator.
 *
 * FilterResult must be able to express PASS, FAIL, HOLD, or SKIP — see
 * docs/MVP_ROADMAP.md Stage 3 for which result each check produces.
 * A HOLD result is absolute: it forces human review regardless of a site's
 * auto_publish setting. Never let calling code bypass a HOLD.
 */
interface ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult;

    public function getFilterName(): string;

    public function appliesTo(string $clusterSlug): bool;
}
