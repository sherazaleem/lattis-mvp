<?php

namespace App\Services;

use App\DataTransferObjects\PromptPayload;
use App\Models\RssItem;
use App\Models\Site;

/**
 * Stage 3 — Prompt Builder. Inline, no external calls.
 * Build in Roadmap Stage 3.
 *
 * TODO:
 *  - Load $site->dna (SiteDna) and its prompt_fragment.
 *  - MVP: use a single hardcoded default author fragment (real author
 *    persona management is deferred — see MVP_ROADMAP.md "OUT of the MVP").
 *  - Assemble system + user prompt from: site DNA fragment, author fragment,
 *    source article facts ($rssItem->title, ->body_html), and
 *    ai_aggressiveness (1=minimal rewrite, 5=fully original).
 *  - Return a PromptPayload. Never make an external call from this class.
 */
class PromptBuilderService
{
    public function build(RssItem $rssItem, Site $site): PromptPayload
    {
        // TODO: implement per docstring above.
        return new PromptPayload(
            systemPrompt: '',
            userPrompt: '',
            metadata: [
                'site_id' => $site->id,
                'rss_item_id' => $rssItem->id,
            ],
        );
    }
}
