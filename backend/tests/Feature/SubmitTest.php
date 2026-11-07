<?php

use App\Models\NewProduct;
use App\Models\Photo;
use App\Models\Supplier;
use App\Models\SupplierChange;
use App\Models\SupplierTag;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\Visit;
use App\Remote\AbraaClient;
use App\Remote\GhlClient;
use App\Visits\SubmitVisit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake();
    $this->sara = User::where('name', 'Sara')->first();
    $this->actingAs($this->sara, 'sanctum');
});

/** A visit read by the fake AI with the given card, so candidates are computed like in production. */
function reviewed(User $u, array $card, array $x = []): Visit
{
    $v = Visit::factory()->create(array_merge(['user_id' => $u->id, 'status' => 'processing', 'shop_name' => $card['company_name'] ?? 'Shop', 'gps_lat' => 25.27, 'gps_lng' => 55.31], $x));
    Photo::create(['visit_id' => $v->id, 'client_id' => 's1', 'type' => 'shelf', 'path' => 'x.jpg']);
    $fields = collect($card)->map(fn ($val, $k) => ['key' => $k, 'value' => $val, 'confidence' => 0.9])->values()->all();
    app(\App\Extraction\ExtractionResult::class)->store($v, $fields, [], null, 'test');

    return $v->fresh();
}

function fields(array $card): array
{
    return collect($card)->map(fn ($v, $k) => ['key' => $k, 'value' => $v, 'confidence' => 1])->values()->all();
}

function prod(string $id, string $brand, ?string $line, string $name, ?string $size, array $x = []): array
{
    return array_merge(['id' => $id, 'category' => 'Hair Care', 'brand' => $brand, 'line' => $line, 'name' => $name, 'size' => $size, 'confidence' => 0.9], $x);
}

function send(Visit $v, array $card, array $products, ?array $supplier = null)
{
    return test()->postJson("/api/visits/{$v->uuid}/submit", array_filter(['fields' => fields($card), 'products' => $products, 'supplier' => $supplier], fn ($x) => $x !== null));
}

$newShop = ['company_name' => 'Bloom Hair Supply', 'contact_name' => 'Rana', 'whatsapp' => '050 777 1234', 'email' => 'sales@bloom.ae', 'area' => 'Deira, Dubai'];

it('creates a new supplier on abraa.com and GHL, linked, with keywords (FR-31, acceptance 1, 7)', function () use ($newShop) {
    $v = reviewed($this->sara, $newShop);
    expect($v->candidates)->toBe([]);
    $res = send($v, $newShop, [prod('p0', 'Kérastase', 'Nutritive', 'Bain Satin Shampoo', '1L'), prod('p1', 'Olaplex', 'No.3', 'Hair Perfector', '100 ml')])
        ->assertOk()->assertJsonPath('outcome', 'supplier_created')->assertJsonPath('new_products', 1);

    $s = Supplier::find($res->json('supplier_id'));
    expect($s->abraa_supplier_id)->not->toBeNull()->and($s->ghl_contact_id)->not->toBeNull()
        ->and($v->fresh()->status)->toBe('live');
    $remote = app(AbraaClient::class)->getSupplier($s->abraa_supplier_id);
    $contact = app(GhlClient::class)->getContact($s->ghl_contact_id);
    expect($remote['company_name'])->toBe('Bloom Hair Supply')->and($remote['phones'])->toBe(['+971507771234'])
        ->and($remote['ghl_contact_id'])->toBe($s->ghl_contact_id)
        ->and($contact['abraa_supplier_id'])->toBe($s->abraa_supplier_id)
        ->and($remote['keywords'])->toContain('Hair Care', 'Kérastase', 'Nutritive', 'Bain Satin Shampoo 1L', 'Olaplex', 'كيراستاس')
        ->and($contact['tags'])->toContain('Olaplex', 'Hair Perfector 100 ml');
    expect(SupplierTag::where('supplier_id', $s->id)->count())->toBe(2)
        ->and(SyncLog::where('status', 'ok')->pluck('action')->all())->toContain('create_supplier', 'create_contact', 'add_keywords', 'link_ghl');
});

