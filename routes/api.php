<?php

use App\Http\Controllers\Api\V1\AdminShopController;
use App\Http\Controllers\Api\V1\AndroidUpdateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogMediaController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinanceReportController;
use App\Http\Controllers\Api\V1\InventoryImportController;
use App\Http\Controllers\Api\V1\MobileOperationController;
use App\Http\Controllers\Api\V1\PosSaleController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Middleware\EnsureApiAccountIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/app-updates/android', [AndroidUpdateController::class, 'show']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['throttle:login', 'app.version']);

    Route::middleware(['auth:sanctum', EnsureApiAccountIsActive::class, 'app.version'])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [AuthController::class, 'update']);
        Route::get('/shops', [ShopController::class, 'index']);
        Route::get('/admin/shops', [AdminShopController::class, 'index']);
        Route::put('/admin/shops/{shop}', [AdminShopController::class, 'update']);
        Route::post('/shops/{shop}/sellers', [ShopController::class, 'storeSeller']);
        Route::put('/shops/{shop}/sellers/{seller}/menus', [ShopController::class, 'updateSellerMenus']);
        Route::get('/shops/{shop}/catalog', [CatalogController::class, 'show']);
        Route::post('/shops/{shop}/inventory-import/preview', [InventoryImportController::class, 'preview']);
        Route::post('/shops/{shop}/inventory-import', [InventoryImportController::class, 'store']);
        Route::post('/shops/{shop}/inventory-import/{session}/confirm', [InventoryImportController::class, 'confirm']);
        Route::get('/catalog/media/{barcode}', [CatalogMediaController::class, 'show'])
            ->middleware('throttle:catalog-media')
            ->middleware('abilities:catalog:read')
            ->where('barcode', '[0-9 -]{8,32}');
        Route::post('/shops/{shop}/pos-sales', [PosSaleController::class, 'store'])->middleware('abilities:pos:write');
        Route::post('/shops/{shop}/mobile-operations', [MobileOperationController::class, 'store'])->middleware(['abilities:pos:write', 'throttle:60,1']);
        Route::get('/shops/{shop}/customers', [CustomerController::class, 'index'])->middleware('abilities:customers:read');
        Route::post('/shops/{shop}/customers', [CustomerController::class, 'store'])->middleware('abilities:customers:write');
        Route::get('/shops/{shop}/customers/{customer}', [CustomerController::class, 'show'])->middleware('abilities:customers:read');
        Route::post('/shops/{shop}/customers/{customer}/payments', [CustomerController::class, 'payment'])->middleware('abilities:customers:write');
        Route::post('/shops/{shop}/customers/{customer}/adjustments', [CustomerController::class, 'adjustment'])->middleware('abilities:customers:write');

        // Financial & Cash Control API
        Route::get('/shops/{shop}/finance/summary', [FinanceReportController::class, 'summary']);
        Route::get('/shops/{shop}/reports/income-statement', [FinanceReportController::class, 'incomeStatement']);
        Route::get('/shops/{shop}/reports/cash-flow', [FinanceReportController::class, 'cashFlow']);

        Route::get('/shops/{shop}/cash-sessions/current', [CashRegisterController::class, 'current'])->middleware('menu:cash');
        Route::post('/shops/{shop}/cash-sessions/open', [CashRegisterController::class, 'open'])->middleware('menu:cash');
        Route::post('/shops/{shop}/cash-sessions/{session}/close', [CashRegisterController::class, 'close'])->middleware('menu:cash');
        Route::post('/shops/{shop}/cash-sessions/{session}/movements', [CashRegisterController::class, 'movement'])->middleware('menu:cash');

        Route::get('/shops/{shop}/expenses', [ExpenseController::class, 'index'])->middleware('menu:expenses');
        Route::get('/shops/{shop}/expense-categories', [ExpenseController::class, 'categories'])->middleware('menu:expenses');
        Route::post('/shops/{shop}/expenses', [ExpenseController::class, 'store'])->middleware('menu:expenses');
        Route::post('/shops/{shop}/expenses/{expense}/payments', [ExpenseController::class, 'pay'])->middleware('menu:expenses');
    });
});
