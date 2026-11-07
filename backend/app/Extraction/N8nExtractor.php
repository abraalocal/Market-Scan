<?php

namespace App\Extraction;

use App\Models\Visit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** EXTRACTION_DRIVER=n8n: hands signed photo URLs to an n8n flow, which answers on /integrations/extraction-result. */
class N8nExtractor implements Extractor
{
    public function start(Visit $visit): void
    {
        $url = config('marketscan.ai.n8n_webhook') ?: throw new RuntimeException('N8N_EXTRACT_WEBHOOK is not set.');
        $photos = $visit->photos()->orderBy('id')->get()->map(fn ($p) => [
            'id' => $p->id, 'type' => $p->type, 'url' => Storage::temporaryUrl($p->path, now()->addMinutes(30)),
        ])->all();
        Http::timeout(15)->withHeaders(['X-Service-Token' => (string) config('marketscan.service_token')])
            ->post($url, ['visit_uuid' => $visit->uuid, 'photos' => $photos])->throw();
    }
}
