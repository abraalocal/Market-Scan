<?php

use Anthropic\Client;
use App\Jobs\FindProduct;
use App\Models\NewProduct;
use App\Models\Photo;
use App\Models\ProductRef;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Products\BraveSearch;
use App\Products\ClaudeMatcher;
use App\Products\SerpApiSearch;
use App\Remote\AbraaClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeTransport;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake();
    $this->omar = User::where('name', 'Omar')->first();
});

/** A new product as submit would create it. */
function newProduct(string $brand, ?string $line, string $name, string $size): NewProduct
{
    [$ref] = app(\App\Catalogue\ProductTree::class)->upsert(['category' => 'Hair Care', 'brand' => $brand, 'line' => $line, 'name' => $name, 'size' => $size]);
    $v = Visit::factory()->create(['status' => 'live']);
    Storage::put('c.jpg', 'jpeg');
    $photo = Photo::create(['visit_id' => $v->id, 'client_id' => 'c', 'type' => 'closeup', 'path' => 'c.jpg']);

    return NewProduct::create(['visit_id' => $v->id, 'ref_id' => $ref->id, 'image_photo_id' => $photo->id, 'moq' => 24]);
}

it('marks an exact web match ready and waits for one click when auto-publish is off (FR-52..FR-54)', function () {
    $np = newProduct('Olaplex', 'No.3', 'Hair Perfector', '100 ml');
    FindProduct::dispatchSync($np->id);
    $np->refresh();
    expect($np->status)->toBe('ready')->and($np->source_url)->toBe('https://brand.example/olaplex-no-3-hair-perfector-100-ml')
        ->and($np->title_en)->toBe('Olaplex No.3 Hair Perfector 100 ml – Wholesale')->and($np->title_ar)->toContain('جملة')
        ->and($np->specs['Size'])->toBe('100 ml');

    $this->actingAs($this->omar, 'sanctum')->getJson('/api/admin/products')->assertJsonCount(1)->assertJsonPath('0.status', 'ready')
        ->assertJsonPath('0.seen.brand', 'Olaplex');
    $this->postJson("/api/admin/products/{$np->id}/publish")->assertOk();
    $np->refresh();
    expect($np->status)->toBe('published')->and($np->abraa_product_id)->not->toBeNull()
        ->and($np->ref->abraa_product_id)->toBe($np->abraa_product_id)
        ->and(app(AbraaClient::class)->findProduct(['brand' => 'Olaplex', 'line' => 'No.3', 'name' => 'Hair Perfector 100 ml', 'size' => '100 ml']))->toBe($np->abraa_product_id);
});

it('publishes straight away when auto-publish is on (acceptance 5)', function () {
    $this->actingAs($this->omar, 'sanctum')->putJson('/api/admin/settings', ['auto_publish' => true])->assertJson(['auto_publish' => true]);
    $np = newProduct('Color Wow', null, 'Dream Coat Supernatural Spray', '200 ml');
    FindProduct::dispatchSync($np->id);
    expect($np->fresh()->status)->toBe('published');
});

it('leaves products with no exact match for a person, who can edit and publish (FR-55)', function () {
    $np = newProduct('Unknown Brand', null, 'Mystery Serum', '50 ml');
    FindProduct::dispatchSync($np->id);
    expect($np->fresh()->status)->toBe('needs_person')->and($np->fresh()->match_reasons)->toBe('Nothing found on the web.');

    $this->actingAs($this->omar, 'sanctum');
    $this->postJson("/api/admin/products/{$np->id}/publish")->assertStatus(422);
    $this->patchJson("/api/admin/products/{$np->id}", ['title_en' => 'Mystery Serum 50 ml – Wholesale', 'title_ar' => 'سيروم – جملة', 'source_url' => 'https://brand.example/serum'])->assertOk();
    $this->postJson("/api/admin/products/{$np->id}/publish")->assertOk();
    expect($np->fresh()->status)->toBe('published')->and($np->fresh()->decided_by)->toBe($this->omar->id);
});

