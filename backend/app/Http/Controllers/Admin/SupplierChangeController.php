<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PushSupplier;
use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\Visit;
use App\Suppliers\SupplierWriter;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** FR-33, FR-60, FR-61: admin approves or rejects supplier updates. */
class SupplierChangeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected']]);
        $rows = SupplierChange::with(['visit', 'requester'])->where('status', $data['status'] ?? 'pending')->latest('id')->limit(200)->get();

        return response()->json($rows->map(fn ($c) => self::present($c))->values());
    }

    public function approve(Request $request, int $id, SupplierWriter $writer): JsonResponse
    {
        $data = $request->validate([
            'proposed' => ['nullable', 'array'],
            'proposed.*' => ['nullable', 'string', 'max:190'],
        ]);
        $push = null;
        $change = DB::transaction(function () use ($request, $id, $data, $writer, &$push) {
            $c = SupplierChange::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($c->status === 'pending', 409, 'This update was already '.$c->status.'.');
            $values = $c->proposed_values;
            foreach (array_intersect_key($data['proposed'] ?? [], array_flip(Supplier::FIELDS)) as $k => $v) {
                $values[$k] = in_array($k, ['whatsapp', 'landline'], true) ? (Phone::normalize($v) ?? '') : trim((string) $v);  // admin edits
            }
            $values = array_filter($values, fn ($v) => $v !== '' && $v !== null);
            $s = Supplier::findOrFail($c->supplier_id);
            $visit = Visit::findOrFail($c->visit_id);
            $writer->applyValues($s, $values, $visit);
            $writer->applyTags($s, $visit);
            $c->update(['proposed_values' => $values, 'status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            $push = [$s->id, $c->keywords_to_add, $visit->id];

            return $c;
        });
        PushSupplier::dispatch($push[0], $push[1], $push[2], update: true);

        return response()->json(self::present($change->fresh(['visit', 'requester'])));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $c = SupplierChange::findOrFail($id);
        abort_unless($c->status === 'pending', 409, 'This update was already '.$c->status.'.');
        $c->update(['status' => 'rejected', 'note' => $data['note'] ?? null, 'decided_by' => $request->user()->id, 'decided_at' => now()]);
        Visit::whereKey($c->visit_id)->where('status', 'pending_approval')->update(['status' => 'submitted']);

        return response()->json(self::present($c->fresh(['visit', 'requester'])));
    }

    public static function present(SupplierChange $c): array
    {
        $s = Supplier::find($c->supplier_id);

        return [
            'id' => $c->id,
            'visit_uuid' => $c->visit?->uuid,
            'requested_by' => $c->requester?->name,
            'source' => $c->visit?->source,
            'company_name' => $c->current_values['company_name'] ?? $c->proposed_values['company_name'] ?? $s?->company_name,
            'on_abraa' => (bool) $c->abraa_supplier_id,
            'in_ghl' => (bool) $c->ghl_contact_id,
            'current' => $c->current_values,
            'proposed' => $c->proposed_values,
            'keywords_to_add' => $c->keywords_to_add,
            'status' => $c->status,
            'note' => $c->note,
            'created_at' => $c->created_at?->toIso8601ZuluString(),
        ];
    }
}
