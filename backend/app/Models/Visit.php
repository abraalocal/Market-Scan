<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'processing', 'review', 'needs_review', 'submitted', 'pending_approval', 'live'];

    protected $table = 'ms_visits';

    protected $fillable = ['uuid', 'user_id', 'supplier_id', 'shop_name', 'source', 'gps_lat', 'gps_lng', 'status', 'extraction', 'candidates', 'captured_at', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'extraction' => 'array', 'candidates' => 'array', 'gps_lat' => 'float', 'gps_lng' => 'float',
            'supplier_id' => 'integer', 'captured_at' => 'datetime', 'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }
}
