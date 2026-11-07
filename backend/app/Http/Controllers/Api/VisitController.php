<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenters\VisitView;
use App\Jobs\ExtractVisitJob;
use App\Models\Photo;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/** FR-05..FR-09: visits, photos, start of AI reading. */
class VisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:'.implode(',', Visit::STATUSES)], 'user_id' => ['nullable', 'integer']]);
        $u = $request->user();
        $q = Visit::query()->where('user_id', $u->isAdmin() && isset($data['user_id']) ? $data['user_id'] : $u->id)
            ->orderByDesc('created_at')->orderByDesc('id');
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }

        return response()->json(VisitView::many($q->limit(200)->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid'],
            'supplier_id' => ['nullable', 'integer', 'exists:ms_suppliers,id'],
            'shop_name' => ['required', 'string', 'max:190'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'captured_at' => ['nullable', 'date'],
        ]);
        $u = $request->user();
        $uuid = strtolower($data['uuid']);
        if ($existing = Visit::where('uuid', $uuid)->first()) {
            abort_unless($existing->user_id === $u->id, 403, 'This visit belongs to someone else.');

            return response()->json(VisitView::one($existing), 200);
        }
        $owner = $u->isOwner();
        $v = Visit::create([
            'uuid' => $uuid, 'user_id' => $u->id, 'shop_name' => $data['shop_name'],
            'supplier_id' => $owner ? $u->supplier_id : ($data['supplier_id'] ?? null),
            'source' => $owner ? 'owner' : 'field_visit',
            'gps_lat' => $data['lat'] ?? null, 'gps_lng' => $data['lng'] ?? null,
            'captured_at' => isset($data['captured_at']) ? Carbon::parse($data['captured_at'])->utc() : now(),
        ]);

        return response()->json(VisitView::one($v->fresh()), 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json(VisitView::one(self::visitFor($request->user(), $uuid)));
    }

    public function uploadPhoto(Request $request, string $uuid): JsonResponse
    {
        $v = self::visitFor($request->user(), $uuid, own: true);
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', Photo::TYPES)],
            'file' => ['required', 'file', 'image', 'max:'.config('marketscan.photos.max_kb')],
            'client_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        abort_unless(in_array($v->status, ['draft', 'needs_review'], true), 409, 'Photos can only be added before the AI reading starts.');

        if ($p = Photo::where('visit_id', $v->id)->where('client_id', $data['client_id'])->first()) {
            return response()->json(['id' => $p->id, 'type' => $p->type, 'duplicate' => true], 200);
        }
        $path = "visits/{$v->uuid}/{$data['type']}/{$data['client_id']}.jpg";
        [$jpeg, $w, $h] = $this->toJpeg($data['file']);
        Storage::put($path, $jpeg);
        $p = Photo::create(['visit_id' => $v->id, 'client_id' => $data['client_id'], 'type' => $data['type'], 'path' => $path, 'width' => $w, 'height' => $h]);

        return response()->json(['id' => $p->id, 'type' => $p->type], 201);
    }

    public function extract(Request $request, string $uuid): JsonResponse
    {
        $v = self::visitFor($request->user(), $uuid, own: true);
        if (! in_array($v->status, ['draft', 'needs_review'], true)) {
            return response()->json(VisitView::one($v), 202); // retry after start: no-op
        }
        $have = $v->photos()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');
        $need = ['card' => 'business card', 'storefront' => 'shop front', 'shelf' => 'shelf'];
        if ($v->source === 'owner') {
            unset($need['storefront']);
            if ($v->supplier_id) {
                unset($need['card']); // owner adding photos to a shop already listed
            }
        }
        $missing = array_values(array_filter($need, fn ($label, $type) => (int) ($have[$type] ?? 0) < 1, ARRAY_FILTER_USE_BOTH));
        if ($missing) {
            throw ValidationException::withMessages(['photos' => 'Missing photos: '.implode(', ', $missing).'.']);
        }
        $v->update(['status' => 'processing']);
        ExtractVisitJob::dispatch($v);

        return response()->json(VisitView::one($v->fresh()), 202);
    }

    public function submit(Request $request, string $uuid, \App\Visits\SubmitVisit $submit): JsonResponse
    {
        $v = self::visitFor($request->user(), $uuid, own: true);
        $data = $request->validate([
            'fields' => ['array'],
            'fields.*.key' => ['required', 'in:'.implode(',', \App\Extraction\ExtractionResult::FIELD_KEYS)],
            'fields.*.value' => ['present', 'nullable', 'string', 'max:255'],
            'fields.*.confidence' => ['nullable', 'numeric'],
            'products' => ['array', 'max:500'],
            'products.*.id' => ['required', 'string', 'max:64'],
            'products.*.category' => ['nullable', 'string', 'max:190'],
            'products.*.brand' => ['required', 'string', 'max:190'],
            'products.*.line' => ['nullable', 'string', 'max:190'],
            'products.*.name' => ['required', 'string', 'max:190'],
            'products.*.size' => ['nullable', 'string', 'max:40'],
            'products.*.barcode' => ['nullable', 'string', 'max:32'],
            'products.*.confidence' => ['nullable', 'numeric', 'between:0,1'],
            'products.*.closeup_photo_id' => ['nullable', 'integer'],
            'supplier' => ['nullable', 'array'],
            'supplier.choice' => ['nullable', 'in:new,existing'],
            'supplier.key' => ['nullable', 'string', 'max:40'],
        ]);
        abort_unless(in_array($v->status, ['review', 'needs_review', 'submitted', 'pending_approval', 'live'], true), 409, 'The AI review is not ready yet.');
        $fields = $data['fields'] ?? ($v->extraction['fields'] ?? []);
        $products = array_map(fn ($p) => $p + ['category' => '', 'confidence' => 1], $data['products'] ?? ($v->extraction['products'] ?? []));
        $summary = $submit($v, $request->user(), $fields, $products, $data['supplier'] ?? null);

        return response()->json(['visit' => VisitView::one($v->fresh())] + $summary);
    }

    /** A visit the user may see: their own; admins may read any. */
    public static function visitFor(User $u, string $uuid, bool $own = false): Visit
    {
        $v = Visit::where('uuid', strtolower($uuid))->first();
        abort_unless($v && ($v->user_id === $u->id || (! $own && $u->isAdmin())), 404, 'Visit not found.');

        return $v;
    }

    /** Max 1600 px on the long side, JPEG (NFR-02, NFR-09). */
    private function toJpeg(UploadedFile $file): array
    {
        $max = (int) config('marketscan.photos.max_side');
        $img = (new ImageManager(new Driver))->read($file->getRealPath());
        if ($img->width() > $max || $img->height() > $max) {
            $img->scaleDown(width: $max, height: $max);
        }

        return [(string) $img->toJpeg(82), $img->width(), $img->height()];
    }
}
