<?php

namespace App\Visits;

use App\Catalogue\ProductTree;
use App\Extraction\ExtractionResult;
use App\Jobs\PushSupplier;
use App\Models\NewProduct;
use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\User;
use App\Models\Visit;
use App\Suppliers\Candidates;
use App\Suppliers\SupplierWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** POST /visits/{uuid}/submit (FR-30..FR-35, FR-40, FR-50). */
class SubmitVisit
{
    public function __construct(private ProductTree $tree, private ExtractionResult $extraction, private SupplierWriter $writer) {}

    public function __invoke(Visit $visit, User $user, array $fields, array $products, ?array $choice): array
    {
        $after = [];
        $summary = DB::transaction(function () use ($visit, $user, $fields, $products, $choice, &$after) {
            $visit = Visit::whereKey($visit->id)->lockForUpdate()->first();
            if (isset($visit->extraction['submit'])) {
                return $visit->extraction['submit']; // phone retry: same answer, nothing done twice
            }
            $owner = $visit->source === 'owner';
            $candidates = $visit->candidates ?? [];
            $existing = null;
            if (! $owner && $candidates) {
                if (! in_array($choice['choice'] ?? null, ['new', 'existing'], true)) {
                    throw ValidationException::withMessages(['supplier' => 'This shop may already be on abraa.com or GHL. Choose it, or say it is a new supplier.']);
                }
                if ($choice['choice'] === 'existing') {
                    $existing = Candidates::resolve($visit, (string) ($choice['key'] ?? ''))
                        ?? throw ValidationException::withMessages(['supplier.key' => 'Choose one of the suppliers shown.']);
                }
            }

            // Products: refs, new-product records (FR-40, FR-50).
            $closeups = $visit->photos()->where('type', 'closeup')->pluck('id')->all();
            $shelf = $visit->photos()->where('type', 'shelf')->orderBy('id')->value('id');
            $submitted = [];
            $reviewed = [];
            $keywords = [];
            $newProducts = 0;
            foreach (array_values($products) as $p) {
                $p['closeup_photo_id'] = in_array((int) ($p['closeup_photo_id'] ?? 0), $closeups, true) ? (int) $p['closeup_photo_id'] : null;
                $p['status'] = $this->extraction->status($p);
                $reviewed[] = $p;
                if ($p['status'] === 'check') {
                    continue; // still unclear after review
                }
                [$ref] = $this->tree->upsert($p);
                if (collect($submitted)->contains('ref_id', $ref->id)) {
                    continue;
                }
                $submitted[] = ['ref_id' => $ref->id, 'photo_id' => $p['closeup_photo_id'] ?? $shelf];
                $keywords = array_merge($keywords, $this->tree->keywords($ref));
                if ($p['status'] === 'new' && ! NewProduct::where('ref_id', $ref->id)->exists()) {
                    $np = NewProduct::create(['visit_id' => $visit->id, 'ref_id' => $ref->id, 'status' => 'searching', 'image_photo_id' => $p['closeup_photo_id'], 'moq' => self::moq($p['size'] ?? null), 'abraa_category_id' => $ref->ancestor('category')?->abraa_category_id]);
                    $after[] = fn () => \App\Jobs\FindProduct::dispatch($np->id);
                    $newProducts++;
                }
            }
            $keywords = array_values(array_unique($keywords));
            $values = SupplierWriter::valuesFromFields($fields);
            $visit->extraction = array_merge($visit->extraction ?? [], ['reviewed_fields' => array_values($fields), 'products' => $reviewed, 'submitted' => $submitted]);
            $visit->submitted_at = now();

            if ($owner || $existing) {
                // FR-32, FR-35: an update waiting for admin approval; nothing is pushed yet.
                $s = $existing ?? ($user->supplier_id ? Supplier::find($user->supplier_id) : null);
                if (! $s) {
                    $s = Supplier::create(['company_name' => $values['company_name'] ?? $visit->shop_name, 'source' => 'owner', 'gps_lat' => $visit->gps_lat, 'gps_lng' => $visit->gps_lng]);
                }
                if ($owner && ! $user->supplier_id) {
                    $user->update(['supplier_id' => $s->id]);
                }
                $change = SupplierChange::create([
                    'visit_id' => $visit->id, 'requested_by' => $user->id, 'supplier_id' => $s->id,
                    'abraa_supplier_id' => $s->abraa_supplier_id, 'ghl_contact_id' => $s->ghl_contact_id,
                    'current_values' => $s->values() + ['phones' => $s->phones()->pluck('phone')->implode(', ')],
                    'proposed_values' => $values, 'keywords_to_add' => $keywords,
                ]);
                $visit->supplier_id = $s->id;
                $visit->status = 'pending_approval';
                $summary = ['outcome' => 'update_pending', 'supplier_id' => $s->id, 'change_id' => $change->id];
            } else {
                // FR-31: new supplier, created on abraa.com and GHL now.
                $s = new Supplier(['source' => 'field_visit', 'company_name' => $values['company_name'] ?? $visit->shop_name]);
                $this->writer->applyValues($s, $values, $visit);
                $visit->supplier_id = $s->id;
                $this->writer->applyTags($s, $visit);
                $visit->status = 'submitted';
                $after[] = fn () => PushSupplier::dispatch($s->id, $keywords, $visit->id);
                $summary = ['outcome' => 'supplier_created', 'supplier_id' => $s->id, 'change_id' => null];
            }
            $summary += ['keywords' => count($keywords), 'new_products' => $newProducts];
            $visit->extraction = array_merge($visit->extraction, ['submit' => $summary]);
            $visit->save();

            return $summary;
        });
        foreach ($after as $f) {
            $f();
        }

        return $summary;
    }

    /** MOQ 24 for items ≤ 100 ml/g, else 12 (FR-53). */
    public static function moq(?string $size): int
    {
        if (! $size || ! preg_match('/([\d.,]+)\s*(ml|g|gm|gr|l|ltr|litre|liter|kg|oz)\b/i', $size, $m)) {
            return 12;
        }
        $n = (float) str_replace(',', '.', $m[1]);
        $amount = match (strtolower($m[2])) { 'l', 'ltr', 'litre', 'liter', 'kg' => $n * 1000, 'oz' => $n * 29.57, default => $n };

        return $amount <= 100 ? 24 : 12;
    }
}
