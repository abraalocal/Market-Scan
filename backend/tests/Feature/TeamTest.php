<?php

use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\User;
use App\Models\Visit;
use App\Remote\FakeGhlClient;
use App\Support\JoinCode;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->sara = User::where('name', 'Sara')->first();
    FakeGhlClient::$sent = [];
});

function liveVisit(User $u, ?Supplier $s, string $outcome = 'supplier_created', array $x = []): Visit
{
    return Visit::factory()->create(array_merge(['user_id' => $u->id, 'supplier_id' => $s?->id, 'status' => 'live', 'extraction' => [
        'reviewed_fields' => [['key' => 'contact_name', 'value' => 'Khalid Rahman', 'confidence' => 1]],
        'submitted' => [['ref_id' => 1], ['ref_id' => 2], ['ref_id' => 3]],
        'submit' => ['outcome' => $outcome],
    ]], $x));
}

it('counts today\'s visits and new suppliers against the targets (FR-10)', function () {
    $s = Supplier::factory()->create();
    liveVisit($this->sara, $s);
    liveVisit($this->sara, $s, 'update_pending');
    liveVisit($this->sara, $s, 'supplier_created', ['created_at' => now()->subDays(2)]);
    Visit::factory()->create(['user_id' => $this->sara->id, 'status' => 'draft']);
    $this->actingAs($this->sara, 'sanctum')->getJson('/api/targets/me')->assertExactJson(['visits' => 2, 'visit_target' => 10, 'suppliers' => 1, 'supplier_target' => 5]);
});

it('gives admins the summary and team view', function () {
    $this->actingAs($this->sara, 'sanctum')->getJson('/api/admin/summary')->assertForbidden();
    $v = liveVisit($this->sara, Supplier::factory()->create());
    SupplierChange::create(['visit_id' => $v->id, 'requested_by' => $this->sara->id, 'current_values' => [], 'proposed_values' => [], 'keywords_to_add' => []]);
    $this->actingAs(User::where('name', 'Omar')->first(), 'sanctum')->getJson('/api/admin/summary')->assertOk()
        ->assertJsonPath('waiting.supplier_changes', 1)->assertJsonPath('totals.visits', 1)->assertJsonPath('totals.visit_target', 30)
        ->assertJsonPath('auto_publish', false)->assertJsonCount(3, 'members');
});

it('shows the owner their shop with its approval state (FR-11)', function () {
    $s = Supplier::factory()->create(['company_name' => 'Owner Shop', 'area' => 'Deira']);
    $owner = User::factory()->owner()->create(['supplier_id' => $s->id]);
    $v = liveVisit($owner, $s, 'update_pending', ['source' => 'owner', 'status' => 'pending_approval']);
    SupplierChange::create(['visit_id' => $v->id, 'requested_by' => $owner->id, 'supplier_id' => $s->id, 'current_values' => [], 'proposed_values' => [], 'keywords_to_add' => []]);
    $this->actingAs($owner, 'sanctum')->getJson('/api/owner/shop')->assertOk()
        ->assertJson(['supplier_id' => $s->id, 'company_name' => 'Owner Shop', 'products' => 0, 'pending_approval' => true, 'on_abraa' => false]);
    $this->actingAs(User::factory()->owner()->create(), 'sanctum')->getJson('/api/owner/shop')->assertContent('null');
    $this->actingAs($this->sara, 'sanctum')->getJson('/api/owner/shop')->assertForbidden();
});

it('sends the welcome through GHL WhatsApp, or gives the join link for a QR (FR-12)', function () {
    $s = Supplier::factory()->create(['company_name' => 'Al Noor Cosmetics', 'ghl_contact_id' => 'ghl_abc']);
    $v = liveVisit($this->sara, $s);
    $this->actingAs($this->sara, 'sanctum');
    $code = JoinCode::for($s->id);
    $this->postJson("/api/visits/{$v->uuid}/welcome", ['channel' => 'whatsapp'])->assertOk()->assertJsonPath('sent', true)
        ->assertJsonPath('join_url', "https://abraa.com/join/$code")
        ->assertJsonPath('message', "Hello Khalid, this is Sara from Abraa. Thank you for your time today at Al Noor Cosmetics.\n\nYour shop is now on Abraa.com with 3 products. Your listing is free, and your first buyer lead is free too.\n\nComplete your profile here: https://abraa.com/join/$code");
    expect(FakeGhlClient::$sent[0][0])->toBe('ghl_abc');
    $this->postJson("/api/visits/{$v->uuid}/welcome", ['channel' => 'qr'])->assertJsonPath('sent', false);
    expect(FakeGhlClient::$sent)->toHaveCount(1);

    $draft = Visit::factory()->create(['user_id' => $this->sara->id, 'status' => 'review']);
    $this->postJson("/api/visits/{$draft->uuid}/welcome")->assertStatus(409);
});
