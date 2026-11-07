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
        $this->app->bind(\Anthropic\Client::class, fn () => new \Anthropic\Client(apiKey: (string) env('ANTHROPIC_API_KEY', '')));
        $this->app->bind(\App\Extraction\Extractor::class, fn ($app) => $app->make(match (config('marketscan.ai.extraction_driver')) {
            'claude' => \App\Extraction\ClaudeExtractor::class,
            'n8n' => \App\Extraction\N8nExtractor::class,
            default => \App\Extraction\FakeExtractor::class,
        }));
        $this->app->bind(\App\Products\ProductSearch::class, fn ($app) => $app->make(match (config('marketscan.search.driver')) {
            'brave' => \App\Products\BraveSearch::class,
            'serpapi' => \App\Products\SerpApiSearch::class,
            default => \App\Products\FakeSearch::class,
        }));
        $this->app->bind(\App\Products\ProductMatcher::class, fn ($app) => $app->make(config('marketscan.ai.match_driver') === 'claude'
            ? \App\Products\ClaudeMatcher::class
            : \App\Products\FakeMatcher::class));
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
