<?php

use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Suppliers\Candidates;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake();
});

function card(array $x = []): array
{
    return array_merge(['company_name' => 'Bloom Hair Supply', 'whatsapp' => '050 777 1234', 'email' => 'sales@bloom.ae', 'area' => 'Deira, Dubai'], $x);
}

it('finds nothing for a brand-new shop', function () {
    expect(app(Candidates::class)->find(card()))->toBe([]);
});

it('finds a supplier on abraa.com and GHL by phone as one candidate (FR-30)', function () {
    $c = app(Candidates::class)->find(card(['whatsapp' => '+971 50 223 7781', 'company_name' => 'Another Name']));
    expect($c)->toHaveCount(1)
        ->and($c[0])->toMatchArray(['company_name' => 'Al Noor Cosmetics', 'on_abraa' => true, 'in_ghl' => true, 'match' => 'phone']);
});

it('finds by email, by name + area, and near names', function () {
    expect(app(Candidates::class)->find(card(['whatsapp' => '', 'email' => 'SALES@alnoorcosmetics.ae']))[0]['match'])->toBe('email');
    expect(app(Candidates::class)->find(card(['whatsapp' => '', 'email' => '', 'company_name' => 'GULF BEAUTY L.L.C.', 'area' => 'Naif']))[0])
        ->toMatchArray(['company_name' => 'Gulf Beauty Trading', 'match' => 'name_area', 'on_abraa' => true, 'in_ghl' => false]);
    $near = app(Candidates::class)->find(card(['whatsapp' => '', 'email' => '', 'company_name' => 'Star Perfume']));
    expect($near[0])->toMatchArray(['company_name' => 'Star Perfumes LLC', 'match' => 'near', 'in_ghl' => true]);
});

it('ignores the same name in another area', function () {
    expect(app(Candidates::class)->find(card(['whatsapp' => '', 'email' => '', 'company_name' => 'Gulf Beauty Trading', 'area' => 'Al Ain'])))->toBe([]);
});

it('includes the shop chosen at the start of the visit', function () {
    $s = Supplier::factory()->create(['company_name' => 'Picked Shop']);
    expect(app(Candidates::class)->find(card(), $s->id)[0])->toMatchArray(['key' => 's'.$s->id, 'match' => 'selected']);
});

it('is filled when a field visit reaches review, and empty for owners', function () {
    $sara = User::where('name', 'Sara')->first();
    $alNoor = app(\App\Suppliers\Directory::class)->search('Al Noor')->first();
    $v = Visit::factory()->create(['user_id' => $sara->id, 'status' => 'processing', 'supplier_id' => $alNoor->id]);
    \App\Jobs\ExtractVisitJob::dispatchSync($v);
    $this->actingAs($sara, 'sanctum')->getJson("/api/visits/{$v->uuid}")->assertJsonPath('status', 'review')
        ->assertJsonPath('candidates.0.company_name', 'Al Noor Cosmetics');

    $owner = User::factory()->owner()->create();
    $o = Visit::factory()->create(['user_id' => $owner->id, 'status' => 'processing', 'source' => 'owner']);
    \App\Jobs\ExtractVisitJob::dispatchSync($o);
    expect($o->fresh()->candidates)->toBe([]);
});
