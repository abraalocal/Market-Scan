<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A supplier update waiting for admin approval (FR-32..FR-35). */
class SupplierChange extends Model
{
    protected $table = 'ms_supplier_changes';

    protected $fillable = ['visit_id', 'requested_by', 'supplier_id', 'abraa_supplier_id', 'ghl_contact_id', 'current_values', 'proposed_values', 'keywords_to_add', 'status', 'decided_by', 'decided_at', 'note'];

    protected function casts(): array
    {
        return [
            'current_values' => 'array', 'proposed_values' => 'array', 'keywords_to_add' => 'array',
            'abraa_supplier_id' => 'integer', 'supplier_id' => 'integer', 'decided_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
