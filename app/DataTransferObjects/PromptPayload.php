<?php

namespace App\DataTransferObjects;

/**
 * Assembled by PromptBuilderService (Site DNA + author fragment + source facts).
 * Never built with an external call — pure assembly.
 */
class PromptPayload
{
    public function __construct(
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly array $metadata = [], // site_id, rss_item_id, author_identifier, cluster_slug, etc.
    ) {}
}
