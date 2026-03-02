<?php

use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RestaurantController;
use Illuminate\Support\Facades\Route;

// ─── API Pública — autenticada por access_token del restaurante ───────────────
Route::middleware('auth.restaurant')->group(function (): void {
    Route::get('/restaurant', [RestaurantController::class, 'show'])->name('api.restaurant');
    Route::get('/menu', [MenuController::class, 'index'])->name('api.menu');
    Route::get('/branches', [BranchController::class, 'index'])->name('api.branches');
    Route::post('/delivery/calculate', [DeliveryController::class, 'calculate'])->name('api.delivery.calculate');
    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
});