it('lets the admin reject, and keeps admin screens for admins', function () {
    $np = newProduct('Unknown Brand', null, 'Mystery Serum', '50 ml');
    $this->actingAs(User::where('name', 'Sara')->first(), 'sanctum')->getJson('/api/admin/products')->assertForbidden();
    $this->actingAs($this->omar, 'sanctum')->postJson("/api/admin/products/{$np->id}/reject", ['note' => 'Tester, not for sale'])->assertOk()->assertJsonPath('status', 'rejected');
});

it('stores pack shots from the matched page with their source', function () {
    config(['marketscan.ai.match_driver' => 'claude']);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    Http::fake(['img.example/*' => Http::response($png, 200, ['Content-Type' => 'image/png']), 'bad.example/*' => Http::response('x', 404)]);
    $reply = ['exact' => true, 'confidence' => 0.93, 'reasons' => 'Same brand, line, name and 100 ml.', 'source_url' => 'https://olaplex.example/no3', 'source_name' => 'olaplex.example',
        'title_en' => 'Olaplex No.3 Hair Perfector 100 ml – Wholesale', 'title_ar' => 'أولابلكس رقم 3 – جملة', 'desc_en' => 'Bond repair treatment.', 'desc_ar' => 'علاج لإصلاح الشعر.',
        'specs' => [['name' => 'Size', 'value' => '100 ml']], 'images' => ['https://img.example/1.png', 'https://bad.example/2.png', 'http://insecure.example/3.png']];
    $t = new FakeClaudeTransport([FakeClaudeTransport::message('', 'pause_turn'), FakeClaudeTransport::message(json_encode($reply))]);
    app()->instance(\App\Products\ProductMatcher::class, new ClaudeMatcher(new Client(apiKey: 'k', requestOptions: ['transporter' => $t])));

    $np = newProduct('Olaplex', 'No.3', 'Hair Perfector', '100 ml');
    FindProduct::dispatchSync($np->id);

    $np->refresh();
    expect($np->status)->toBe('ready')->and($np->specs)->toBe(['Size' => '100 ml'])
        ->and($np->images)->toBe([['path' => "products/{$np->id}/0.jpg", 'source_url' => 'https://img.example/1.png']]);
    Storage::assertExists("products/{$np->id}/0.jpg");
    $body = $t->body(0);
    expect($body['tools'][0])->toMatchArray(['type' => 'web_fetch_20260209', 'name' => 'web_fetch', 'max_uses' => 4])
        ->and(collect($body['messages'][0]['content'])->where('type', 'image')->count())->toBe(1)
        ->and(count($t->requests))->toBe(2)                    // continued after pause_turn
        ->and($t->body(1)['messages'][1]['role'])->toBe('assistant');
});

it('parses Brave and SerpAPI results', function () {
    config(['marketscan.search.brave_key' => 'b', 'marketscan.search.serpapi_key' => 's']);
    Http::fake([
        'api.search.brave.com/*' => Http::response(['web' => ['results' => [['title' => 'Olaplex No.3', 'url' => 'https://olaplex.com/no3', 'description' => '<b>Hair</b> Perfector']]]]),
        'serpapi.com/*' => Http::response(['organic_results' => [['title' => 'Olaplex No.3', 'link' => 'https://olaplex.com/no3', 'snippet' => 'Hair Perfector', 'source' => 'Olaplex']]]),
    ]);
    expect((new BraveSearch)->search('Olaplex No.3')[0])->toBe(['title' => 'Olaplex No.3', 'url' => 'https://olaplex.com/no3', 'snippet' => 'Hair Perfector', 'source' => 'olaplex.com'])
        ->and((new SerpApiSearch)->search('Olaplex No.3')[0]['source'])->toBe('Olaplex');
    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'brave') && $r->hasHeader('X-Subscription-Token', 'b'));
});

it('a failed lookup goes to a person', function () {
    $np = newProduct('Olaplex', 'No.3', 'Hair Perfector', '100 ml');
    (new FindProduct($np->id))->failed(new RuntimeException('search down'));
    expect($np->fresh()->status)->toBe('needs_person');
});
