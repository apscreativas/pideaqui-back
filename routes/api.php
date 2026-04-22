<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\SlugCheckController;
use Illuminate\Support\Facades\Route;

// ─── Live slug availability check — used by self-signup and SuperAdmin ────────
// Rate limit intentionally generous: the UI fires one request per keystroke
// (debounced), so legitimate form typing must not hit the ceiling. The
// endpoint is a single indexed lookup, so throughput is cheap.
Route::get('/slug-check', [SlugCheckController::class, 'check'])
    ->middleware('throttle:120,1')
    ->name('api.slug-check');

// ─── API Pública — tenant resuelto por slug en la URL ─────────────────────────
// El middleware `tenant.slug` inyecta el Restaurant en los attributes del
// request, así los controllers permanecen agnósticos de cómo se identificó
// al tenant. Rate limits son por IP + ruta; endpoints de escritura (orders,
// coupons, delivery) tienen cuotas más estrictas que las lecturas.
//
// Límites calibrados para UX real:
//  - Lecturas (GET): 120/min — la SPA hace 3-4 fetches en boot + re-fetch
//    en visibilitychange; 120/min deja margen para múltiples reloads por
//    minuto sin afectar a usuarios legítimos.
//  - POST /delivery/calculate: 30/min — cada movimiento del pin o cambio
//    de dirección dispara un cálculo; el frontend debe debouncearlo.
//  - POST /coupons/validate: 20/min — anti-spam razonable; un user puede
//    probar varios códigos sin bloquearse.
//  - POST /orders: 30/min — defensa anti-spam en escritura.
Route::prefix('public/{slug}')
    ->middleware(['tenant.slug', 'throttle:120,1'])
    ->group(function (): void {
        Route::get('/restaurant', [RestaurantController::class, 'show'])->name('api.public.restaurant');
        Route::get('/menu', [MenuController::class, 'index'])->name('api.public.menu');
        Route::get('/branches', [BranchController::class, 'index'])->name('api.public.branches');
        Route::post('/delivery/calculate', [DeliveryController::class, 'calculate'])->middleware('throttle:30,1')->name('api.public.delivery.calculate');
        Route::post('/coupons/validate', [CouponController::class, 'validate'])->middleware('throttle:20,1')->name('api.public.coupons.validate');
        Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('api.public.orders.store');
    });
