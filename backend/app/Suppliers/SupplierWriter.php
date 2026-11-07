<?php

namespace App\Suppliers;

use App\Catalogue\ProductTree;
use App\Models\ProductRef;
use App\Models\Supplier;
use App\Models\SupplierTag;
use App\Models\Visit;
use App\Support\Phone;

/** Applies approved (or new-supplier) data to the local record: values, phones, tags (FR-34, FR-40..FR-43). */
class SupplierWriter
{
    public function __construct(private ProductTree $tree) {}

    /** Card values → supplier columns; phones normalised. */
    public static function valuesFromFields(array $fields): array
    {
        $f = collect($fields)->pluck('value', 'key')->map(fn ($v) => trim((string) $v));
        $out = [
            'company_name' => $f['company_name'] ?? '',
            'contact_name' => $f['contact_name'] ?? '',
            'whatsapp' => Phone::normalize($f['whatsapp'] ?? null) ?? '',
            'landline' => Phone::normalize($f['landline'] ?? null) ?? '',
            'email' => mb_strtolower($f['email'] ?? ''),
            'website' => $f['website'] ?? '',
            'area' => $f['area'] ?? '',
        ];

        return array_filter($out, fn ($v) => $v !== '');
    }

    public function applyValues(Supplier $s, array $values, Visit $visit): void
    {
        foreach (array_intersect_key($values, array_flip(Supplier::FIELDS)) as $k => $v) {
            if ($v !== null && $v !== '') {
                $s->{$k} = $v;
            }
        }
        $s->company_name = $s->company_name ?: $visit->shop_name;
        $s->gps_lat ??= $visit->gps_lat;
        $s->gps_lng ??= $visit->gps_lng;
        $s->last_visit_at = now();
        $s->save();
        foreach (['whatsapp', 'landline'] as $kind) {
            $p = $values[$kind] ?? null;
            if ($p && ! \App\Models\SupplierPhone::where('phone', $p)->exists()) {
                $s->phones()->create(['phone' => $p, 'kind' => $kind]);   // added, never replaced
            }
        }
    }

    /** Write product tags from the visit's reviewed products. @return string[] keywords */
    public function applyTags(Supplier $s, Visit $visit): array
    {
        $owner = $visit->source === 'owner';
        $refIds = [];
        $keywords = [];
        foreach ($visit->extraction['submitted'] ?? [] as $p) {
            $ref = ProductRef::find($p['ref_id']);
            if (! $ref) {
                continue;
            }
            $refIds[] = $ref->id;
            $keywords = array_merge($keywords, $this->tree->keywords($ref));
            $tag = SupplierTag::firstOrNew(['supplier_id' => $s->id, 'ref_id' => $ref->id]);
            $tag->fill([
                'seen_on_last_visit' => true, 'visit_id' => $visit->id, 'last_seen_at' => now(),
                'evidence_photo_id' => $p['photo_id'] ?? $tag->evidence_photo_id,
            ]);
            $tag->first_seen_at ??= now();
            // Owner tags stay self-reported until a field visit confirms them (FR-43).
            $tag->self_reported = $tag->exists ? ($tag->self_reported && $owner) : $owner;
            $tag->save();
        }
        if (! $owner) {
            // FR-42: tags not on this visit's shelves are kept but marked.
            SupplierTag::where('supplier_id', $s->id)->whereNotIn('ref_id', $refIds ?: [0])->update(['seen_on_last_visit' => false]);
        }

        return array_values(array_unique($keywords));
    }
}
