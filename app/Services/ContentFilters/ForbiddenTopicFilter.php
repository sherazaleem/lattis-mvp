<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\Site;

class ForbiddenTopicFilter implements ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        $forbidden = array_filter((array) ($site->dna?->forbidden_topics ?? []));

        if (empty($forbidden)) {
            return new FilterResult('skip', $this->getFilterName(), 'No forbidden topics configured for this site.');
        }

        $haystack = strtolower(strip_tags($article->bodyHtml).' '.$article->title);

        foreach ($forbidden as $topic) {
            $needle = strtolower(trim((string) $topic));

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return new FilterResult(
                    'fail',
                    $this->getFilterName(),
                    "Forbidden topic keyword matched: \"{$topic}\".",
                );
            }
        }

        return new FilterResult('pass', $this->getFilterName());
    }

    public function getFilterName(): string
    {
        return 'forbidden_topic';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        return true;
    }
}
