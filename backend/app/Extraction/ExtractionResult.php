<?php

namespace App\Extraction;

use App\Catalogue\ProductTree;
use App\Models\Visit;
use App\Remote\AbraaClient;

/**
 * Stores an AI result on a visit (FR-20..FR-23).
 * Product status is computed here, never trusted from the AI: on abraa.com → on_abraa; confidence < 0.7 → check; else new.
 */
class ExtractionResult
{
    public const LOW_CONFIDENCE = 0.7;

    public const FIELD_KEYS = ['company_name', 'contact_name', 'whatsapp', 'landline', 'email', 'website', 'area'];

    public function __construct(private ProductTree $tree, private AbraaClient $abraa) {}

    public function store(Visit $visit, array $fields, array $products, ?string $error = null, string $driver = 'unknown'): Visit
    {
        if ($error) {
            $visit->update(['status' => 'needs_review', 'extraction' => array_merge($visit->extraction ?? [], ['error' => $error, 'driver' => $driver])]);

            return $visit;
        }
        $closeups = $visit->photos()->where('type', 'closeup')->pluck('id')->all();
        $clean = [];
        $ids = [];
        foreach (array_values($products) as $i => $p) {
            $id = (string) ($p['id'] ?? "p$i");
            $id = isset($ids[$id]) ? "$id-$i" : $id;
            $ids[$id] = true;
            $row = [
                'id' => $id,
                'category' => (string) ($p['category'] ?? ''),
                'brand' => (string) $this->tree->canonicalBrand((string) ($p['brand'] ?? '')),
                'line' => ($p['line'] ?? null) ?: null,
                'name' => (string) ($p['name'] ?? ''),
                'size' => ($p['size'] ?? null) ?: null,
                'barcode' => ($p['barcode'] ?? null) ?: null,
                'confidence' => round((float) ($p['confidence'] ?? 0), 2),
                'closeup_photo_id' => in_array((int) ($p['closeup_photo_id'] ?? 0), $closeups, true) ? (int) $p['closeup_photo_id'] : null,
            ];
            $row['status'] = $this->status($row);
            $clean[] = $row;
        }
        $visit->extraction = [
            'fields' => array_values(array_map(fn ($f) => ['key' => $f['key'], 'value' => (string) ($f['value'] ?? ''), 'confidence' => round((float) ($f['confidence'] ?? 0), 2)],
                array_filter($fields, fn ($f) => in_array($f['key'] ?? null, self::FIELD_KEYS, true)))),
            'products' => $clean,
            'driver' => $driver,
            'at' => now()->toIso8601ZuluString(),
        ];
        $visit->status = 'review';
        $visit->save();

        return $visit;
    }

    public function status(array $p): string
    {
        if ($this->abraaProductId($p)) {
            return 'on_abraa';
        }

        return (float) ($p['confidence'] ?? 0) < self::LOW_CONFIDENCE ? 'check' : 'new';
    }

    /** abraa.com product id: cached on the product ref, else asked from abraa.com (and cached). */
    public function abraaProductId(array $p): ?int
    {
        $ref = $this->tree->findProduct($p);
        if ($ref?->abraa_product_id) {
            return $ref->abraa_product_id;
        }
        $id = $this->abraa->findProduct(array_intersect_key($p, array_flip(['brand', 'line', 'name', 'size', 'barcode'])));
        if ($id && $ref) {
            $ref->update(['abraa_product_id' => $id]);
        }

        return $id;
    }
}
