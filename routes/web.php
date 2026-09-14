<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminGlobalCategoryController;
use App\Http\Controllers\AdminModerationController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CatalogHomeController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\PublicProductController;
use App\Http\Controllers\PublicReportController;
use App\Http\Controllers\PublicShopController;
use App\Http\Controllers\SellerBulkProductController;
use App\Http\Controllers\SellerInventoryController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\SellerShopController;
use App\Http\Controllers\SellerShopMetricController;
use App\Http\Controllers\ShopCategoryController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\WhatsAppRedirectController;
use App\Models\GlobalCategory;
use Illuminate\Support\Facades\Route;

Route::get('/', CatalogHomeController::class)->name('home');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/tienda/{shop:slug}', [PublicShopController::class, 'show'])->name('shops.show');
Route::get('/tienda/{shop:slug}/producto/{product:slug}', [PublicProductController::class, 'show'])->name('products.show');

Route::get('/r/wa/tienda/{shop:slug}', [WhatsAppRedirectController::class, 'shop'])->name('track.wa.shop');
Route::get('/r/wa/tienda/{shop:slug}/producto/{product:slug}', [WhatsAppRedirectController::class, 'product'])->name('track.wa.product');

Route::post('/reportar', [PublicReportController::class, 'store'])->middleware('throttle:report')->name('reports.store');

Route::get('/email/verify', [EmailVerificationCodeController::class, 'show'])->name('verification.notice');
Route::post('/email/verify-code', [EmailVerificationCodeController::class, 'verify'])->middleware('throttle:10,1')->name('verification.verify-code');
Route::post('/email/verification-code', [EmailVerificationCodeController::class, 'resend'])->middleware('throttle:5,10')->name('verification.send-code');
Route::get('/email/activar/{id}/{hash}', [EmailVerificationCodeController::class, 'verifyDirectLink'])->middleware('throttle:10,1')->name('verification.verify-link');


Route::middleware(['auth', 'verified'])->prefix('panel')->name('seller.')->group(function () {
    Route::get('/', [SellerShopController::class, 'index'])->name('dashboard');
    Route::get('/tiendas/crear', [SellerShopController::class, 'create'])->name('shops.create');
    Route::post('/tiendas', [SellerShopController::class, 'store'])->name('shops.store');
    Route::get('/tiendas/{shop}/editar', [SellerShopController::class, 'edit'])
        ->middleware('can:update,shop')
        ->name('shops.edit');
    Route::put('/tiendas/{shop}', [SellerShopController::class, 'update'])
        ->middleware('can:update,shop')
        ->name('shops.update');
    Route::delete('/tiendas/{shop}', [SellerShopController::class, 'destroy'])
        ->middleware('can:delete,shop')
        ->name('shops.destroy');
    Route::scopeBindings()->middleware('can:update,shop')->group(function () {
        Route::get('/tiendas/{shop}/categorias', [ShopCategoryController::class, 'index'])->name('shops.categories.index');
        Route::post('/tiendas/{shop}/categorias', [ShopCategoryController::class, 'store'])->name('shops.categories.store');
        Route::put('/tiendas/{shop}/categorias/{category}', [ShopCategoryController::class, 'update'])->middleware('can:update,category')->name('shops.categories.update');
        Route::delete('/tiendas/{shop}/categorias/{category}', [ShopCategoryController::class, 'destroy'])->middleware('can:delete,category')->name('shops.categories.destroy');

        Route::get('/tiendas/{shop}/productos', [SellerProductController::class, 'index'])->name('shops.products.index');
        Route::get('/tiendas/{shop}/productos/crear', [SellerProductController::class, 'create'])->name('shops.products.create');
        Route::post('/tiendas/{shop}/productos', [SellerProductController::class, 'store'])->name('shops.products.store');
        Route::get('/tiendas/{shop}/subida-masiva', [SellerBulkProductController::class, 'create'])->name('shops.products.bulk.create');
        Route::post('/tiendas/{shop}/subida-masiva', [SellerBulkProductController::class, 'store'])->name('shops.products.bulk.store');
        Route::get('/tiendas/{shop}/productos/{product}/editar', [SellerProductController::class, 'edit'])->middleware('can:update,product')->name('shops.products.edit');
        Route::put('/tiendas/{shop}/productos/{product}', [SellerProductController::class, 'update'])->middleware('can:update,product')->name('shops.products.update');
        Route::delete('/tiendas/{shop}/productos/{product}', [SellerProductController::class, 'destroy'])->middleware('can:delete,product')->name('shops.products.destroy');
        Route::post('/tiendas/{shop}/productos/{product}/restaurar', [SellerProductController::class, 'restore'])->name('shops.products.restore');
        Route::post('/tiendas/{shop}/productos/{product}/imagenes', [SellerProductController::class, 'uploadImage'])->middleware('can:update,product')->name('shops.products.images.store');
        Route::delete('/tiendas/{shop}/productos/{product}/imagenes/{image}', [SellerProductController::class, 'destroyImage'])->middleware('can:update,product')->name('shops.products.images.destroy');

        Route::get('/tiendas/{shop}/metricas', [SellerShopMetricController::class, 'index'])->name('shops.metrics.index');
        Route::get('/tiendas/{shop}/qr/descargar', [SellerShopMetricController::class, 'downloadQr'])->name('shops.qr.download');
        Route::get('/tiendas/{shop}/qr/imprimir', [SellerShopMetricController::class, 'print'])->name('shops.qr.print');

        Route::get('/tiendas/{shop}/inventario', [SellerInventoryController::class, 'index'])->name('shops.inventory.index');
        Route::post('/tiendas/{shop}/productos/{product}/inventario/venta', [SellerInventoryController::class, 'recordSale'])->middleware('can:update,product')->name('shops.inventory.sale');
        Route::post('/tiendas/{shop}/productos/{product}/inventario/reposicion', [SellerInventoryController::class, 'recordRestock'])->middleware('can:update,product')->name('shops.inventory.restock');
        Route::post('/tiendas/{shop}/productos/{product}/inventario/ajuste', [SellerInventoryController::class, 'adjustStock'])->middleware('can:update,product')->name('shops.inventory.adjustment');
        Route::get('/tiendas/{shop}/productos/{product}/inventario/movimientos', [SellerInventoryController::class, 'movements'])->middleware('can:update,product')->name('shops.inventory.movements');
    });
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/categorias', [AdminGlobalCategoryController::class, 'index'])->can('viewAny', GlobalCategory::class)->name('categories.index');
    Route::post('/categorias', [AdminGlobalCategoryController::class, 'store'])->can('create', GlobalCategory::class)->name('categories.store');
    Route::put('/categorias/{category}', [AdminGlobalCategoryController::class, 'update'])->can('update', 'category')->name('categories.update');
    Route::delete('/categorias/{category}', [AdminGlobalCategoryController::class, 'destroy'])->can('delete', 'category')->name('categories.destroy');

    Route::get('/reportes', [AdminModerationController::class, 'index'])->name('reports.index');
    Route::get('/reportes/{report}', [AdminModerationController::class, 'show'])->name('reports.show');
    Route::post('/reportes/{report}/resolver', [AdminModerationController::class, 'resolve'])->name('reports.resolve');
    Route::post('/tiendas/{shop}/toggle-status', [AdminModerationController::class, 'toggleShopStatus'])->name('shops.toggle-status');
    Route::post('/productos/{product}/toggle-status', [AdminModerationController::class, 'toggleProductStatus'])->name('products.toggle-status');

    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/usuarios/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/usuarios/{user}/toggle-role', [AdminUserController::class, 'toggleRole'])->name('users.toggle-role');
});
