<?php

namespace App\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierPhone;
use App\Remote\AbraaClient;
use App\Remote\GhlClient;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * One view of suppliers across Market Scan, abraa.com and GHL. Anything found remotely gets a local,
 * linked copy (abraa_supplier_id / ghl_contact_id) so the app can refer to it by id.
 */
class Directory
{
    public function __construct(private AbraaClient $abraa, private GhlClient $ghl) {}

    /** @return Collection<int, Supplier> */
    public function search(string $q, int $limit = 20): Collection
    {
        $q = trim($q);
        $digits = Phone::digits($q);
        $phone = strlen($digits) >= 8 ? Phone::normalize($q) : null;
        $name = $phone ? null : $q;

        $found = collect();
        foreach ($this->abraa->searchSuppliers($phone ? [$phone] : [], null, $name) as $r) {
            $found->push($this->mirrorAbraa($r));
        }
        foreach ($this->ghl->searchContacts($phone ? [$phone] : [], null, $name) as $c) {
            $found->push($this->mirrorGhl($c));
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
        $local = Supplier::query()->where(function ($w) use ($like, $digits) {
            $w->whereRaw('lower(company_name) like ?', [$like]);
            if (strlen($digits) >= 3) {
                $w->orWhereIn('id', SupplierPhone::select('supplier_id')->where('phone', 'like', '%'.ltrim($digits, '0').'%'));
            }
        })->limit($limit)->get();

        return $found->merge($local)->unique('id')->values()->take($limit);
    }

    public function byPhone(string $e164): ?Supplier
    {
        if ($row = SupplierPhone::where('phone', $e164)->first()) {
            return $row->supplier;
        }
        if ($r = $this->abraa->searchSuppliers([$e164])[0] ?? null) {
            return $this->mirrorAbraa($r);
        }
        if ($c = $this->ghl->searchContacts([$e164])[0] ?? null) {
            return $this->mirrorGhl($c);
        }

        return null;
    }

    public function mirrorAbraa(array $r): Supplier
    {
        $s = Supplier::where('abraa_supplier_id', $r['id'])->first()
            ?? ($r['ghl_contact_id'] ? Supplier::where('ghl_contact_id', $r['ghl_contact_id'])->first() : null)
            ?? new Supplier(['source' => 'field_visit']);
        $s->abraa_supplier_id = $r['id'];
        if ($r['ghl_contact_id'] && ! $s->ghl_contact_id && ! Supplier::where('ghl_contact_id', $r['ghl_contact_id'])->whereKeyNot($s->id ?? 0)->exists()) {
            $s->ghl_contact_id = $r['ghl_contact_id'];
        }

        return $this->fill($s, $r);
    }

    public function mirrorGhl(array $c): Supplier
    {
        $s = Supplier::where('ghl_contact_id', $c['id'])->first()
            ?? ($c['abraa_supplier_id'] ? Supplier::where('abraa_supplier_id', $c['abraa_supplier_id'])->first() : null)
            ?? new Supplier(['source' => 'field_visit']);
        $s->ghl_contact_id = $c['id'];
        if ($c['abraa_supplier_id'] && ! $s->abraa_supplier_id && ! Supplier::where('abraa_supplier_id', $c['abraa_supplier_id'])->whereKeyNot($s->id ?? 0)->exists()) {
            $s->abraa_supplier_id = (int) $c['abraa_supplier_id'];
        }

        return $this->fill($s, $c);
    }

    /** Remote values fill the local copy; local values are kept where the remote has none. */
    private function fill(Supplier $s, array $r): Supplier
    {
        foreach (Supplier::FIELDS as $k) {
            if (! empty($r[$k]) && ! in_array($k, ['whatsapp', 'landline'], true)) {
                $s->{$k} = $r[$k];
            }
        }
        $s->company_name ??= 'Unknown shop';
        $phones = array_values(array_filter(array_map(fn ($p) => Phone::normalize($p), $r['phones'] ?? [])));
        $s->whatsapp ??= $phones[0] ?? null;
        $s->save();
        foreach ($phones as $p) {
            if (! SupplierPhone::where('phone', $p)->exists()) {
                $s->phones()->create(['phone' => $p, 'kind' => 'mobile']);
            }
        }

        return $s;
    }
}
