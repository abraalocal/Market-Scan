<?php

namespace App\Remote;

use App\Support\Phone;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * GHL_DRIVER=http: GoHighLevel API v2 (services.leadconnectorhq.com, Version 2021-07-28).
 * The abraa.com supplier id goes in the custom field GHL_FIELD_ABRAA_ID; area in address1.
 * To verify against a real location before go-live (Mostafa).
 */
class HttpGhlClient implements GhlClient
{
    public function searchContacts(array $phones = [], ?string $email = null, ?string $name = null): array
    {
        $found = [];
        foreach ($phones as $p) {
            if ($c = $this->http()->get('/contacts/search/duplicate', ['locationId' => $this->location(), 'number' => $p])->throw()->json('contact')) {
                $found[$c['id']] = $c;
            }
        }
        if ($email && ($c = $this->http()->get('/contacts/search/duplicate', ['locationId' => $this->location(), 'email' => $email])->throw()->json('contact'))) {
            $found[$c['id']] = $c;
        }
        if ($name) {
            foreach ($this->http()->post('/contacts/search', ['locationId' => $this->location(), 'pageLimit' => 10, 'query' => $name])->throw()->json('contacts', []) as $c) {
                $found[$c['id']] = $c;
            }
        }

        return array_values(array_map([$this, 'contact'], $found));
    }

    public function getContact(string $id): ?array
    {
        $res = $this->http()->get("/contacts/$id");

        return $res->status() === 404 ? null : $this->contact($res->throw()->json('contact'));
    }

    public function createContact(array $data, array $tags): string
    {
        return (string) $this->http()->post('/contacts/', $this->body($data) + ['locationId' => $this->location(), 'tags' => array_values($tags), 'source' => 'Market Scan'])
            ->throw()->json('contact.id');
    }

    public function updateContact(string $id, array $data): void
    {
        $this->http()->put("/contacts/$id", $this->body($data))->throw();
    }

    public function addTags(string $id, array $tags): void
    {
        $this->http()->post("/contacts/$id/tags", ['tags' => array_values($tags)])->throw();
    }

    public function sendWhatsApp(string $contactId, string $text): void
    {
        $this->http()->post('/conversations/messages', ['type' => 'WhatsApp', 'contactId' => $contactId, 'message' => $text])->throw();
    }

    private function body(array $d): array
    {
        $phones = array_values($d['phones'] ?? []);
        $out = array_filter([
            'companyName' => $d['company_name'] ?? null,
            'name' => $d['contact_name'] ?? null,
            'phone' => $phones[0] ?? null,
            'email' => $d['email'] ?? null,
            'website' => $d['website'] ?? null,
            'address1' => $d['area'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        if (! empty($d['abraa_supplier_id']) && ($field = config('marketscan.ghl.abraa_field_id'))) {
            $out['customFields'] = [['id' => $field, 'field_value' => (string) $d['abraa_supplier_id']]];
        }

        return $out;
    }

    private function contact(array $c): array
    {
        $field = config('marketscan.ghl.abraa_field_id');
        $abraa = collect($c['customFields'] ?? [])->firstWhere('id', $field)['value'] ?? null;
        $phones = array_values(array_filter(array_merge([Phone::normalize($c['phone'] ?? null)], array_map(fn ($p) => Phone::normalize(is_array($p) ? ($p['phone'] ?? null) : $p), $c['additionalPhones'] ?? []))));

        return [
            'id' => (string) $c['id'], 'company_name' => $c['companyName'] ?? null,
            'contact_name' => $c['contactName'] ?? trim(($c['firstName'] ?? '').' '.($c['lastName'] ?? '')) ?: null,
            'phones' => $phones, 'email' => $c['email'] ?? null, 'website' => $c['website'] ?? null, 'area' => $c['address1'] ?? $c['city'] ?? null,
            'abraa_supplier_id' => $abraa ? (int) $abraa : null, 'tags' => $c['tags'] ?? [],
        ];
    }

    private function location(): string
    {
        return (string) config('marketscan.ghl.location_id');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('marketscan.ghl.base_url'), '/'))
            ->withToken((string) config('marketscan.ghl.key'))->withHeaders(['Version' => config('marketscan.ghl.version')])
            ->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }
}
