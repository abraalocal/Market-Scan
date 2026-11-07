<?php

namespace App\Http\Presenters;

use App\Models\Supplier;
use Illuminate\Support\Collection;

/** ShopHit schema (docs/openapi.yaml). */
final class ShopHit
{
    public static function many(Collection $suppliers): array
    {
        return $suppliers->map(fn (Supplier $s) => self::one($s))->values()->all();
    }

    public static function one(Supplier $s): array
    {
        $badge = match (true) {
            $s->abraa_supplier_id !== null => 'on_abraa',
            $s->ghl_contact_id !== null => 'in_ghl',
            $s->source === 'owner' => 'self_reported',
            default => 'new',
        };

        return [
            'id' => $s->id,
            'company_name' => $s->company_name,
            'area' => $s->area,
            'distance_m' => isset($s->distance_m) ? (int) round((float) $s->distance_m) : null,
            'last_visit_at' => $s->last_visit_at?->toIso8601ZuluString(),
            'badge' => $badge,
            'phones' => $s->phones()->orderBy('id')->pluck('phone')->all(),
        ];
    }
}
