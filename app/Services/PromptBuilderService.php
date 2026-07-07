<?php

namespace App\Services;

use App\DataTransferObjects\PromptPayload;
use App\Models\RssItem;
use App\Models\Site;
use App\Models\SiteDna;
use RuntimeException;

/**
 * Assembles a PromptPayload from site_dna + a hardcoded default author
 * fragment + the source RssItem. Pure assembly — never makes an external
 * call. Real author persona management is deferred (MVP_ROADMAP.md "OUT of
 * the MVP") — every site gets the same hardcoded author fragment for now.
 */
class PromptBuilderService
{
    public const DEFAULT_AUTHOR_IDENTIFIER = 'default_author_v1';

    private const DEFAULT_AUTHOR_FRAGMENT = <<<'TEXT'
        You write as Alex Morgan, a knowledgeable and approachable industry
        writer. Clear, concise, plain language — no hype, no filler, no
        clickbait phrasing. State facts directly and cite them from the
        source material rather than inventing new claims.
        TEXT;

    public function build(RssItem $rssItem, Site $site): PromptPayload
    {
        $dna = $site->dna;

        if (!$dna) {
            throw new RuntimeException("Site [{$site->id}] has no site_dna record — cannot build a prompt.");
        }

        return new PromptPayload(
            systemPrompt: $this->buildSystemPrompt($dna),
            userPrompt: $this->buildUserPrompt($rssItem, $dna),
            metadata: [
                'site_id' => $site->id,
                'rss_item_id' => $rssItem->id,
                'author_identifier' => self::DEFAULT_AUTHOR_IDENTIFIER,
                'cluster_slug' => $site->cluster?->slug,
                'ai_aggressiveness' => $dna->ai_aggressiveness,
                'min_word_count' => $dna->min_word_count,
                'prompt_version' => $dna->version,
            ],
        );
    }

    private function buildSystemPrompt(SiteDna $dna): string
    {
        $lines = [
            self::DEFAULT_AUTHOR_FRAGMENT,
            '',
            "Niche: {$dna->niche}",
        ];

        if ($dna->angle) {
            $lines[] = "Angle: {$dna->angle}";
        }

        if ($dna->audience) {
            $lines[] = "Audience: {$dna->audience}";
        }

        if ($dna->cta_style) {
            $lines[] = "Call-to-action style: {$dna->cta_style}";
        }

        if (!empty($dna->format_rules)) {
            $lines[] = 'Format rules: '.implode('; ', (array) $dna->format_rules);
        }

        if ($dna->prompt_fragment) {
            $lines[] = '';
            $lines[] = $dna->prompt_fragment;
        }

        if (!empty($dna->forbidden_topics)) {
            $lines[] = '';
            $lines[] = 'Never write about, mention, or allude to any of the following, under any circumstance: '
                .implode(', ', (array) $dna->forbidden_topics).'.';
        }

        return implode("\n", $lines);
    }

    private function buildUserPrompt(RssItem $rssItem, SiteDna $dna): string
    {
        $aggressivenessInstruction = match (true) {
            $dna->ai_aggressiveness <= 1 => 'Make only light edits for clarity and grammar. Preserve the original wording and structure as closely as possible.',
            $dna->ai_aggressiveness === 2 => 'Rewrite lightly — keep the original structure but improve phrasing and flow.',
            $dna->ai_aggressiveness === 4 => 'Rewrite substantially in your own voice and structure, using the source only for facts.',
            $dna->ai_aggressiveness >= 5 => 'Fully rewrite from scratch in your own voice and structure. Use the source purely as a fact reference — do not mirror its wording, structure, or paragraph order at all.',
            default => 'Rewrite in your own voice, reorganizing as needed, using the source for facts and context.',
        };

        return <<<TEXT
            Source article title: {$rssItem->title}

            Source article content:
            {$rssItem->body_html}

            ---

            Write a new article based on the source above.

            {$aggressivenessInstruction}

            Minimum length: {$dna->min_word_count} words.

            Output the article body only, as HTML paragraphs (<p> tags). Do
            not include a title, front matter, or any commentary about these
            instructions.
            TEXT;
    }
}
