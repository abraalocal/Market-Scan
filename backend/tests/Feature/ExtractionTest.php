<?php

use Anthropic\Client;
use App\Extraction\ClaudeExtractor;
use App\Extraction\ExtractionResult;
use App\Jobs\ExtractVisitJob;
use App\Models\Photo;
use App\Models\Visit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeClaudeTransport;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake();
});

function processing(array $types = ['card', 'storefront', 'shelf', 'closeup']): Visit
{
    $v = Visit::factory()->create(['status' => 'processing', 'shop_name' => 'Bloom Hair Supply']);
    foreach ($types as $i => $t) {
        Storage::put("visits/{$v->uuid}/$t/c$i.jpg", 'jpegbytes');
        Photo::create(['visit_id' => $v->id, 'client_id' => "c$i", 'type' => $t, 'path' => "visits/{$v->uuid}/$t/c$i.jpg"]);
    }

    return $v;
}

function p(array $x = []): array
{
    return array_merge(['id' => 'p0', 'category' => 'Hair Care', 'brand' => 'Kérastase', 'line' => 'Nutritive', 'name' => 'Bain Satin Shampoo', 'size' => '1L', 'barcode' => null, 'confidence' => 0.9], $x);
}

it('fake driver fills fields and products and sets review (FR-20, FR-21)', function () {
    $v = processing();
    ExtractVisitJob::dispatchSync($v);
    $v->refresh();
    expect($v->status)->toBe('review')
        ->and(collect($v->extraction['fields'])->firstWhere('key', 'landline')['confidence'])->toBeLessThan(0.8)
        ->and(count($v->extraction['products']))->toBeGreaterThanOrEqual(8)
        ->and($v->extraction['products'][0]['closeup_photo_id'])->toBe(Photo::where('type', 'closeup')->value('id'));
});

it('computes status on the server from abraa.com (FR-23)', function () {
    $v = processing();
    app(ExtractionResult::class)->store($v, [], [
        p(['id' => 'a', 'brand' => 'kerastase', 'size' => '1 L', 'status' => 'new']),     // alias + spelling, listed on abraa.com
        p(['id' => 'b', 'size' => '250 ml', 'status' => 'on_abraa']),                         // not listed → new
        p(['id' => 'c', 'brand' => 'Unknown', 'confidence' => 0.6]),                           // unclear → check
        p(['id' => 'd', 'brand' => 'X', 'name' => 'blurry', 'barcode' => '4005808158062', 'confidence' => 0.4]), // barcode on abraa.com
    ]);
    $x = $v->fresh()->extraction['products'];
    expect(collect($x)->pluck('status', 'id')->all())->toBe(['a' => 'on_abraa', 'b' => 'new', 'c' => 'check', 'd' => 'on_abraa'])
        ->and($x[0]['brand'])->toBe('Kérastase');
});

it('claude driver sends every photo and reads the structured reply (FR-20..FR-22)', function () {
    $v = processing();
    $closeup = Photo::where('type', 'closeup')->value('id');
    $reply = ['fields' => [['key' => 'company_name', 'value' => 'Bloom Hair Supply', 'confidence' => 0.96], ['key' => 'whatsapp', 'value' => '050 777 1234', 'confidence' => 0.9]],
        'products' => [p(['closeup_photo_id' => $closeup]), p(['id' => 'p1', 'brand' => 'Olaplex', 'line' => 'No.3', 'name' => 'Hair Perfector', 'size' => '100 ml'])]];
    $t = new FakeClaudeTransport([FakeClaudeTransport::message(json_encode($reply))]);
    $ex = new ClaudeExtractor(app(ExtractionResult::class), new Client(apiKey: 'test-key', requestOptions: ['transporter' => $t]));

    $ex->start($v);

    $body = $t->body();
    expect($body['model'])->toBe('claude-opus-5-5')
        ->and($body['output_config']['format']['type'])->toBe('json_schema')
        ->and($body['fallbacks'])->toBe('default')
        ->and(collect($body['messages'][0]['content'])->where('type', 'image')->count())->toBe(4)
        ->and($t->requests[0]->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01');
    $v->refresh();
    expect($v->status)->toBe('review')
        ->and(collect($v->extraction['products'])->pluck('status')->all())->toBe(['on_abraa', 'new'])
        ->and($v->extraction['products'][0]['closeup_photo_id'])->toBe($closeup)
        ->and($v->extraction['driver'])->toBe('claude');
});

it('claude refusal puts the visit in needs_review', function () {
    $v = processing();
    $t = new FakeClaudeTransport([FakeClaudeTransport::message('', 'refusal')]);
    (new ClaudeExtractor(app(ExtractionResult::class), new Client(apiKey: 'k', requestOptions: ['transporter' => $t])))->start($v);
    expect($v->fresh()->status)->toBe('needs_review');
});

it('n8n driver posts signed photo URLs; the callback needs the service token', function () {
    config(['marketscan.ai.extraction_driver' => 'n8n', 'marketscan.ai.n8n_webhook' => 'https://n8n.test/x']);
    Http::fake(['n8n.test/*' => Http::response(['ok' => true])]);
    $v = processing(['card', 'shelf']);
    ExtractVisitJob::dispatchSync($v);
    Http::assertSent(fn (HttpRequest $r) => $r['visit_uuid'] === $v->uuid && count($r['photos']) === 2 && str_contains($r['photos'][0]['url'], 'expir'));

    $body = ['visit_uuid' => $v->uuid, 'fields' => [['key' => 'company_name', 'value' => 'Bloom', 'confidence' => 0.9]], 'products' => [p()]];
    $this->postJson('/api/integrations/extraction-result', $body)->assertUnauthorized();
    $this->postJson('/api/integrations/extraction-result', $body, ['X-Service-Token' => 'test-service-token'])->assertOk()->assertJsonPath('status', 'review');
});

it('a failed AI job leaves the visit in needs_review', function () {
    $v = processing();
    (new ExtractVisitJob($v))->failed(new RuntimeException('timeout'));
    expect($v->fresh()->status)->toBe('needs_review')->and($v->fresh()->extraction['error'])->toContain('timeout');
});
