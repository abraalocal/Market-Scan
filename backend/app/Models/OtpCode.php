<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ms_otp_codes';

    protected $fillable = ['phone', 'code_hash', 'attempts', 'expires_at', 'used_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
