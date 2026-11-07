<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPhone extends Model
{
    public $timestamps = false;

    protected $table = 'ms_supplier_phones';

    protected $fillable = ['supplier_id', 'phone', 'kind'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
