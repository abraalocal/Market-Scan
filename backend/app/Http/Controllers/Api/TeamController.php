<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenters\VisitView;
use App\Models\NewProduct;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\SupplierTag;
use App\Models\User;
use App\Models\Visit;
use App\Remote\GhlClient;
use App\Support\JoinCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/** FR-10 targets and admin summary, FR-11 owner shop, FR-12 welcome. */
class TeamController extends Controller
{
    public function targets(Request $request): JsonResponse
    {
        return response()->json($this->counts($request->user()));
    }

    public function summary(): JsonResponse
    {
        $members = User::whereIn('role', ['field_sales'])->orderBy('name')->get()
            ->map(fn (User $u) => ['user_id' => $u->id, 'name' => $u->name, 'area' => (string) $u->area] + $this->counts($u));

        return response()->json([
            'waiting' => [
                'supplier_changes' => SupplierChange::where('status', 'pending')->count(),
                'products_ready' => NewProduct::where('status', 'ready')->count(),
                'products_need_person' => NewProduct::where('status', 'needs_person')->count(),
                'visits_need_review' => Visit::where('status', 'needs_review')->count(),
            ],
            'auto_publish' => (bool) Setting::get('auto_publish', false),
            'totals' => [
                'visits' => $members->sum('visits'), 'visit_target' => $members->sum('visit_target'),
                'suppliers' => $members->sum('suppliers'), 'supplier_target' => $members->sum('supplier_target'),
            ],
            'members' => $members->values(),
        ]);
    }

    public function ownerShop(Request $request): JsonResponse|Response
    {
        $u = $request->user();
        abort_unless($u->isOwner(), 403, 'Business owners only.');
        $s = $u->supplier_id ? Supplier::find($u->supplier_id) : null;
        if (! $s) {
            return response('null', 200, ['Content-Type' => 'application/json']);   // OwnerShop | null
        }
        $tags = SupplierTag::where('supplier_id', $s->id)->get(['self_reported']);

        return response()->json([
            'supplier_id' => $s->id,
            'company_name' => $s->company_name,
            'area' => (string) $s->area,
            'products' => $tags->count(),
            'self_reported' => $tags->isNotEmpty() && $tags->every(fn ($t) => $t->self_reported),
            'pending_approval' => SupplierChange::where('supplier_id', $s->id)->where('status', 'pending')->exists(),
            'on_abraa' => $s->abraa_supplier_id !== null,
        ]);
    }

    public function welcome(Request $request, string $uuid, GhlClient $ghl): JsonResponse
    {
        $data = $request->validate(['channel' => ['nullable', 'in:whatsapp,qr']]);
        $u = $request->user();
        $v = VisitController::visitFor($u, $uuid, own: true);
        abort_unless($v->supplier_id && in_array($v->status, ['submitted', 'pending_approval', 'live'], true), 409, 'Submit the visit first.');
        $s = $v->supplier;
        $url = rtrim(config('abraa.join_url'), '/').'/'.JoinCode::for($s->id);
        $f = collect($v->extraction['reviewed_fields'] ?? $v->extraction['fields'] ?? [])->pluck('value', 'key');
        $ar = $u->locale === 'ar';
        $contact = trim(explode(' ', (string) ($f['contact_name'] ?? $s->contact_name ?? ''))[0]) ?: ($ar ? 'بك' : 'there');
        $message = __(config('messages.welcome.'.($ar ? 'ar' : 'en')), [
            'contact' => $contact, 'user' => $u->name, 'shop' => $s->company_name, 'count' => count($v->extraction['submitted'] ?? []), 'url' => $url,
        ]);
        $sent = false;
        if (($data['channel'] ?? 'whatsapp') === 'whatsapp' && $s->ghl_contact_id) {
            try {
                $ghl->sendWhatsApp($s->ghl_contact_id, $message);
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['join_url' => $url, 'message' => $message, 'sent' => $sent]);
    }

    /** Today in the team's time zone: visits past draft and new suppliers created. */
    private function counts(User $u): array
    {
        $now = now(config('marketscan.timezone'));
        $range = [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()];
        $visits = Visit::where('user_id', $u->id)->where('status', '!=', 'draft')->whereBetween('created_at', $range)->get(['extraction']);

        return [
            'visits' => $visits->count(),
            'visit_target' => $u->daily_visit_target,
            'suppliers' => $visits->filter(fn ($v) => ($v->extraction['submit']['outcome'] ?? null) === 'supplier_created')->count(),
            'supplier_target' => $u->daily_supplier_target,
        ];
    }
}
