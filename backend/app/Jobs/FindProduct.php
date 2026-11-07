<?php

namespace App\Jobs;

use App\Models\NewProduct;
use App\Models\Photo;
use App\Models\Setting;
use App\Products\ProductMatcher;
use App\Products\ProductSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/** FR-51..FR-55: web lookup → exact match? → ready (or published when auto-publish is on) / needs a person. */
class FindProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MIN_CONFIDENCE = 0.85;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $newProductId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ProductSearch $search, ProductMatcher $matcher): void
    {
        $np = NewProduct::with('ref')->find($this->newProductId);
        if (! $np || $np->status !== 'searching') {
            return;
        }
        $ref = $np->ref;
        $seen = [
            'category' => $ref->ancestor('category')?->name, 'brand' => $ref->ancestor('brand')?->name, 'line' => $ref->ancestor('line')?->name,
            'name' => $ref->size ? trim(preg_replace('/\s*'.preg_quote($ref->size, '/').'$/i', '', $ref->name)) : $ref->name,
            'size' => $ref->size, 'barcode' => $ref->barcode,
        ];
        $query = trim(implode(' ', array_filter([$seen['brand'], $seen['line'], $seen['name'], $seen['size']])));
        $results = $search->search($query);
        if ($seen['barcode']) {
            $results = array_merge($search->search($seen['barcode']), $results);
        }
        $photo = $np->image_photo_id ? Photo::find($np->image_photo_id) : null;
        $m = $matcher->match($seen, array_slice($results, 0, 10), $photo && Storage::exists($photo->path) ? Storage::get($photo->path) : null);

        $exact = ($m['exact'] ?? false) && (float) ($m['confidence'] ?? 0) >= self::MIN_CONFIDENCE;
        $np->fill([
            'status' => $exact ? 'ready' : 'needs_person',
            'match_confidence' => (float) ($m['confidence'] ?? 0),
            'match_reasons' => $m['reasons'] ?? null,
            'source_name' => $m['source_name'] ?? null,
            'source_url' => $m['source_url'] ?? null,
            'title_en' => $m['title_en'] ?? null, 'title_ar' => $m['title_ar'] ?? null,
            'desc_en' => $m['desc_en'] ?? null, 'desc_ar' => $m['desc_ar'] ?? null,
            'specs' => self::specs($m['specs'] ?? []),
            'images' => $exact ? $this->storeImages($np, $m['images'] ?? []) : [],
        ])->save();

        if ($exact && Setting::get('auto_publish', false)) {
            PublishProduct::dispatch($np->id, null);
        }
    }

    /** [{name, value}] or {name: value} → {name: value} */
    public static function specs(array $s): array
    {
        return array_is_list($s) ? collect($s)->filter(fn ($x) => isset($x['name']))->mapWithKeys(fn ($x) => [$x['name'] => (string) ($x['value'] ?? '')])->all() : $s;
    }

    /** Download up to 4 pack shots and keep our own copies; the source URL stays with each (FR-53, FR-56). */
    private function storeImages(NewProduct $np, array $urls): array
    {
        $out = [];
        foreach (array_slice(array_filter($urls, fn ($u) => is_string($u) && str_starts_with($u, 'https://')), 0, 4) as $i => $url) {
            try {
                $res = Http::timeout(15)->withHeaders(['User-Agent' => 'MarketScan/1.0'])->get($url);
                if (! $res->successful() || ! str_starts_with((string) $res->header('Content-Type'), 'image/') || strlen($res->body()) > 8_000_000) {
                    continue;
                }
                $img = (new ImageManager(new Driver))->read($res->body())->scaleDown(width: 1600, height: 1600);
                $path = "products/{$np->id}/$i.jpg";
                Storage::put($path, (string) $img->toJpeg(85));
                $out[] = ['path' => $path, 'source_url' => $url];
            } catch (Throwable) {
                continue; // a broken image never blocks the listing
            }
        }

        return $out;
    }

    public function failed(Throwable $e): void
    {
        NewProduct::whereKey($this->newProductId)->where('status', 'searching')->update(['status' => 'needs_person', 'match_reasons' => 'Lookup failed: '.mb_substr($e->getMessage(), 0, 200)]);
    }
}
