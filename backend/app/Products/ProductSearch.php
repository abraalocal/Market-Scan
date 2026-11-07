<?php

namespace App\Products;

/** Web lookup for a product not on abraa.com yet (FR-51). Results: [['title', 'url', 'snippet', 'source']]. */
interface ProductSearch
{
    public function search(string $query): array;
}
