<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product seen on a shelf that is not on abraa.com yet (FR-50..FR-56). */
class NewProduct extends Model
{
    protected $table = 'ms_new_products';

    protected $fillable = ['visit_id', 'ref_id', 'status', 'match_confidence', 'match_reasons', 'source_name', 'source_url', 'title_en', 'title_ar', 'desc_en', 'desc_ar', 'specs', 'images', 'abraa_category_id', 'moq', 'image_photo_id', 'abraa_product_id', 'decided_by', 'decided_at', 'note'];

    protected function casts(): array
    {
        return [
            'specs' => 'array', 'images' => 'array', 'match_confidence' => 'float', 'moq' => 'integer',
            'abraa_category_id' => 'integer', 'abraa_product_id' => 'integer', 'image_photo_id' => 'integer', 'decided_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function ref(): BelongsTo
    {
        return $this->belongsTo(ProductRef::class, 'ref_id');
    }
}
