<?php

use App\Http\Controllers\Api\V1\Admin\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\Auth\TokenController;
use App\Http\Controllers\Api\V1\Admin\Products\ProductController;
use App\Http\Controllers\Api\V1\Admin\Products\ProductImageController;
use App\Http\Controllers\Api\V1\Admin\Rfqs\RfqController as AdminRfqController;
use App\Http\Controllers\Api\V1\PublicCatalog\GuestRfqController;
use App\Http\Controllers\Api\V1\PublicCatalog\PublicProductController;
use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Middleware\PreventAuthResponseCaching;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin/auth')->name('api.v1.admin.auth.')
    ->middleware(PreventAuthResponseCaching::class)->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:admin-login')->name('login');

        Route::middleware(['auth:sanctum', EnsureActiveAdmin::class, 'throttle:admin-api'])
            ->group(function (): void {
                Route::get('/me', [AuthController::class, 'me'])->name('me');
                Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
                Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
                Route::get('/tokens', [TokenController::class, 'index'])->name('tokens.index');
                Route::delete('/tokens/{token}', [TokenController::class, 'destroy'])
                    ->whereNumber('token')->name('tokens.destroy');
            });
    });

Route::bind('archivedProduct', fn (string $id): Product => Product::onlyTrashed()->findOrFail($id));

Route::prefix('v1')->name('api.v1.')
    ->group(function (): void {
        Route::get('/seafood', [PublicProductController::class, 'index'])
            ->middleware('throttle:60,1')->name('seafood.index');
        Route::get('/seafood/{slug}', [PublicProductController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')->middleware('throttle:60,1')->name('seafood.show');
        Route::post('/quote-requests', [GuestRfqController::class, 'store'])
            ->middleware('throttle:6,1')->name('quote-requests.store');
    });

Route::prefix('v1/admin')->name('api.v1.admin.')
    ->middleware([PreventAuthResponseCaching::class, 'auth:sanctum', EnsureActiveAdmin::class, 'throttle:admin-api'])
    ->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->whereNumber('product')->name('products.destroy');
        Route::post('/products/{archivedProduct}/restore', [ProductController::class, 'restore'])
            ->whereNumber('archivedProduct')->name('products.restore');
        Route::post('/product-images', [ProductImageController::class, 'store'])->name('product-images.store');
        Route::get('/rfqs', [AdminRfqController::class, 'index'])->name('rfqs.index');
        Route::get('/rfqs/{rfq}', [AdminRfqController::class, 'show'])->whereNumber('rfq')->name('rfqs.show');
        Route::patch('/rfqs/{rfq}', [AdminRfqController::class, 'update'])->whereNumber('rfq')->name('rfqs.update');
        Route::post('/rfqs/{rfq}/notes', [AdminRfqController::class, 'storeNote'])->whereNumber('rfq')->name('rfqs.notes.store');
    });
