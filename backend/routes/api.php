<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ShopController;
use Illuminate\Support\Facades\Route;

// Capture App API (docs/openapi.yaml). Bearer token from /auth/verify.
Route::post('/auth/otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/verify', [AuthController::class, 'verify']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/shops/search', [ShopController::class, 'search']);
    Route::get('/shops/check-phone', [ShopController::class, 'checkPhone']);
});
