<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use SimpleXMLElement;

/**
 * Minimal RSS 2.0 / Atom parser built on PHP's built-in SimpleXML — no
 * external feed-parsing package. Returns plain arrays, not model instances;
 * FetchRssFeedJob owns dedup/word-count/persistence decisions.
 */
class RssFeedParser
{
    /**
     * @return array<int, array{title: string, url: string, published_at: ?Carbon, body_html: string}>
     */
    public function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = collect(libxml_get_errors())->pluck('message')->map(trim(...))->implode('; ');
            libxml_clear_errors();

            throw new RuntimeException("Malformed feed XML: {$errors}");
        }

        libxml_clear_errors();

        if (isset($doc->channel)) {
            return $this->parseRss($doc);
        }

        if ($doc->getName() === 'feed') {
            return $this->parseAtom($doc);
        }

        throw new RuntimeException('Unrecognized feed format (not RSS 2.0 or Atom).');
    }

    private function parseRss(SimpleXMLElement $doc): array
    {
        $items = [];

        foreach ($doc->channel->item as $item) {
            $namespaces = $item->getNamespaces(true);
            $content = '';

            if (isset($namespaces['content'])) {
                $content = (string) $item->children($namespaces['content'])->encoded;
            }

            $items[] = [
                'title' => trim((string) $item->title),
                'url' => trim((string) $item->link),
                'published_at' => $this->parseDate((string) ($item->pubDate ?? '')),
                'body_html' => $content !== '' ? $content : trim((string) ($item->description ?? '')),
            ];
        }

        return $items;
    }

    private function parseAtom(SimpleXMLElement $doc): array
    {
        $items = [];

        foreach ($doc->entry as $entry) {
            $link = '';

            foreach ($entry->link as $l) {
                $attrs = $l->attributes();
                if (!isset($attrs['rel']) || (string) $attrs['rel'] === 'alternate') {
                    $link = (string) $attrs['href'];
                    break;
                }
            }

            $body = (string) ($entry->content ?? '');
            if ($body === '') {
                $body = (string) ($entry->summary ?? '');
            }

            $items[] = [
                'title' => trim((string) $entry->title),
                'url' => trim($link),
                'published_at' => $this->parseDate((string) ($entry->updated ?? $entry->published ?? '')),
                'body_html' => trim($body),
            ];
        }

        return $items;
    }

    private function parseDate(string $date): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
