<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\DeliveryRangeController;
use App\Http\Controllers\LimitsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\RestaurantController as SuperAdminRestaurantController;
use App\Http\Controllers\SuperAdmin\StatisticsController as SuperAdminStatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// ─── Admin Restaurante — Auth (guest) ────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

// ─── Admin Restaurante — Panel (autenticado + tenant) ────────────────────────
Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─── Pedidos ───────────────────────────────────────────────────────────────
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/new-count', [OrderController::class, 'newCount'])->name('orders.new-count');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}/status', [OrderController::class, 'advanceStatus'])->name('orders.advance-status');
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // ─── Cancelaciones ──────────────────────────────────────────────────────────
    Route::get('/cancellations', [CancellationController::class, 'index'])->name('cancellations.index');

    // ─── Mapa operativo ──────────────────────────────────────────────────────────
    Route::get('/map', [MapController::class, 'index'])->name('map.index');

    // ─── Configuración ─────────────────────────────────────────────────────────
    Route::get('/settings', fn () => redirect()->route('settings.general'))->name('settings.index');
    Route::get('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
    Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');

    Route::get('/settings/delivery-methods', [DeliveryMethodController::class, 'index'])->name('settings.delivery-methods');
    Route::put('/settings/delivery-methods', [DeliveryMethodController::class, 'update'])->name('settings.delivery-methods.update');

    Route::get('/settings/shipping-rates', [DeliveryRangeController::class, 'index'])->name('settings.shipping-rates');
    Route::post('/settings/shipping-rates', [DeliveryRangeController::class, 'store'])->name('settings.shipping-rates.store');
    Route::put('/settings/shipping-rates/{deliveryRange}', [DeliveryRangeController::class, 'update'])->name('settings.shipping-rates.update');
    Route::delete('/settings/shipping-rates/{deliveryRange}', [DeliveryRangeController::class, 'destroy'])->name('settings.shipping-rates.destroy');

    Route::get('/settings/payment-methods', [PaymentMethodController::class, 'index'])->name('settings.payment-methods');
    Route::put('/settings/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('settings.payment-methods.update');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');

    Route::get('/settings/schedules', [SettingsController::class, 'schedules'])->name('settings.schedules');
    Route::put('/settings/schedules', [SettingsController::class, 'updateSchedules'])->name('settings.schedules.update');

    Route::get('/settings/limits', [LimitsController::class, 'index'])->name('settings.limits');

    // ─── Menú ──────────────────────────────────────────────────────────────────
    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::patch('/menu/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::post('/menu/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/menu/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/menu/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::patch('/menu/products/reorder', [ProductController::class, 'reorder'])->name('products.reorder');
    Route::get('/menu/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/menu/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/menu/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/menu/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/menu/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/menu/products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

    // ─── Sucursales ────────────────────────────────────────────────────────────
    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    Route::patch('/branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
});

// ─── SuperAdmin ───────────────────────────────────────────────────────────────
Route::prefix('super')->name('super.')->group(function (): void {
    Route::middleware('auth:superadmin')->group(function (): void {
        Route::post('/logout', [LoginController::class, 'destroySuperAdmin'])->name('logout');

        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/restaurants', [SuperAdminRestaurantController::class, 'index'])->name('restaurants.index');
        Route::get('/restaurants/create', [SuperAdminRestaurantController::class, 'create'])->name('restaurants.create');
        Route::post('/restaurants', [SuperAdminRestaurantController::class, 'store'])->name('restaurants.store');
        Route::get('/restaurants/{restaurant}', [SuperAdminRestaurantController::class, 'show'])->name('restaurants.show');
        Route::put('/restaurants/{restaurant}/limits', [SuperAdminRestaurantController::class, 'updateLimits'])->name('restaurants.update-limits');
        Route::patch('/restaurants/{restaurant}/toggle', [SuperAdminRestaurantController::class, 'toggleActive'])->name('restaurants.toggle');
        Route::post('/restaurants/{restaurant}/regenerate-token', [SuperAdminRestaurantController::class, 'regenerateToken'])->name('restaurants.regenerate-token');
        Route::put('/restaurants/{restaurant}/reset-password', [SuperAdminRestaurantController::class, 'resetAdminPassword'])->name('restaurants.reset-password');

        Route::get('/profile', [SuperAdminProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [SuperAdminProfileController::class, 'update'])->name('profile.update');

        Route::get('/statistics', [SuperAdminStatisticsController::class, 'index'])->name('statistics');
    });
});
