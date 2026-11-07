<?php

namespace App\Products;

use Illuminate\Support\Facades\Http;

/** PRODUCT_SEARCH_DRIVER=brave: Brave Search API (web results). */
class BraveSearch implements ProductSearch
{
    public function search(string $query): array
    {
        $res = Http::timeout(15)->withHeaders(['X-Subscription-Token' => (string) config('marketscan.search.brave_key'), 'Accept' => 'application/json'])
            ->get('https://api.search.brave.com/res/v1/web/search', ['q' => $query, 'count' => 10])->throw();

        return collect($res->json('web.results', []))->map(fn ($r) => [
            'title' => $r['title'] ?? '', 'url' => $r['url'] ?? '', 'snippet' => strip_tags($r['description'] ?? ''), 'source' => parse_url($r['url'] ?? '', PHP_URL_HOST),
        ])->filter(fn ($r) => $r['url'])->values()->all();
    }
}
