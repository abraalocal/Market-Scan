<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One push to abraa.com or GHL (FR-36, NFR-08). */
class SyncLog extends Model
{
    protected $table = 'ms_sync_log';

    protected $fillable = ['target', 'action', 'subject_type', 'subject_id', 'payload', 'status', 'attempts', 'remote_id', 'error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer'];
    }
}
