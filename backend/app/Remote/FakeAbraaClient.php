<?php

namespace App\Remote;

use App\Support\Names;
use Illuminate\Support\Facades\DB;

/** ABRAA_DRIVER=fake: behaves like the abraa.com API, data in ms_fake_abraa_* (development and tests only). */
class FakeAbraaClient implements AbraaClient
{
    public function searchSuppliers(array $phones = [], ?string $email = null, ?string $name = null): array
    {
        $out = [];
        foreach (DB::table('ms_fake_abraa_suppliers')->orderBy('id')->get() as $row) {
            $s = $this->row($row);
            $hit = array_intersect($phones, $s['phones'])
                || ($email && $s['email'] && strcasecmp($email, $s['email']) === 0)
                || ($name && Names::key($name) !== '' && str_contains(Names::key($s['company_name']), Names::key($name)))
                || ($name && Names::similarity($name, $s['company_name']) >= 0.8);
            if ($hit) {
                $out[] = $s;
            }
        }

        return $out;
    }

    public function getSupplier(int $id): ?array
    {
        $row = DB::table('ms_fake_abraa_suppliers')->find($id);

        return $row ? $this->row($row) : null;
    }

    public function createSupplier(array $data): int
    {
        return DB::table('ms_fake_abraa_suppliers')->insertGetId([
            'data' => json_encode($this->clean($data)), 'keywords' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function updateSupplier(int $id, array $data): void
    {
        $s = $this->getSupplier($id) ?? throw new \RuntimeException("abraa.com supplier $id not found");
        DB::table('ms_fake_abraa_suppliers')->where('id', $id)->update([
            'data' => json_encode(array_merge($this->clean($s), $this->clean($data))), 'updated_at' => now(),
        ]);
    }

    public function addKeywords(int $id, array $keywords): void
    {
        $s = $this->getSupplier($id) ?? throw new \RuntimeException("abraa.com supplier $id not found");
        DB::table('ms_fake_abraa_suppliers')->where('id', $id)->update([
            'keywords' => json_encode(array_values(array_unique(array_merge($s['keywords'], $keywords)))), 'updated_at' => now(),
        ]);
    }

    public function findProduct(array $p): ?int
    {
        foreach (DB::table('ms_fake_abraa_products')->orderBy('id')->get() as $row) {
            $d = json_decode($row->data, true);
            if (! empty($p['barcode']) && ($d['barcode'] ?? null) === $p['barcode']) {
                return $row->id;
            }
            $same = fn ($k) => Names::squash($d[$k] ?? '') === Names::squash($p[$k] ?? '');
            if ($same('brand') && $same('line') && $same('name') && $same('size')) {
                return $row->id;
            }
        }

        return null;
    }

    public function createProduct(array $data): int
    {
        return DB::table('ms_fake_abraa_products')->insertGetId(['data' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function row(object $row): array
    {
        return ['id' => (int) $row->id] + $this->clean(json_decode($row->data, true)) + ['keywords' => json_decode($row->keywords ?? '[]', true) ?: []];
    }

    private function clean(array $d): array
    {
        $keys = ['company_name', 'contact_name', 'phones', 'email', 'website', 'area', 'ghl_contact_id'];
        $out = array_intersect_key($d, array_flip($keys)) + array_fill_keys($keys, null);
        $out['phones'] = array_values($out['phones'] ?? []);

        return $out;
    }
}
