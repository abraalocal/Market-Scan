<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\Visit;
use App\Remote\AbraaClient;
use App\Remote\GhlClient;
use App\Suppliers\SyncRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Creates or updates the supplier on abraa.com and GHL together and adds its keywords (FR-31, FR-33, FR-36, FR-41).
 * Safe to retry: whatever was created is stored before the next call.
 */
class PushSupplier implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public int $supplierId, public array $keywords, public ?int $visitId = null, public bool $update = false) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(AbraaClient $abraa, GhlClient $ghl, SyncRecorder $rec): void
    {
        $s = Supplier::findOrFail($this->supplierId);
        $data = $s->only(['company_name', 'contact_name', 'email', 'website', 'area']) + ['phones' => $s->phones()->orderBy('id')->pluck('phone')->all()];
        $tags = array_slice($this->keywords, 0, (int) config('marketscan.ghl.max_tags'));

        if (! $s->abraa_supplier_id) {
            $s->abraa_supplier_id = $rec->run('abraa', 'create_supplier', 'supplier', $s->id, $data, fn () => $abraa->createSupplier($data + ['ghl_contact_id' => $s->ghl_contact_id]));
            $s->save();
        } elseif ($this->update) {
            $rec->run('abraa', 'update_supplier', 'supplier', $s->id, $data, fn () => $abraa->updateSupplier($s->abraa_supplier_id, $data));
        }
        if ($this->keywords) {
            $rec->run('abraa', 'add_keywords', 'supplier', $s->id, ['keywords' => $this->keywords], fn () => $abraa->addKeywords($s->abraa_supplier_id, $this->keywords));
        }

        if (! $s->ghl_contact_id) {
            $s->ghl_contact_id = $rec->run('ghl', 'create_contact', 'supplier', $s->id, $data + ['tags' => $tags],
                fn () => $ghl->createContact($data + ['abraa_supplier_id' => $s->abraa_supplier_id], $tags));
            $s->save();
            // Link back: abraa.com stores the GHL contact id (FR-36).
            $rec->run('abraa', 'link_ghl', 'supplier', $s->id, ['ghl_contact_id' => $s->ghl_contact_id], fn () => $abraa->updateSupplier($s->abraa_supplier_id, ['ghl_contact_id' => $s->ghl_contact_id]));
        } else {
            if ($this->update) {
                $rec->run('ghl', 'update_contact', 'supplier', $s->id, $data, fn () => $ghl->updateContact($s->ghl_contact_id, $data + ['abraa_supplier_id' => $s->abraa_supplier_id]));
            }
            if ($tags) {
                $rec->run('ghl', 'add_tags', 'supplier', $s->id, ['tags' => $tags], fn () => $ghl->addTags($s->ghl_contact_id, $tags));
            }
        }

        if ($this->visitId) {
            Visit::whereKey($this->visitId)->whereIn('status', ['submitted', 'pending_approval'])->update(['status' => 'live']);
        }
    }
}
