<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierTag extends Model
{
    protected $table = 'ms_supplier_tags';

    protected $fillable = ['supplier_id', 'ref_id', 'self_reported', 'seen_on_last_visit', 'visit_id', 'evidence_photo_id', 'first_seen_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['self_reported' => 'boolean', 'seen_on_last_visit' => 'boolean', 'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function ref(): BelongsTo
    {
        return $this->belongsTo(ProductRef::class, 'ref_id');
    }
}
