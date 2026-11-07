<?php

namespace App\Products;

use Illuminate\Support\Facades\Http;

/** PRODUCT_SEARCH_DRIVER=serpapi: SerpAPI (Google results). */
class SerpApiSearch implements ProductSearch
{
    public function search(string $query): array
    {
        $res = Http::timeout(20)->get('https://serpapi.com/search.json', ['engine' => 'google', 'q' => $query, 'num' => 10, 'api_key' => (string) config('marketscan.search.serpapi_key')])->throw();

        return collect($res->json('organic_results', []))->map(fn ($r) => [
            'title' => $r['title'] ?? '', 'url' => $r['link'] ?? '', 'snippet' => $r['snippet'] ?? '', 'source' => $r['source'] ?? parse_url($r['link'] ?? '', PHP_URL_HOST),
        ])->filter(fn ($r) => $r['url'])->values()->all();
    }
}
