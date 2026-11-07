<?php

namespace App\Suppliers;

use App\Models\SyncLog;
use Throwable;

/** Runs one push to abraa.com or GHL and records it in ms_sync_log (FR-36, NFR-08). */
class SyncRecorder
{
    public function run(string $target, string $action, string $type, int $id, array $payload, callable $call): mixed
    {
        $log = SyncLog::create(['target' => $target, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'payload' => $payload, 'status' => 'queued', 'attempts' => 1]);
        try {
            $result = $call();
            $log->update(['status' => 'ok', 'remote_id' => is_scalar($result) ? (string) $result : null]);

            return $result;
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }
}
