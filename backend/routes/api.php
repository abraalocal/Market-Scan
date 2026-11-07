<?php

use App\Http\Controllers\Admin\SupplierChangeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Integrations\ExtractionController;
use Illuminate\Support\Facades\Route;

// Capture App API (docs/openapi.yaml). Bearer token from /auth/verify.
Route::post('/auth/otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/verify', [AuthController::class, 'verify']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/shops/search', [ShopController::class, 'search']);
    Route::get('/shops/check-phone', [ShopController::class, 'checkPhone']);

    Route::get('/visits', [VisitController::class, 'index']);
    Route::post('/visits', [VisitController::class, 'store']);
    Route::get('/visits/{uuid}', [VisitController::class, 'show']);
    Route::post('/visits/{uuid}/photos', [VisitController::class, 'uploadPhoto']);
    Route::post('/visits/{uuid}/extract', [VisitController::class, 'extract']);
    Route::post('/visits/{uuid}/submit', [VisitController::class, 'submit']);

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/supplier-changes', [SupplierChangeController::class, 'index']);
        Route::post('/supplier-changes/{id}/approve', [SupplierChangeController::class, 'approve'])->whereNumber('id');
        Route::post('/supplier-changes/{id}/reject', [SupplierChangeController::class, 'reject'])->whereNumber('id');
    });
});

// Automation callbacks (optional n8n driver). Shared secret in X-Service-Token.
Route::prefix('integrations')->middleware('service.token')->group(function () {
    Route::post('/extraction-result', [ExtractionController::class, 'store']);
});
