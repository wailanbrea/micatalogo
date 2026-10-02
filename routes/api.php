<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PosSaleController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Middleware\EnsureApiAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', EnsureApiAccountIsActive::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/shops', [ShopController::class, 'index']);
        Route::get('/shops/{shop}/catalog', [CatalogController::class, 'show']);
        Route::post('/shops/{shop}/pos-sales', [PosSaleController::class, 'store'])->middleware('abilities:pos:write');
    });
});
