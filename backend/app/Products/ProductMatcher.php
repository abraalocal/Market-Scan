<?php

namespace App\Products;

/**
 * Decides whether a search result is exactly the product seen on the shelf and fills the listing (FR-52, FR-53).
 * Returns ['exact' => bool, 'confidence' => float, 'reasons' => string, 'source_url' => ?string, 'source_name' => ?string,
 *          'title_en', 'title_ar', 'desc_en', 'desc_ar', 'specs' => array, 'images' => string[] (URLs)]
 */
interface ProductMatcher
{
    /** @param array $seen brand, line, name, size, barcode, category; @param array $results from ProductSearch; @param ?string $photo JPEG bytes of the close-up */
    public function match(array $seen, array $results, ?string $photo): array;
}
