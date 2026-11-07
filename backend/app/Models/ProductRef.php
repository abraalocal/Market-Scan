<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One node of the category > brand > line > product tree (FR-23). */
class ProductRef extends Model
{
    protected $table = 'ms_product_refs';

    protected $fillable = ['level', 'name', 'name_ar', 'aliases', 'parent_id', 'abraa_category_id', 'abraa_product_id', 'size', 'barcode'];

    protected function casts(): array
    {
        return ['aliases' => 'array', 'parent_id' => 'integer', 'abraa_category_id' => 'integer', 'abraa_product_id' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** This ref and its ancestors, nearest first. */
    public function lineage(): array
    {
        $chain = [];
        for ($node = $this; $node; $node = $node->parent_id ? self::find($node->parent_id) : null) {
            $chain[] = $node;
        }

        return $chain;
    }

    public function ancestor(string $level): ?self
    {
        foreach ($this->lineage() as $node) {
            if ($node->level === $level) {
                return $node;
            }
        }

        return null;
    }
}
