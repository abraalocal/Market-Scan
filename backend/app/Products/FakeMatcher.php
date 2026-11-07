<?php

namespace App\Products;

use App\Support\Names;

/** PRODUCT_MATCH_DRIVER=fake: exact when a result title contains brand, name and size. */
class FakeMatcher implements ProductMatcher
{
    public function match(array $seen, array $results, ?string $photo): array
    {
        $full = trim(implode(' ', array_filter([$seen['brand'] ?? '', $seen['line'] ?? '', $seen['name'] ?? '', $seen['size'] ?? ''])));
        foreach ($results as $r) {
            $t = Names::squash($r['title']);
            if (str_contains($t, Names::squash($seen['brand'] ?? '-')) && str_contains($t, Names::squash($seen['name'] ?? '-')) && str_contains($t, Names::squash($seen['size'] ?? ''))) {
                return [
                    'exact' => true, 'confidence' => 0.95, 'reasons' => 'Brand, name and size match the result title.',
                    'source_url' => $r['url'], 'source_name' => $r['source'],
                    'title_en' => "$full – Wholesale", 'title_ar' => "{$full} – جملة",
                    'desc_en' => "$full, original product, for wholesale orders from UAE suppliers on Abraa.",
                    'desc_ar' => "{$full}، منتج أصلي لطلبات الجملة من موردين في الإمارات عبر أبرا.",
                    'specs' => array_filter(['Brand' => $seen['brand'] ?? null, 'Line' => $seen['line'] ?? null, 'Size' => $seen['size'] ?? null]),
                    'images' => [],
                ];
            }
        }

        return ['exact' => false, 'confidence' => 0.0, 'reasons' => $results ? 'No result matched brand, name and size.' : 'Nothing found on the web.', 'source_url' => null, 'source_name' => null, 'images' => []];
    }
}
