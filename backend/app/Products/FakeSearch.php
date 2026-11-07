<?php

namespace App\Products;

use App\Catalogue\SampleCatalogue;
use App\Support\Names;

/** PRODUCT_SEARCH_DRIVER=fake: a result for products in the sample catalogue, nothing for the rest. */
class FakeSearch implements ProductSearch
{
    public function search(string $query): array
    {
        foreach (SampleCatalogue::PRODUCTS as [$cat, $brand, $line, $name, $size]) {
            $title = trim("$brand $line $name $size");
            if (Names::squash($query) === Names::squash($title)) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(Names::key($title))), '-');

                return [['title' => "$title | Official store", 'url' => "https://brand.example/$slug", 'snippet' => "$title. Original product.", 'source' => 'brand.example']];
            }
        }

        return [];
    }
}
