<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenters\ShopHit;
use App\Models\Supplier;
use App\Suppliers\Directory;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-03 find shop (local + abraa.com + GHL, or nearby by GPS), FR-04 duplicate phone check. Staff only. */
class ShopController extends Controller
{
    /** Haversine distance in metres; bindings (lat, lat, lng). Works on MySQL 8 and SQLite 3.35+. */
    private const DISTANCE = '12742000 * asin(sqrt(power(sin(radians(gps_lat - ?) / 2), 2) + cos(radians(?)) * cos(radians(gps_lat)) * power(sin(radians(gps_lng - ?) / 2), 2)))';

    public function search(Request $request, Directory $directory): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Owners can only see their own shop.');
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_m' => ['nullable', 'integer', 'min:10', 'max:20000'],
        ]);
        $q = trim($data['q'] ?? '');
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        if ($q !== '') {
            $hits = $directory->search($q);
            if ($lat !== null && $lng !== null && $hits->isNotEmpty()) {
                $dist = Supplier::whereIn('id', $hits->pluck('id'))->whereNotNull('gps_lat')
                    ->selectRaw('id, '.self::DISTANCE.' as d', [(float) $lat, (float) $lat, (float) $lng])->pluck('d', 'id');
                $hits->each(fn ($s) => $s->distance_m = $dist[$s->id] ?? null);
            }

            return response()->json(ShopHit::many($hits));
        }
        if ($lat === null || $lng === null) {
            return response()->json([]);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $r = (int) ($data['radius_m'] ?? 200);
        $dLat = $r / 111_320;
        $dLng = $r / (111_320 * max(0.01, cos(deg2rad($lat))));
        $near = Supplier::query()->select('ms_suppliers.*')
            ->selectRaw(self::DISTANCE.' as distance_m', [$lat, $lat, $lng])
            ->whereBetween('gps_lat', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('gps_lng', [$lng - $dLng, $lng + $dLng])
            ->whereRaw(self::DISTANCE.' <= ?', [$lat, $lat, $lng, $r])
            ->orderBy('distance_m')->limit(20)->get();

        return response()->json(ShopHit::many($near));
    }

    public function checkPhone(Request $request, Directory $directory): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403, 'Owners can only see their own shop.');
        $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $phone = Phone::normalize($request->query('phone'));
        $s = $phone ? $directory->byPhone($phone) : null;

        return response()->json(['match' => $s ? ShopHit::one($s) : null]);
    }
}
