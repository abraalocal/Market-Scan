<?php

namespace App\Products;

use Anthropic\Client;
use RuntimeException;

/**
 * PRODUCT_MATCH_DRIVER=claude: Claude compares the shelf photo and what was read from it with the search results,
 * opens the most promising pages (web fetch) and returns the listing as structured JSON.
 */
class ClaudeMatcher implements ProductMatcher
{
    public function __construct(private Client $claude) {}

    public function match(array $seen, array $results, ?string $photo): array
    {
        $content = [];
        if ($photo) {
            $content[] = ['type' => 'text', 'text' => 'Close-up photo from the shop shelf:'];
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($photo)]];
        }
        $content[] = ['type' => 'text', 'text' => "Read from the shelf:\n".json_encode($seen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nWeb search results:\n".json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n\n".self::INSTRUCTIONS];

        $messages = [['role' => 'user', 'content' => $content]];
        for ($turn = 0; $turn < 4; $turn++) {
            $m = $this->claude->beta->messages->create(
                model: config('marketscan.ai.model'),
                maxTokens: 16000,
                messages: $messages,
                tools: [['type' => 'web_fetch_20260209', 'name' => 'web_fetch', 'maxUses' => 4, 'maxContentTokens' => 20000]],
                outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
            if ($m->stopReason === 'refusal') {
                return ['exact' => false, 'confidence' => 0.0, 'reasons' => 'The AI declined this request.', 'source_url' => null, 'source_name' => null, 'images' => []];
            }
            if ($m->stopReason !== 'pause_turn') {
                break;
            }
            // Long server-tool turn: send the partial answer back so Claude continues.
            $messages[] = ['role' => 'assistant', 'content' => $m->content];
        }
        $text = null;
        foreach (array_reverse($m->content) as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }
        $out = json_decode((string) $text, true);
        if (! is_array($out)) {
            throw new RuntimeException('Claude returned no JSON for the product match.');
        }

        return $out + ['images' => [], 'specs' => []];
    }

    private const INSTRUCTIONS = <<<'TXT'
Decide whether one of these web results is exactly the product on the shelf: same brand, same line, same product, same size or volume (and same barcode if both are known). A different size, scent, colour or a bundle is NOT exact. Prefer the brand's own site, then large reputable retailers. Open up to 4 of the most promising result pages with web_fetch to check.
If exact: fill a wholesale listing for abraa.com from the page: title_en (brand, line, product, size; end with " – Wholesale"), title_ar (Arabic translation, end with " – جملة"), desc_en and desc_ar (2-4 factual sentences each: what it is, key benefits, use; no prices, no claims that are not on the page), specs (key facts such as size, ingredients highlights, skin/hair type, country of origin, as name/value pairs), images (direct URLs of the product's own pack shots on that page, up to 4, no logos or banners), source_url and source_name of the page used.
If not exact or unsure: exact = false, explain in reasons what differs or is missing, leave listing fields empty.
confidence is 0..1 that the match is exact.
TXT;

    private const SCHEMA = [
        'type' => 'object', 'additionalProperties' => false,
        'required' => ['exact', 'confidence', 'reasons', 'source_url', 'source_name', 'title_en', 'title_ar', 'desc_en', 'desc_ar', 'specs', 'images'],
        'properties' => [
            'exact' => ['type' => 'boolean'],
            'confidence' => ['type' => 'number'],
            'reasons' => ['type' => 'string'],
            'source_url' => ['type' => ['string', 'null']],
            'source_name' => ['type' => ['string', 'null']],
            'title_en' => ['type' => ['string', 'null']],
            'title_ar' => ['type' => ['string', 'null']],
            'desc_en' => ['type' => ['string', 'null']],
            'desc_ar' => ['type' => ['string', 'null']],
            'specs' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['name', 'value'], 'properties' => ['name' => ['type' => 'string'], 'value' => ['type' => 'string']]]],
            'images' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ];
}