it('requires a choice when candidates exist', function () {
    $card = ['company_name' => 'Al Noor', 'whatsapp' => '050 223 7781'];
    $v = reviewed($this->sara, $card);
    expect($v->candidates[0]['company_name'])->toBe('Al Noor Cosmetics');
    send($v, $card, [])->assertStatus(422)->assertJsonValidationErrors('supplier');
    send($v, $card, [], ['choice' => 'existing', 'key' => 's999'])->assertStatus(422);
});

it('choosing an existing supplier creates an update that waits for approval (FR-32, acceptance 2, 3)', function () {
    $card = ['company_name' => 'Al Noor Cosmetics', 'contact_name' => 'Khalid R.', 'whatsapp' => '050 223 7781', 'landline' => '04 223 1180', 'area' => 'Deira, Dubai'];
    $v = reviewed($this->sara, $card);
    $res = send($v, $card, [prod('p0', 'Olaplex', 'No.3', 'Hair Perfector', '100 ml')], ['choice' => 'existing', 'key' => $v->candidates[0]['key']])
        ->assertOk()->assertJsonPath('outcome', 'update_pending')->assertJsonPath('visit.status', 'pending_approval');

    $change = SupplierChange::find($res->json('change_id'));
    expect($change->status)->toBe('pending')
        ->and($change->current_values['contact_name'])->toBe('Khalid Rahman')
        ->and($change->proposed_values)->toMatchArray(['contact_name' => 'Khalid R.', 'landline' => '+97142231180'])
        ->and($change->keywords_to_add)->toContain('Olaplex');
    $s = Supplier::find($res->json('supplier_id'));
    expect(app(AbraaClient::class)->getSupplier($s->abraa_supplier_id)['contact_name'])->toBe('Khalid Rahman') // unchanged until approved
        ->and(SupplierTag::where('supplier_id', $s->id)->count())->toBe(0)
        ->and(Supplier::count())->toBe(1)
        ->and(SyncLog::count())->toBe(0);
});

it('"it is a new supplier" creates one even with candidates', function () {
    $card = ['company_name' => 'Star Perfume', 'whatsapp' => '050 333 4444', 'area' => 'Deira'];
    $v = reviewed($this->sara, $card);
    expect($v->candidates)->not->toBe([]);
    send($v, $card, [], ['choice' => 'new'])->assertOk()->assertJsonPath('outcome', 'supplier_created');
});

it('owner listings always wait for approval and their tags are self-reported (FR-35)', function () {
    $owner = User::factory()->owner()->create(['phone' => '+971551112233']);
    $this->actingAs($owner, 'sanctum');
    $card = ['company_name' => 'Owner Shop', 'whatsapp' => '055 111 2233'];
    $v = reviewed($owner, $card, ['source' => 'owner']);
    $res = send($v, $card, [prod('p0', 'Olaplex', 'No.3', 'Hair Perfector', '100 ml')])->assertOk()->assertJsonPath('outcome', 'update_pending');
    expect($owner->fresh()->supplier_id)->toBe($res->json('supplier_id'))
        ->and(Supplier::find($res->json('supplier_id'))->abraa_supplier_id)->toBeNull();
});

it('skips unclear products and creates one new-product record per product (FR-40, FR-50)', function () use ($newShop) {
    $v = reviewed($this->sara, $newShop);
    send($v, $newShop, [
        prod('p0', 'Olaplex', 'No.3', 'Hair Perfector', '100 ml'),
        prod('p1', 'Olaplex', 'No.3', 'Hair Perfector', '100 ml'),
        prod('p2', 'Nevada', null, 'unclear', null, ['confidence' => 0.4]),
        prod('p3', 'Kérastase', 'Nutritive', 'Bain Satin Shampoo', '1L'),
    ])->assertJsonPath('new_products', 1)->assertJsonPath('keywords', 9); // English and Arabic names
    expect(NewProduct::sole()->moq)->toBe(24);
});

it('is safe to retry', function () use ($newShop) {
    $v = reviewed($this->sara, $newShop);
    $a = send($v, $newShop, [])->json('supplier_id');
    expect(send($v, $newShop, [])->assertOk()->json('supplier_id'))->toBe($a)->and(Supplier::count())->toBe(1);
});

it('computes MOQ', function (?string $size, int $moq) {
    expect(SubmitVisit::moq($size))->toBe($moq);
})->with([['100 ml', 24], ['50ml', 24], ['250 ml', 12], ['1L', 12], ['0.1 L', 24], [null, 12]]);
