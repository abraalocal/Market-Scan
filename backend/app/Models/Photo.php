<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['card', 'storefront', 'shelf', 'closeup'];

    protected $table = 'ms_photos';

    protected $fillable = ['visit_id', 'client_id', 'type', 'path', 'width', 'height'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
