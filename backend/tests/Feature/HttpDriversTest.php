<?php

use App\Remote\HttpAbraaClient;
use App\Remote\HttpGhlClient;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['marketscan.abraa.url' => 'https://abraa.test/api/ms', 'marketscan.abraa.token' => 'tok',
        'marketscan.ghl.key' => 'ghl-key', 'marketscan.ghl.location_id' => 'loc1', 'marketscan.ghl.base_url' => 'https://ghl.test', 'marketscan.ghl.abraa_field_id' => 'fld1']);
});

it('talks to the abraa.com API per docs/abraa-api.md', function () {
    Http::fake([
        'abraa.test/api/ms/suppliers/search*' => Http::response(['data' => [['id' => 7, 'company_name' => 'Al Noor', 'phones' => ['+971502237781']]]]),
        'abraa.test/api/ms/suppliers' => Http::response(['data' => ['id' => 8]], 201),
        'abraa.test/api/ms/suppliers/8/keywords' => Http::response(['data' => []]),
        'abraa.test/api/ms/products/search*' => Http::response(['data' => [['id' => 99]]]),
        'abraa.test/api/ms/products' => Http::response(['data' => ['id' => 100]], 201),
    ]);
    $c = new HttpAbraaClient;
    expect($c->searchSuppliers(['+971502237781'])[0])->toMatchArray(['id' => 7, 'company_name' => 'Al Noor'])
        ->and($c->createSupplier(['company_name' => 'X']))->toBe(8)
        ->and($c->findProduct(['barcode' => '123']))->toBe(99)
        ->and($c->createProduct(['title_en' => 'T']))->toBe(100);
    $c->addKeywords(8, ['Hair Care']);
    Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer tok') && str_contains($r->url(), 'products/search?barcode=123'));
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/suppliers/8/keywords') && $r['keywords'] === ['Hair Care']);
});

it('talks to GHL v2 contacts and conversations', function () {
    Http::fake([
        'ghl.test/contacts/search/duplicate*' => Http::response(['contact' => ['id' => 'c1', 'companyName' => 'Al Noor', 'phone' => '+971502237781', 'customFields' => [['id' => 'fld1', 'value' => '7']], 'tags' => ['Hair Care']]]),
        'ghl.test/contacts/' => Http::response(['contact' => ['id' => 'c2']], 201),
        'ghl.test/contacts/c2/tags' => Http::response(['tags' => []]),
        'ghl.test/conversations/messages' => Http::response(['messageId' => 'm1']),
    ]);
    $g = new HttpGhlClient;
    expect($g->searchContacts(['+971502237781'])[0])->toMatchArray(['id' => 'c1', 'company_name' => 'Al Noor', 'phones' => ['+971502237781'], 'abraa_supplier_id' => 7])
        ->and($g->createContact(['company_name' => 'Bloom', 'phones' => ['+971507771234'], 'abraa_supplier_id' => 8, 'area' => 'Deira'], ['Hair Care']))->toBe('c2');
    $g->addTags('c2', ['Olaplex']);
    $g->sendWhatsApp('c2', 'Hello');
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/contacts/') && $r['companyName'] === 'Bloom' && $r['locationId'] === 'loc1'
        && $r['customFields'][0] === ['id' => 'fld1', 'field_value' => '8'] && $r->hasHeader('Version', '2021-07-28'));
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/conversations/messages') && $r['type'] === 'WhatsApp');
});
