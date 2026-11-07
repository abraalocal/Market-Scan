<?php

namespace App\Jobs;

use App\Extraction\Extractor;
use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractVisitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public Visit $visit) {}

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(Extractor $extractor): void
    {
        if ($this->visit->fresh()?->status === 'processing') {
            $extractor->start($this->visit->fresh());
        }
    }

    public function failed(\Throwable $e): void
    {
        $v = $this->visit->fresh();
        if ($v && $v->status === 'processing') {
            $v->update(['status' => 'needs_review', 'extraction' => array_merge($v->extraction ?? [], ['error' => 'AI reading failed: '.$e->getMessage()])]);
        }
    }
}
