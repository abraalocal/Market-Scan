<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Auth\OtpSender::class, fn () => config('marketscan.otp.driver') === 'whatsapp'
            ? new \App\Auth\WhatsAppOtpSender
            : new \App\Auth\LogOtpSender);
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
