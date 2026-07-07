<?php

namespace App\DataTransferObjects;

/**
 * The shape passed to publishing adapters and content filters. Carries
 * everything needed to publish or validate without another DB round trip.
 */
class ArticlePayload
{
    public function __construct(
        public readonly int $generatedArticleId,
        public readonly string $mongoId,
        public readonly string $title,
        public readonly string $bodyHtml,
        public readonly ?string $slug = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $focusKeyword = null,
        public readonly ?string $imageAltText = null,
        public readonly array $faqSchema = [],
    ) {}
}
