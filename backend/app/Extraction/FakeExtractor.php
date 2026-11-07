<?php

namespace App\Extraction;

use App\Catalogue\SampleCatalogue;
use App\Models\Visit;

/** EXTRACTION_DRIVER=fake: realistic sample result (one unreadable landline, one unclear product). */
class FakeExtractor implements Extractor
{
    public function __construct(private ExtractionResult $result) {}

    public function start(Visit $visit): void
    {
        $seed = abs(crc32($visit->uuid));
        $s = $visit->supplier;
        $company = $s?->company_name ?? $visit->shop_name;
        $phone = $s?->phones()->orderBy('id')->value('phone') ?? '+97150'.str_pad((string) ($seed % 10_000_000), 7, '0', STR_PAD_LEFT);
        $slug = substr(preg_replace('/[^a-z0-9]+/', '', strtolower($company)) ?: 'shop', 0, 18);
        $fields = [
            ['key' => 'company_name', 'value' => $company, 'confidence' => 0.97],
            ['key' => 'contact_name', 'value' => $s?->contact_name ?? 'Khalid Rahman', 'confidence' => 0.93],
            ['key' => 'whatsapp', 'value' => $phone, 'confidence' => 0.95],
            ['key' => 'email', 'value' => $s?->email ?? "sales@{$slug}.ae", 'confidence' => 0.9],
            ['key' => 'landline', 'value' => '04 2?? 1180', 'confidence' => 0.41],
            ['key' => 'area', 'value' => $s?->area ?? 'Deira, Dubai', 'confidence' => 0.99],
        ];

        $all = SampleCatalogue::PRODUCTS;
        $n = min(count($all), max(8, $visit->photos()->where('type', 'shelf')->count() * 6 + 2));
        $closeups = $visit->photos()->where('type', 'closeup')->orderBy('id')->pluck('id')->all();
        $products = [];
        for ($i = 0; $i < $n; $i++) {
            [$cat, $brand, $line, $name, $size, $barcode] = $all[($seed + $i) % count($all)];
            $unclear = $i === 3;
            $products[] = [
                'id' => "p$i", 'category' => $cat, 'brand' => $brand, 'line' => $line,
                'name' => $unclear ? "$brand product (name unclear)" : $name, 'size' => $size, 'barcode' => $barcode,
                'confidence' => $unclear ? 0.52 : 0.9, 'closeup_photo_id' => $closeups[$i] ?? null,
            ];
        }
        $this->result->store($visit, $fields, $products, null, 'fake');
    }
}
