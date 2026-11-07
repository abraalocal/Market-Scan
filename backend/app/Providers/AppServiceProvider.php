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
        $this->app->bind(\App\Remote\AbraaClient::class, fn ($app) => $app->make(match (config('marketscan.abraa.driver')) {
            'http' => \App\Remote\HttpAbraaClient::class,
            default => \App\Remote\FakeAbraaClient::class,
        }));
        $this->app->bind(\App\Remote\GhlClient::class, fn ($app) => $app->make(match (config('marketscan.ghl.driver')) {
            'http' => \App\Remote\HttpGhlClient::class,
            default => \App\Remote\FakeGhlClient::class,
        }));
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
