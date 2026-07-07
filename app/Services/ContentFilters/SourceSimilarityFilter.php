<?php

namespace App\Services\ContentFilters;

use App\DataTransferObjects\ArticlePayload;
use App\DataTransferObjects\FilterResult;
use App\Interfaces\ContentFilterInterface;
use App\Models\GeneratedArticle;
use App\Models\Site;

/**
 * FAILs when the generated article is too close to the source (cosine
 * similarity, bag-of-words) — i.e. insufficient rewriting occurred.
 */
class SourceSimilarityFilter implements ContentFilterInterface
{
    private const THRESHOLD = 0.85;

    public function check(ArticlePayload $article, Site $site): FilterResult
    {
        $sourceHtml = GeneratedArticle::with('rssItem')->find($article->generatedArticleId)?->rssItem?->body_html ?? '';
        $similarity = $this->cosineSimilarity($this->tokenize($sourceHtml), $this->tokenize($article->bodyHtml));

        if ($similarity > self::THRESHOLD) {
            return new FilterResult(
                'fail',
                $this->getFilterName(),
                sprintf('Source similarity %.2f exceeds threshold %.2f.', $similarity, self::THRESHOLD),
            );
        }

        return new FilterResult('pass', $this->getFilterName());
    }

    public function getFilterName(): string
    {
        return 'source_similarity';
    }

    public function appliesTo(string $clusterSlug): bool
    {
        return true;
    }

    /** @return array<string, int> word => frequency */
    private function tokenize(string $html): array
    {
        $text = strtolower(strip_tags($html));
        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_count_values($words ?: []);
    }

    /**
     * @param array<string, int> $a
     * @param array<string, int> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $dot = 0;
        $magA = 0;
        $magB = 0;

        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $term) {
            $va = $a[$term] ?? 0;
            $vb = $b[$term] ?? 0;
            $dot += $va * $vb;
            $magA += $va ** 2;
            $magB += $vb ** 2;
        }

        if ($magA === 0 || $magB === 0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
