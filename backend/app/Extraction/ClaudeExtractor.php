<?php

namespace App\Extraction;

use Anthropic\Client;
use App\Models\Visit;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * EXTRACTION_DRIVER=claude: one Claude call reads the business card and every shelf and close-up photo
 * and returns card fields and products as JSON (structured output).
 */
class ClaudeExtractor implements Extractor
{
    public const MAX_PHOTOS = 20;

    public function __construct(private ExtractionResult $result, private Client $claude) {}

    public function start(Visit $visit): void
    {
        $content = [];
        $photos = $visit->photos()->orderByRaw("case type when 'card' then 0 when 'storefront' then 1 when 'shelf' then 2 else 3 end")->orderBy('id')->limit(self::MAX_PHOTOS)->get();
        foreach ($photos as $p) {
            $content[] = ['type' => 'text', 'text' => "Photo {$p->id} ({$p->type}):"];
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode(Storage::get($p->path))]];
        }
        $content[] = ['type' => 'text', 'text' => self::INSTRUCTIONS];

        $message = $this->claude->beta->messages->create(
            model: config('marketscan.ai.model'),
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => $content]],
            outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        if ($message->stopReason === 'refusal') {
            $this->result->store($visit, [], [], 'The AI could not read these photos. Please check them and try again.', 'claude');

            return;
        }
        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }
        if (! is_array($json)) {
            throw new RuntimeException('Claude returned no JSON (stop reason '.$message->stopReason.').');
        }
        $this->result->store($visit, $json['fields'] ?? [], $json['products'] ?? [], null, 'claude');
    }

    private const INSTRUCTIONS = <<<'TXT'
These photos are from one shop visit in the UAE: a business card, the shop front, shelves and close-ups of products.
1. From the business card, read: company_name, contact_name, whatsapp (mobile), landline, email, website, area. Give each a confidence 0..1; below 0.8 means a person should check it. Leave out fields that are not on the card. Copy digits exactly; if a digit is unreadable, use "?" for it.
2. From the shelf and close-up photos, list every distinct product you can identify: category (one of Hair Care, Skin Care, Body Care, Fragrance, Makeup, General), brand, line (product range, or null), name, size (e.g. "250 ml"), barcode only if readable. confidence 0..1 that brand, line, name and size are all right. If a close-up shows the product, set closeup_photo_id to that photo's number. List each product once even if several are on the shelf.
Write brand names as printed on the pack (Latin script when the pack has it). Ignore people; do not describe faces.
TXT;

    private const SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['fields', 'products'],
        'properties' => [
            'fields' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['key', 'value', 'confidence'],
                'properties' => [
                    'key' => ['type' => 'string', 'enum' => ['company_name', 'contact_name', 'whatsapp', 'landline', 'email', 'website', 'area']],
                    'value' => ['type' => 'string'],
                    'confidence' => ['type' => 'number'],
                ],
            ]],
            'products' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['id', 'category', 'brand', 'line', 'name', 'size', 'barcode', 'confidence', 'closeup_photo_id'],
                'properties' => [
                    'id' => ['type' => 'string'],
                    'category' => ['type' => 'string'],
                    'brand' => ['type' => 'string'],
                    'line' => ['type' => ['string', 'null']],
                    'name' => ['type' => 'string'],
                    'size' => ['type' => ['string', 'null']],
                    'barcode' => ['type' => ['string', 'null']],
                    'confidence' => ['type' => 'number'],
                    'closeup_photo_id' => ['type' => ['integer', 'null']],
                ],
            ]],
        ],
    ];
}
