<?php

use App\Http\Controllers\Api\V1\Admin\Auth\AuthController;
use App\Http\Controllers\Api\V1\Admin\Auth\TokenController;
use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Middleware\PreventAuthResponseCaching;
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
