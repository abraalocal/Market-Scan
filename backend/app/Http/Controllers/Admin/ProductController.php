<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PublishProduct;
use App\Models\NewProduct;
use App\Models\Photo;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** FR-54, FR-55, FR-60: products waiting for approval or for a person; auto-publish switch. */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:searching,ready,needs_person,published,rejected']]);
        $q = NewProduct::with('ref')->latest('id')->limit(200);
        $data['status'] ?? null ? $q->where('status', $data['status']) : $q->whereIn('status', ['ready', 'needs_person']);

        return response()->json($q->get()->map(fn ($p) => self::present($p))->values());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title_en' => ['nullable', 'string', 'max:255'], 'title_ar' => ['nullable', 'string', 'max:255'],
            'desc_en' => ['nullable', 'string', 'max:5000'], 'desc_ar' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'url', 'max:500'], 'source_name' => ['nullable', 'string', 'max:120'],
            'specs' => ['nullable', 'array'], 'moq' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'abraa_category_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $p = NewProduct::findOrFail($id);
        abort_if(in_array($p->status, ['published', 'rejected'], true), 409, 'This product is already '.$p->status.'.');
        $p->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(self::present($p->fresh('ref')));
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $p = NewProduct::findOrFail($id);
        abort_unless(in_array($p->status, ['ready', 'needs_person'], true), 409, 'This product is '.$p->status.'.');
        abort_unless($p->title_en && $p->title_ar, 422, 'Add the English and Arabic titles first.');
        $p->update(['decided_by' => $request->user()->id]);
        PublishProduct::dispatch($p->id, $request->user()->id);

        return response()->json(self::present($p->fresh('ref')));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $p = NewProduct::findOrFail($id);
        abort_if($p->status === 'published', 409, 'This product is already on abraa.com.');
        $p->update(['status' => 'rejected', 'note' => $data['note'] ?? null, 'decided_by' => $request->user()->id, 'decided_at' => now()]);

        return response()->json(self::present($p->fresh('ref')));
    }

    public function settings(): JsonResponse
    {
        return response()->json(['auto_publish' => (bool) Setting::get('auto_publish', false)]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['auto_publish' => ['required', 'boolean']]);
        Setting::put('auto_publish', (bool) $data['auto_publish'], $request->user()->id);

        return $this->settings();
    }

    public static function present(NewProduct $p): array
    {
        $ref = $p->ref;
        $photo = $p->image_photo_id ? Photo::find($p->image_photo_id) : null;

        return [
            'id' => $p->id,
            'status' => $p->status,
            'seen' => ['brand' => $ref->ancestor('brand')?->name, 'line' => $ref->ancestor('line')?->name, 'name' => $ref->name, 'size' => $ref->size, 'barcode' => $ref->barcode],
            'match_confidence' => $p->match_confidence,
            'match_reasons' => $p->match_reasons,
            'source_name' => $p->source_name,
            'source_url' => $p->source_url,
            'title_en' => $p->title_en, 'title_ar' => $p->title_ar, 'desc_en' => $p->desc_en, 'desc_ar' => $p->desc_ar,
            'specs' => (object) ($p->specs ?? []),
            'images' => collect($p->images ?? [])->map(fn ($i) => Storage::temporaryUrl($i['path'], now()->addHour()))->values()->all(),
            'shelf_photo_url' => $photo ? Storage::temporaryUrl($photo->path, now()->addHour()) : null,
            'moq' => $p->moq,
            'abraa_product_id' => $p->abraa_product_id,
        ];
    }
}
