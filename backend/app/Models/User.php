<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/** Field sales, admin or business owner. OTP login only (NFR-05). */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'ms_users';

    protected $fillable = ['name', 'phone', 'role', 'area', 'daily_visit_target', 'daily_supplier_target', 'supplier_id', 'locale'];

    protected function casts(): array
    {
        return ['daily_visit_target' => 'integer', 'daily_supplier_target' => 'integer', 'supplier_id' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Sales staff and admins do visits for any shop. */
    public function isStaff(): bool
    {
        return in_array($this->role, ['field_sales', 'admin'], true);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'phone' => $this->phone, 'role' => $this->role,
            'area' => $this->area, 'locale' => $this->locale ?? 'en', 'supplier_id' => $this->supplier_id,
        ];
    }
}
