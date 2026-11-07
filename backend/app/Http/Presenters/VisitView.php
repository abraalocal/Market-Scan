<?php

namespace App\Http\Presenters;

use App\Models\Photo;
use App\Models\Visit;
use Illuminate\Support\Collection;

/** Visit schema (docs/openapi.yaml). */
final class VisitView
{
    private const WITH_RESULT = ['review', 'needs_review', 'submitted', 'pending_approval', 'live'];

    public static function one(Visit $v): array
    {
        return self::many(collect([$v]))[0];
    }

    public static function many(Collection $visits): array
    {
        $counts = Photo::whereIn('visit_id', $visits->pluck('id'))->selectRaw('visit_id, type, count(*) as n')->groupBy('visit_id', 'type')->get()->groupBy('visit_id');

        return $visits->map(function (Visit $v) use ($counts) {
            $x = in_array($v->status, self::WITH_RESULT, true) ? ($v->extraction ?? []) : [];
            $products = array_values($x['products'] ?? []);
            $by = collect($products)->countBy('status');

            return [
                'uuid' => $v->uuid,
                'shop_name' => $v->shop_name,
                'supplier_id' => $v->supplier_id,
                'source' => $v->source,
                'status' => $v->status,
                'photo_counts' => (object) ($counts->get($v->id)?->mapWithKeys(fn ($r) => [$r->type => (int) $r->n])->all() ?? []),
                'fields' => array_values($x['fields'] ?? []),
                'products' => $products,
                'totals' => ['found' => count($products), 'on_abraa' => (int) $by->get('on_abraa', 0), 'new' => (int) $by->get('new', 0), 'check' => (int) $by->get('check', 0)],
                'candidates' => $v->status === 'review' ? array_values($v->candidates ?? []) : [],
                'captured_at' => ($v->captured_at ?? $v->created_at)?->toIso8601ZuluString(),
                'error' => $v->status === 'needs_review' ? ($v->extraction['error'] ?? null) : null,
            ];
        })->values()->all();
    }
}
