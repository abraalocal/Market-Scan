<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Local copy of a supplier, linked to abraa.com (abraa_supplier_id) and GHL (ghl_contact_id). */
class Supplier extends Model
{
    use HasFactory;

    public const FIELDS = ['company_name', 'contact_name', 'whatsapp', 'landline', 'email', 'website', 'area'];

    protected $table = 'ms_suppliers';

    protected $fillable = ['abraa_supplier_id', 'ghl_contact_id', 'company_name', 'contact_name', 'whatsapp', 'landline', 'email', 'website', 'area', 'gps_lat', 'gps_lng', 'source', 'last_visit_at'];

    protected function casts(): array
    {
        return ['abraa_supplier_id' => 'integer', 'gps_lat' => 'float', 'gps_lng' => 'float', 'last_visit_at' => 'datetime'];
    }

    public function phones(): HasMany
    {
        return $this->hasMany(SupplierPhone::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(SupplierTag::class);
    }

    public function values(): array
    {
        return $this->only(self::FIELDS);
    }
}
