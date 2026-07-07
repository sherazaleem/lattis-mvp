<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\Site;

class DuplicateParagraphFilter implements ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        $seen = [];

        foreach ($this->extractParagraphs($article->bodyHtml) as $paragraph) {
            $normalized = strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($paragraph))));

            if ($normalized === '') {
                continue;
            }

            if (isset($seen[$normalized])) {
                return new FilterResult(
                    'fail',
                    $this->getFilterName(),
                    'Duplicate paragraph detected: "'.substr($normalized, 0, 80).'..."',
                );
            }

            $seen[$normalized] = true;
        }

        return new FilterResult('pass', $this->getFilterName());
    }

    public function getFilterName(): string
    {
        return 'duplicate_paragraph';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        return true;
    }

    /** @return string[] */
    private function extractParagraphs(string $html): array
    {
        if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches) && !empty($matches[1])) {
            return $matches[1];
        }

        return preg_split('/\n{2,}/', $html) ?: [];
    }
}
