<?php

use App\Models\Photo;
use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\SupplierTag;
use App\Models\User;
use App\Models\Visit;
use App\Remote\AbraaClient;
use App\Remote\GhlClient;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->sara = User::where('name', 'Sara')->first();
    $this->omar = User::where('name', 'Omar')->first();
});

function pendingChange(User $by, array $card, array $products = [], array $x = []): SupplierChange
{
    $v = Visit::factory()->create(array_merge(['user_id' => $by->id, 'status' => 'processing'], $x));
    Photo::create(['visit_id' => $v->id, 'client_id' => 's', 'type' => 'shelf', 'path' => 'x.jpg']);
    $f = collect($card)->map(fn ($val, $k) => ['key' => $k, 'value' => $val, 'confidence' => 1])->values()->all();
    app(\App\Extraction\ExtractionResult::class)->store($v, $f, [], null, 'test');
    $v->refresh();
    test()->actingAs($by, 'sanctum');
    $choice = $v->candidates ? ['choice' => 'existing', 'key' => $v->candidates[0]['key']] : null;
    $id = test()->postJson("/api/visits/{$v->uuid}/submit", array_filter(['fields' => $f, 'products' => $products, 'supplier' => $choice]))->assertOk()->json('change_id');

    return SupplierChange::findOrFail($id);
}

$olaplex = [['id' => 'p0', 'category' => 'Hair Care', 'brand' => 'Olaplex', 'line' => 'No.3', 'name' => 'Hair Perfector', 'size' => '100 ml', 'confidence' => 1]];

it('is for admins only (NFR-05)', function () {
    $this->actingAs($this->sara, 'sanctum')->getJson('/api/admin/supplier-changes')->assertForbidden();
    $this->actingAs($this->omar, 'sanctum')->getJson('/api/admin/supplier-changes')->assertOk();
});

it('lists pending updates with current and proposed values side by side', function () use ($olaplex) {
    pendingChange($this->sara, ['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Khalid R.', 'whatsapp' => '0502237781'], $olaplex);
    $this->actingAs($this->omar, 'sanctum')->getJson('/api/admin/supplier-changes')->assertOk()->assertJsonCount(1)
        ->assertJsonPath('0.requested_by', 'Sara')->assertJsonPath('0.current.contact_name', 'Khalid Rahman')
        ->assertJsonPath('0.proposed.contact_name', 'Khalid R.')->assertJsonPath('0.on_abraa', true)->assertJsonPath('0.in_ghl', true);
});

it('approval (with edits) updates abraa.com and GHL and adds the keywords (FR-33, acceptance 2)', function () use ($olaplex) {
    $c = pendingChange($this->sara, ['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Khalid R.', 'whatsapp' => '0502237781', 'landline' => '04 223 1180'], $olaplex);
    $this->actingAs($this->omar, 'sanctum')->postJson("/api/admin/supplier-changes/{$c->id}/approve", ['proposed' => ['contact_name' => 'Khalid Rahman Ali']])
        ->assertOk()->assertJsonPath('status', 'approved');

    $s = Supplier::find($c->supplier_id);
    $abraa = app(AbraaClient::class)->getSupplier($s->abraa_supplier_id);
    $ghl = app(GhlClient::class)->getContact($s->ghl_contact_id);
    expect($abraa['contact_name'])->toBe('Khalid Rahman Ali')->and($ghl['contact_name'])->toBe('Khalid Rahman Ali')
        ->and($abraa['phones'])->toContain('+97142231180')
        ->and($abraa['keywords'])->toContain('Olaplex', 'Hair Perfector 100 ml')
        ->and($ghl['tags'])->toContain('Olaplex')
        ->and(SupplierTag::where('supplier_id', $s->id)->count())->toBe(1)
        ->and($c->fresh()->decided_by)->toBe($this->omar->id)
        ->and(Visit::find($c->visit_id)->status)->toBe('live');

    $this->postJson("/api/admin/supplier-changes/{$c->id}/approve")->assertStatus(409);
});

it('rejection pushes nothing and keeps the reason', function () use ($olaplex) {
    $c = pendingChange($this->sara, ['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Wrong', 'whatsapp' => '0502237781'], $olaplex);
    $this->actingAs($this->omar, 'sanctum')->postJson("/api/admin/supplier-changes/{$c->id}/reject", ['note' => 'Different shop'])->assertOk()->assertJsonPath('note', 'Different shop');
    $s = Supplier::find($c->supplier_id);
    expect(app(AbraaClient::class)->getSupplier($s->abraa_supplier_id)['contact_name'])->toBe('Khalid Rahman')
        ->and(\App\Models\SyncLog::count())->toBe(0);
});

it('approving an owner\'s new shop creates it on both sides with self-reported tags (FR-35, acceptance 6)', function () use ($olaplex) {
    $owner = User::factory()->owner()->create(['phone' => '+971551112233']);
    $c = pendingChange($owner, ['company_name' => 'Owner Shop', 'whatsapp' => '055 111 2233'], $olaplex, ['source' => 'owner']);
    $this->actingAs($this->omar, 'sanctum')->postJson("/api/admin/supplier-changes/{$c->id}/approve")->assertOk();
    $s = Supplier::find($c->supplier_id);
    expect($s->abraa_supplier_id)->not->toBeNull()->and($s->ghl_contact_id)->not->toBeNull()
        ->and(SupplierTag::where('supplier_id', $s->id)->first()->self_reported)->toBeTrue();
});
