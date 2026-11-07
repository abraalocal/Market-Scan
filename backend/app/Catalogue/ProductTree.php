<?php

namespace App\Catalogue;

use App\Models\ProductRef;
use App\Support\Names;

/** Lookups and upserts on ms_product_refs (FR-23). Names compare normalised and against aliases and Arabic names. */
class ProductTree
{
    public static function productName(string $name, ?string $size): string
    {
        $name = trim($name);
        $size = trim((string) $size);

        return $size !== '' && ! str_ends_with(Names::squash($name), Names::squash($size)) ? "$name $size" : $name;
    }

    public function category(?string $name): ?ProductRef
    {
        return $this->child(null, 'category', $name);
    }

    /** Brand by name or alias: under the category first, then anywhere. */
    public function brand(?string $name, ?ProductRef $category = null): ?ProductRef
    {
        if ($category && ($b = $this->child($category, 'brand', $name))) {
            return $b;
        }
        $key = Names::key($name);

        return $key === '' ? null : ProductRef::where('level', 'brand')->orderBy('id')->get()->first(fn ($r) => $this->matches($r, $key));
    }

    public function child(?ProductRef $parent, string $level, ?string $name): ?ProductRef
    {
        $key = Names::key($name);
        if ($key === '') {
            return null;
        }
        $q = ProductRef::where('level', $level);
        $parent ? $q->where('parent_id', $parent->id) : $q->whereNull('parent_id');

        return $q->orderBy('id')->get()->first(fn ($r) => $this->matches($r, $key));
    }

    /** Existing product ref: barcode, then brand + line + name + size. */
    public function findProduct(array $p): ?ProductRef
    {
        if (! empty($p['barcode']) && ($r = ProductRef::where('level', 'product')->where('barcode', $p['barcode'])->first())) {
            return $r;
        }
        $brand = $this->brand($p['brand'] ?? null, $this->category($p['category'] ?? null));
        $parent = $brand && ! empty($p['line']) ? $this->child($brand, 'line', $p['line']) : $brand;

        return $parent ? $this->child($parent, 'product', self::productName((string) ($p['name'] ?? ''), $p['size'] ?? null)) : null;
    }

    /** Canonical brand spelling ("kerastase" → "Kérastase") when the brand is known (FR-23). */
    public function canonicalBrand(?string $name): ?string
    {
        return $this->brand($name)?->name ?? $name;
    }

    /**
     * Create missing levels category → brand → line → product. Unknown categories become General.
     *
     * @return array{0: ProductRef, 1: bool} [ref, category was unknown]
     */
    public function upsert(array $p): array
    {
        if ($r = $this->findProduct($p)) {
            return [$r, false];
        }
        $known = config('abraa.categories');
        $catName = collect(array_keys($known))->first(fn ($c) => Names::key($c) === Names::key($p['category'] ?? ''));
        $flag = $catName === null;
        $catName ??= config('abraa.fallback_category');
        $cat = $this->category($catName) ?? ProductRef::create(['level' => 'category', 'name' => $catName, 'abraa_category_id' => $known[$catName] ?? null]);
        $brand = $this->brand($p['brand'], $cat) ?? ProductRef::create(['level' => 'brand', 'name' => trim($p['brand']), 'parent_id' => $cat->id]);
        $parent = $brand;
        if (! empty($p['line'])) {
            $parent = $this->child($brand, 'line', $p['line']) ?? ProductRef::create(['level' => 'line', 'name' => trim($p['line']), 'parent_id' => $brand->id]);
        }
        $name = self::productName($p['name'], $p['size'] ?? null);
        $ref = $this->child($parent, 'product', $name) ?? ProductRef::create([
            'level' => 'product', 'name' => $name, 'parent_id' => $parent->id, 'size' => $p['size'] ?? null, 'barcode' => $p['barcode'] ?? null,
        ]);

        return [$ref, $flag];
    }

    /** Keywords for a product ref: category, brand, line, product names (+ Arabic where known), FR-41. */
    public function keywords(ProductRef $ref): array
    {
        $out = [];
        foreach (array_reverse($ref->lineage()) as $node) {
            $out[] = $node->name;
            if ($node->name_ar) {
                $out[] = $node->name_ar;
            }
        }

        return $out;
    }

    private function matches(ProductRef $r, string $key): bool
    {
        if (Names::key($r->name) === $key || ($r->name_ar && Names::key($r->name_ar) === $key)) {
            return true;
        }
        foreach ($r->aliases ?? [] as $a) {
            if (Names::key($a) === $key) {
                return true;
            }
        }

        return $r->level === 'product' && Names::squash($r->name) === str_replace(' ', '', $key);
    }
}
