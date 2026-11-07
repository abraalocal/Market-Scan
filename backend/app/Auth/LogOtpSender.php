<?php

namespace App\Auth;

use Illuminate\Support\Facades\Log;

/** Development: the code goes to storage/logs/laravel.log. */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        Log::info("OTP for {$phone}: {$code}");
    }
}
