<?php

use App\Jobs\ExtractVisitJob;
use App\Models\Photo;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake();
    Queue::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

function body(array $x = []): array
{
    return array_merge(['uuid' => (string) Str::uuid(), 'shop_name' => 'Bloom Hair Supply', 'lat' => 25.27, 'lng' => 55.31, 'captured_at' => '2026-11-08T08:15:00Z'], $x);
}

function photo(string $uuid, string $type, string $cid, int $w = 800, int $h = 600)
{
    return test()->post("/api/visits/$uuid/photos", ['type' => $type, 'client_id' => $cid, 'file' => UploadedFile::fake()->image("$cid.jpg", $w, $h)], ['Accept' => 'application/json']);
}

it('creates a visit once per uuid (FR-07)', function () {
    $b = body();
    $this->postJson('/api/visits', $b)->assertCreated()->assertJsonPath('status', 'draft')->assertJsonPath('captured_at', '2026-11-08T08:15:00Z')
        ->assertJsonStructure(['uuid', 'shop_name', 'supplier_id', 'source', 'status', 'photo_counts', 'fields', 'products', 'totals', 'candidates', 'captured_at', 'error']);
    $this->postJson('/api/visits', $b)->assertOk();
    expect(Visit::count())->toBe(1);
});

it('owners always visit their own shop', function () {
    $mine = Supplier::factory()->create();
    $this->actingAs(User::factory()->owner()->create(['supplier_id' => $mine->id]), 'sanctum');
    $this->postJson('/api/visits', body(['supplier_id' => Supplier::factory()->create()->id]))->assertJsonPath('source', 'owner')->assertJsonPath('supplier_id', $mine->id);
});

it('stores photos once per client_id, max 1600 px (FR-05, FR-06)', function () {
    $b = body();
    $this->postJson('/api/visits', $b);
    photo($b['uuid'], 'shelf', 'p1', 4000, 3000)->assertCreated();
    photo($b['uuid'], 'shelf', 'p1', 4000, 3000)->assertOk()->assertJsonPath('duplicate', true);
    $p = Photo::sole();
    Storage::assertExists("visits/{$b['uuid']}/shelf/p1.jpg");
    expect([$p->width, $p->height])->toBe([1600, 1200]);
    $this->post("/api/visits/{$b['uuid']}/photos", ['type' => 'card', 'client_id' => 'x', 'file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
});

it('names missing photos, then starts the AI reading once', function () {
    $b = body();
    $this->postJson('/api/visits', $b);
    photo($b['uuid'], 'shelf', 's1');
    $this->postJson("/api/visits/{$b['uuid']}/extract")->assertStatus(422)->assertJsonPath('errors.photos.0', 'Missing photos: business card, shop front.');
    photo($b['uuid'], 'card', 'c1');
    photo($b['uuid'], 'storefront', 'f1');
    $this->postJson("/api/visits/{$b['uuid']}/extract")->assertStatus(202)->assertJsonPath('status', 'processing');
    $this->postJson("/api/visits/{$b['uuid']}/extract")->assertStatus(202);
    Queue::assertPushed(ExtractVisitJob::class, 1);
});

it('lets owners skip the shop front, and the card when their shop is listed', function () {
    $this->actingAs(User::factory()->owner()->create(), 'sanctum');
    $b = body();
    $this->postJson('/api/visits', $b);
    photo($b['uuid'], 'shelf', 's1');
    $this->postJson("/api/visits/{$b['uuid']}/extract")->assertStatus(422)->assertJsonPath('errors.photos.0', 'Missing photos: business card.');

    $this->actingAs(User::factory()->owner()->create(['supplier_id' => Supplier::factory()->create()->id]), 'sanctum');
    $b = body();
    $this->postJson('/api/visits', $b);
    photo($b['uuid'], 'shelf', 's1');
    $this->postJson("/api/visits/{$b['uuid']}/extract")->assertStatus(202);
});

it('lists own visits newest first; admins may pick a user', function () {
    $old = Visit::factory()->create(['user_id' => $this->user->id, 'status' => 'live', 'created_at' => now()->subDay()]);
    $new = Visit::factory()->create(['user_id' => $this->user->id, 'status' => 'review']);
    $other = Visit::factory()->create();
    $this->getJson('/api/visits')->assertJsonCount(2)->assertJsonPath('0.uuid', $new->uuid);
    $this->getJson('/api/visits?status=live')->assertJsonCount(1)->assertJsonPath('0.uuid', $old->uuid);
    $this->getJson("/api/visits/{$other->uuid}")->assertNotFound();
    $this->actingAs(User::factory()->admin()->create(), 'sanctum');
    $this->getJson('/api/visits?user_id='.$other->user_id)->assertJsonCount(1);
    $this->getJson("/api/visits/{$other->uuid}")->assertOk();
});
