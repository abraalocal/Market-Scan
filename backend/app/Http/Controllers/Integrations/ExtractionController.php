<?php

namespace App\Http\Controllers\Integrations;

use App\Extraction\ExtractionResult;
use App\Http\Controllers\Controller;
use App\Http\Presenters\VisitView;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtractionController extends Controller
{
    /** AI result posted by n8n when EXTRACTION_DRIVER=n8n. */
    public function store(Request $request, ExtractionResult $result): JsonResponse
    {
        $data = $request->validate([
            'visit_uuid' => ['required', 'string'],
            'error' => ['nullable', 'string', 'max:1000'],
            'fields' => ['array'],
            'fields.*.key' => ['required', 'in:'.implode(',', ExtractionResult::FIELD_KEYS)],
            'fields.*.value' => ['present', 'nullable', 'string', 'max:255'],
            'fields.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'products' => ['array', 'max:500'],
            'products.*.id' => ['required', 'string', 'max:64'],
            'products.*.category' => ['required', 'string', 'max:190'],
            'products.*.brand' => ['required', 'string', 'max:190'],
            'products.*.line' => ['nullable', 'string', 'max:190'],
            'products.*.name' => ['required', 'string', 'max:190'],
            'products.*.size' => ['nullable', 'string', 'max:40'],
            'products.*.barcode' => ['nullable', 'string', 'max:32'],
            'products.*.confidence' => ['required', 'numeric', 'between:0,1'],
            'products.*.closeup_photo_id' => ['nullable', 'integer'],
        ]);
        $v = Visit::where('uuid', strtolower($data['visit_uuid']))->first();
        abort_unless($v, 404, 'Visit not found.');
        abort_unless(in_array($v->status, ['processing', 'needs_review'], true), 409, "Visit is {$v->status}.");
        $result->store($v, $data['fields'] ?? [], $data['products'] ?? [], $data['error'] ?? null, 'n8n');

        return response()->json(VisitView::one($v->fresh()));
    }
}
