<?php

namespace App\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierPhone;
use App\Models\Visit;
use App\Remote\AbraaClient;
use App\Remote\GhlClient;
use App\Support\Names;
use App\Support\Phone;

/**
 * FR-30: existing suppliers a visit may be, searched on abraa.com, GHL and locally by phone, then email, then
 * company name + area, plus near matches. A supplier on both abraa.com and GHL is one candidate.
 */
class Candidates
{
    public const NEAR = 0.85;

    public const MAX = 5;

    public function __construct(private AbraaClient $abraa, private GhlClient $ghl, private Directory $directory) {}

    public function forVisit(Visit $visit): array
    {
        $f = collect($visit->extraction['fields'] ?? [])->pluck('value', 'key');

        return $this->find($f->all(), $visit->supplier_id);
    }

    /** @param array<string, string> $fields card values by key */
    public function find(array $fields, ?int $selectedId = null): array
    {
        $phones = array_values(array_unique(array_filter([Phone::normalize($fields['whatsapp'] ?? null), Phone::normalize($fields['landline'] ?? null)])));
        $email = trim((string) ($fields['email'] ?? '')) ?: null;
        $company = trim((string) ($fields['company_name'] ?? '')) ?: null;
        $area = $fields['area'] ?? null;

        $pool = collect();
        foreach ($this->abraa->searchSuppliers($phones, $email, $company) as $r) {
            $pool->push($this->directory->mirrorAbraa($r));
        }
        foreach ($this->ghl->searchContacts($phones, $email, $company) as $c) {
            $pool->push($this->directory->mirrorGhl($c));
        }
        $pool = $pool->merge(Supplier::whereIn('id', SupplierPhone::whereIn('phone', $phones ?: ['-'])->select('supplier_id'))->get());
        if ($email) {
            $pool = $pool->merge(Supplier::whereRaw('lower(email) = ?', [mb_strtolower($email)])->get());
        }
        if ($company) {
            $pool = $pool->merge(Supplier::whereNotNull('area')->get()->filter(fn ($s) => Names::similarity($s->company_name, $company) >= self::NEAR));
        }
        if ($selectedId && ($sel = Supplier::find($selectedId))) {
            $pool->push($sel);
        }

        $out = [];
        foreach ($pool->unique('id') as $s) {
            $sPhones = $s->phones()->pluck('phone')->all();
            $sim = Names::similarity($s->company_name, $company);
            [$match, $score] = match (true) {
                $s->id === $selectedId => ['selected', 1.0],
                (bool) array_intersect($phones, $sPhones) => ['phone', 1.0],
                $email && $s->email && strcasecmp($email, $s->email) === 0 => ['email', 0.95],
                $sim === 1.0 && Names::sameArea($s->area, $area) => ['name_area', 0.9],
                $sim >= self::NEAR && (Names::sameArea($s->area, $area) || ! $area || ! $s->area) => ['near', round($sim * 0.85, 3)],
                default => [null, 0],
            };
            if ($match) {
                $out[] = [
                    'key' => 's'.$s->id,
                    'company_name' => $s->company_name,
                    'contact_name' => $s->contact_name,
                    'phones' => $sPhones,
                    'email' => $s->email,
                    'area' => $s->area,
                    'on_abraa' => $s->abraa_supplier_id !== null,
                    'in_ghl' => $s->ghl_contact_id !== null,
                    'match' => $match,
                    'score' => $score,
                ];
            }
        }
        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($out, 0, self::MAX);
    }

    /** Local supplier for a candidate key from this visit, or null. */
    public static function resolve(Visit $visit, string $key): ?Supplier
    {
        $known = collect($visit->candidates ?? [])->pluck('key')->all();

        return in_array($key, $known, true) && preg_match('/^s(\d+)$/', $key, $m) ? Supplier::find((int) $m[1]) : null;
    }
}
