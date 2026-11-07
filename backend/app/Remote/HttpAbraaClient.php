<?php

namespace App\Remote;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** ABRAA_DRIVER=http: the abraa.com API described in docs/abraa-api.md. Never the database. */
class HttpAbraaClient implements AbraaClient
{
    public function searchSuppliers(array $phones = [], ?string $email = null, ?string $name = null): array
    {
        $q = array_filter(['phone' => $phones ?: null, 'email' => $email, 'name' => $name]);
        if (! $q) {
            return [];
        }

        return array_map([$this, 'supplier'], $this->http()->get('/suppliers/search', $q)->throw()->json('data', []));
    }

    public function getSupplier(int $id): ?array
    {
        $res = $this->http()->get("/suppliers/$id");
        if ($res->status() === 404) {
            return null;
        }

        return $this->supplier($res->throw()->json('data'));
    }

    public function createSupplier(array $data): int
    {
        return (int) $this->http()->post('/suppliers', $data)->throw()->json('data.id');
    }

    public function updateSupplier(int $id, array $data): void
    {
        $this->http()->patch("/suppliers/$id", $data)->throw();
    }

    public function addKeywords(int $id, array $keywords): void
    {
        $this->http()->post("/suppliers/$id/keywords", ['keywords' => array_values($keywords)])->throw();
    }

    public function findProduct(array $p): ?int
    {
        $q = ! empty($p['barcode']) ? ['barcode' => $p['barcode']] : array_filter(array_intersect_key($p, array_flip(['brand', 'line', 'name', 'size'])));
        $id = $this->http()->get('/products/search', $q)->throw()->json('data.0.id');

        return $id ? (int) $id : null;
    }

    public function createProduct(array $data): int
    {
        return (int) $this->http()->post('/products', $data)->throw()->json('data.id');
    }

    private function supplier(array $d): array
    {
        return [
            'id' => (int) $d['id'], 'company_name' => $d['company_name'] ?? null, 'contact_name' => $d['contact_name'] ?? null,
            'phones' => array_values($d['phones'] ?? []), 'email' => $d['email'] ?? null, 'website' => $d['website'] ?? null,
            'area' => $d['area'] ?? null, 'ghl_contact_id' => $d['ghl_contact_id'] ?? null, 'keywords' => $d['keywords'] ?? [],
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('marketscan.abraa.url'), '/'))
            ->withToken((string) config('marketscan.abraa.token'))->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }
}
