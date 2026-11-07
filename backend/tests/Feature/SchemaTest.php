<?php

use App\Models\ProductRef;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;

it('creates every table from docs/schema.sql', function () {
    foreach (['ms_users', 'ms_otp_codes', 'ms_suppliers', 'ms_supplier_phones', 'ms_visits', 'ms_photos', 'ms_product_refs',
        'ms_supplier_tags', 'ms_supplier_changes', 'ms_new_products', 'ms_sync_log', 'ms_settings'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("missing $t");
    }
});

it('seeds an admin, three field sales and the category/brand tree, twice safely', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect(User::where('role', 'admin')->pluck('name')->all())->toBe(['Omar'])
        ->and(User::where('role', 'field_sales')->count())->toBe(3)
        ->and(ProductRef::where('level', 'category')->count())->toBe(6)
        ->and(ProductRef::where('name', 'Kérastase')->first()->ancestor('category')->name)->toBe('Hair Care');
});

it('casts JSON columns and stores settings', function () {
    $v = Visit::factory()->create(['candidates' => [['key' => 'abraa:1']]]);
    expect($v->fresh()->candidates[0]['key'])->toBe('abraa:1');
    Setting::put('auto_publish', true);
    expect(Setting::get('auto_publish'))->toBeTrue()->and(Setting::get('missing', 'x'))->toBe('x');
});
