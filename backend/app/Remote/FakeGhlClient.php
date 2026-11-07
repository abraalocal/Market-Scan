<?php

namespace App\Remote;

use App\Support\Names;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** GHL_DRIVER=fake: behaves like GHL contacts, data in ms_fake_ghl_contacts (development and tests only). */
class FakeGhlClient implements GhlClient
{
    /** Messages "sent" by the fake, for tests. */
    public static array $sent = [];

    public function searchContacts(array $phones = [], ?string $email = null, ?string $name = null): array
    {
        $out = [];
        foreach (DB::table('ms_fake_ghl_contacts')->orderBy('created_at')->get() as $row) {
            $c = $this->row($row);
            $hit = array_intersect($phones, $c['phones'])
                || ($email && $c['email'] && strcasecmp($email, $c['email']) === 0)
                || ($name && Names::key($name) !== '' && str_contains(Names::key($c['company_name']), Names::key($name)))
                || ($name && Names::similarity($name, $c['company_name']) >= 0.8);
            if ($hit) {
                $out[] = $c;
            }
        }

        return $out;
    }

    public function getContact(string $id): ?array
    {
        $row = DB::table('ms_fake_ghl_contacts')->where('id', $id)->first();

        return $row ? $this->row($row) : null;
    }

    public function createContact(array $data, array $tags): string
    {
        $id = 'ghl_'.Str::lower(Str::random(12));
        DB::table('ms_fake_ghl_contacts')->insert([
            'id' => $id, 'data' => json_encode($this->clean($data)), 'tags' => json_encode(array_values(array_unique($tags))), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function updateContact(string $id, array $data): void
    {
        $c = $this->getContact($id) ?? throw new \RuntimeException("GHL contact $id not found");
        DB::table('ms_fake_ghl_contacts')->where('id', $id)->update(['data' => json_encode(array_merge($this->clean($c), $this->partial($data))), 'updated_at' => now()]);
    }

    public function addTags(string $id, array $tags): void
    {
        $c = $this->getContact($id) ?? throw new \RuntimeException("GHL contact $id not found");
        DB::table('ms_fake_ghl_contacts')->where('id', $id)->update(['tags' => json_encode(array_values(array_unique(array_merge($c['tags'], $tags)))), 'updated_at' => now()]);
    }

    public function sendWhatsApp(string $contactId, string $text): void
    {
        self::$sent[] = [$contactId, $text];
    }

    private function row(object $row): array
    {
        return ['id' => $row->id] + $this->clean(json_decode($row->data, true)) + ['tags' => json_decode($row->tags ?? '[]', true) ?: []];
    }

    private function clean(array $d): array
    {
        $keys = ['company_name', 'contact_name', 'phones', 'email', 'website', 'area', 'abraa_supplier_id'];
        $out = array_intersect_key($d, array_flip($keys)) + array_fill_keys($keys, null);
        $out['phones'] = array_values($out['phones'] ?? []);

        return $out;
    }

    /** Only the keys given: an update never blanks other fields. */
    private function partial(array $d): array
    {
        return array_intersect_key($d, array_flip(['company_name', 'contact_name', 'phones', 'email', 'website', 'area', 'abraa_supplier_id']));
    }
}
