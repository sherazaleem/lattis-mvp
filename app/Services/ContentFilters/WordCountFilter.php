<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\Site;

class WordCountFilter implements ContentFilterInterface
{
    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        $minWords = $site->dna?->min_word_count ?? 0;
        $wordCount = $this->countWords($article->bodyHtml);

        if ($wordCount < $minWords) {
            return new FilterResult(
                'fail',
                $this->getFilterName(),
                "Word count ({$wordCount}) is below site minimum ({$minWords}).",
            );
        }

        return new FilterResult('pass', $this->getFilterName());
    }

    public function getFilterName(): string
    {
        return 'word_count';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        return true;
    }

    private function countWords(string $html): int
    {
        $text = trim(strip_tags($html));

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }
}
