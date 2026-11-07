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

    'photos' => ['max_kb' => 12 * 1024, 'max_side' => 1600],
];
