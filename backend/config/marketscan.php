<?php

return [
    'service_token' => env('SERVICE_TOKEN'),
    'timezone' => env('TEAM_TIMEZONE', 'Asia/Dubai'),

    'otp' => [
        'driver' => env('OTP_DRIVER', 'log'),
        'ttl_seconds' => 300,
        'max_attempts' => 5,
        'per_phone_limit' => 3,
        'window_seconds' => 600,
        'dev_code' => '123456',                 // only when APP_ENV=local
    ],

    'ai' => [
        'extraction_driver' => env('EXTRACTION_DRIVER', 'fake'),   // fake | claude | n8n
        'match_driver' => env('PRODUCT_MATCH_DRIVER', 'fake'),     // fake | claude
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'n8n_webhook' => env('N8N_EXTRACT_WEBHOOK'),
    ],

    'search' => [
        'driver' => env('PRODUCT_SEARCH_DRIVER', 'fake'),   // fake | brave | serpapi
        'brave_key' => env('BRAVE_API_KEY'),
        'serpapi_key' => env('SERPAPI_KEY'),
    ],

    'abraa' => [
        'driver' => env('ABRAA_DRIVER', 'fake'),
        'url' => env('ABRAA_API_URL'),
        'token' => env('ABRAA_API_TOKEN'),
    ],

    'ghl' => [
        'driver' => env('GHL_DRIVER', 'fake'),
        'key' => env('GHL_API_KEY'),
        'location_id' => env('GHL_LOCATION_ID'),
        'base_url' => env('GHL_BASE_URL', 'https://services.leadconnectorhq.com'),
        'version' => '2021-07-28',
        'max_tags' => 50,
    ],

    'photos' => ['max_kb' => 12 * 1024, 'max_side' => 1600],
];
