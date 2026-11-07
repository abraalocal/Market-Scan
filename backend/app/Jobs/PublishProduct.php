<?php

namespace App\Jobs;

use App\Models\NewProduct;
use App\Models\Photo;
use App\Remote\AbraaClient;
use App\Suppliers\SyncRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/** Lists a new product on abraa.com through its API (FR-53, FR-54, FR-56). */
class PublishProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public int $newProductId, public ?int $byUserId) {}

    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(AbraaClient $abraa, SyncRecorder $rec): void
    {
        $np = NewProduct::with(['ref', 'visit'])->find($this->newProductId);
        if (! $np || ! in_array($np->status, ['ready', 'needs_person'], true)) {
            return;
        }
        $ref = $np->ref;
        $images = collect($np->images ?? [])->map(fn ($i) => Storage::temporaryUrl($i['path'], now()->addDay()))->all();
        if (! $images && $np->image_photo_id && ($p = Photo::find($np->image_photo_id))) {
            $images = [Storage::temporaryUrl($p->path, now()->addDay())]; // close-up as the fallback picture (FR-22)
        }
        $payload = [
            'title_en' => $np->title_en, 'title_ar' => $np->title_ar, 'desc_en' => $np->desc_en, 'desc_ar' => $np->desc_ar,
            'specs' => $np->specs ?? [], 'category_id' => $np->abraa_category_id, 'category' => $ref->ancestor('category')?->name,
            'brand' => $ref->ancestor('brand')?->name, 'line' => $ref->ancestor('line')?->name, 'name' => $ref->name, 'size' => $ref->size, 'barcode' => $ref->barcode,
            'moq' => $np->moq, 'price' => null, 'images' => $images, 'source_url' => $np->source_url,
            'market_scan' => ['new_product_id' => $np->id, 'visit_uuid' => $np->visit?->uuid, 'supplier_id' => $np->visit?->supplier?->abraa_supplier_id],
        ];
        $id = $rec->run('abraa', 'create_product', 'new_product', $np->id, $payload, fn () => $abraa->createProduct($payload));
        $np->update(['status' => 'published', 'abraa_product_id' => $id, 'decided_by' => $this->byUserId ?? $np->decided_by, 'decided_at' => now()]);
        $ref->update(['abraa_product_id' => $id]); // next visits see it as on abraa.com
    }
}
