<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('name', 'Sara')->first(), 'sanctum');
});

it('finds suppliers on abraa.com and GHL by name and links them locally (FR-03)', function () {
    $res = $this->getJson('/api/shops/search?q=noor')->assertOk()->assertJsonCount(1);
    $res->assertJsonPath('0.company_name', 'Al Noor Cosmetics')->assertJsonPath('0.badge', 'on_abraa')->assertJsonPath('0.phones', ['+971502237781']);
    $s = Supplier::sole();
    expect($s->abraa_supplier_id)->not->toBeNull()->and($s->ghl_contact_id)->toStartWith('ghl_');

    $this->getJson('/api/shops/search?q=noor')->assertJsonCount(1);
    expect(Supplier::count())->toBe(1); // searched twice, one local copy
});

it('marks GHL-only contacts as in_ghl', function () {
    $this->getJson('/api/shops/search?q=star perfumes')->assertJsonPath('0.badge', 'in_ghl');
});

it('finds by full phone on either side', function () {
    $this->getJson('/api/shops/search?q=055 410 2290')->assertJsonPath('0.company_name', 'Gulf Beauty Trading');
    $this->getJson('/api/shops/search?q=0529081144')->assertJsonPath('0.company_name', 'Star Perfumes LLC');
});

it('lists local suppliers within 200 m, nearest first', function () {
    Supplier::factory()->create(['company_name' => 'Near', 'gps_lat' => 25.2712, 'gps_lng' => 55.3076]);
    Supplier::factory()->create(['company_name' => 'Mid', 'gps_lat' => 25.2722, 'gps_lng' => 55.3080]);
    Supplier::factory()->create(['company_name' => 'Far', 'gps_lat' => 25.2800, 'gps_lng' => 55.3075]);
    $res = $this->getJson('/api/shops/search?lat=25.2711&lng=55.3075')->assertOk();
    expect(collect($res->json())->pluck('company_name')->all())->toBe(['Near', 'Mid'])
        ->and($res->json('1.distance_m'))->toBeGreaterThan(100)->toBeLessThan(200);
});

it('warns when a phone already belongs to a supplier anywhere (FR-04)', function () {
    $this->getJson('/api/shops/check-phone?phone=050 223 7781')->assertOk()->assertJsonPath('match.company_name', 'Al Noor Cosmetics');
    $this->getJson('/api/shops/check-phone?phone=0529081144')->assertJsonPath('match.badge', 'in_ghl');
    $this->getJson('/api/shops/check-phone?phone=0501111111')->assertJson(['match' => null]);
});

it('keeps owners out of the directory (NFR-05)', function () {
    $this->actingAs(User::factory()->owner()->create(), 'sanctum');
    $this->getJson('/api/shops/search?q=a')->assertForbidden();
    $this->getJson('/api/shops/check-phone?phone=0554102290')->assertForbidden();
});
