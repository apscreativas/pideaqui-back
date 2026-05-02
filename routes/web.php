<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CancellationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryMethodController;
use App\Http\Controllers\DeliveryRangeController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LimitsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ModifierCatalogController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SpecialDateController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SuperAdmin\BillingSettingsController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\PlanController as SuperAdminPlanController;
use App\Http\Controllers\SuperAdmin\ProfileController as SuperAdminProfileController;
use App\Http\Controllers\SuperAdmin\RestaurantController as SuperAdminRestaurantController;
use App\Http\Controllers\SuperAdmin\StatisticsController as SuperAdminStatisticsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// ─── Stripe Webhook ──────────────────────────────────────────────────────────
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('stripe.webhook');

// ─── Auth (guest) ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:3,1')->name('register.store');
});

// ─── Auth routes (auth only — usuario puede salir incluso sin verificar) ──────
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])
        ->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// ─── Admin Restaurante — Todos los roles (autenticado + verificado + tenant) ──
Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {

    // Dashboard — ambos roles (controller filtra métricas por rol)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Pedidos — ambos roles (controller filtra por branch para operators)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/new-count', [OrderController::class, 'newCount'])->name('orders.new-count');
    Route::get('/orders/history', [OrderHistoryController::class, 'index'])->name('orders.history');
    Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
    Route::post('/orders/preview-delivery', [OrderController::class, 'previewDelivery'])->middleware('throttle:60,1')->name('orders.preview-delivery');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:30,1')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    Route::put('/orders/{order}/status', [OrderController::class, 'advanceStatus'])->name('orders.advance-status');
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    // Mapa — ambos roles (filtrado por branch en controller)

    // Mapa — ambos roles
    Route::get('/map', [MapController::class, 'index'])->name('map.index');

    // ─── POS — historial + venta vía modal (admin + operator) ────────────────
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/sales/{sale}', [PosController::class, 'salesShow'])->name('pos.sales.show');
    Route::post('/pos/sales', [PosController::class, 'store'])->middleware('throttle:60,1')->name('pos.sales.store');
    Route::put('/pos/sales/{sale}/cancel', [PosController::class, 'cancel'])->name('pos.sales.cancel');
    Route::put('/pos/sales/{sale}/pay', [PosController::class, 'closePay'])->name('pos.sales.pay');
    Route::post('/pos/sales/{sale}/payments', [PosController::class, 'storePayment'])->name('pos.sales.payments.store');
    Route::delete('/pos/sales/{sale}/payments/{payment}', [PosController::class, 'destroyPayment'])->name('pos.sales.payments.destroy');

    // Perfil — ambos roles (cada usuario edita su propio perfil)
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('settings.profile');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
});

// ─── Admin Restaurante — Solo admin principal ────────────────────────────────
Route::middleware(['auth', 'tenant', 'role:admin'])->group(function (): void {

    // Cancelaciones — solo admin
    Route::get('/cancellations', [CancellationController::class, 'index'])->name('cancellations.index');

    // Configuración
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

    Route::get('/settings/branding', [SettingsController::class, 'branding'])->name('settings.branding');
    Route::put('/settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');

    Route::get('/settings/schedules', [SettingsController::class, 'schedules'])->name('settings.schedules');
    Route::put('/settings/schedules', [SettingsController::class, 'updateSchedules'])->name('settings.schedules.update');

    Route::post('/settings/special-dates', [SpecialDateController::class, 'store'])->name('special-dates.store');
    Route::put('/settings/special-dates/{specialDate}', [SpecialDateController::class, 'update'])->name('special-dates.update');
    Route::delete('/settings/special-dates/{specialDate}', [SpecialDateController::class, 'destroy'])->name('special-dates.destroy');

    Route::get('/settings/limits', [LimitsController::class, 'index'])->name('settings.limits');

    // Suscripción — ambos roles
    Route::get('/settings/subscription', [SubscriptionController::class, 'index'])->name('settings.subscription');
    Route::post('/settings/subscription/initiate', [SubscriptionController::class, 'initiateSubscription'])->name('settings.subscription.initiate');
    Route::post('/settings/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('settings.subscription.checkout');
    Route::put('/settings/subscription/swap', [SubscriptionController::class, 'swap'])->name('settings.subscription.swap');
    Route::post('/settings/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('settings.subscription.cancel');
    Route::post('/settings/subscription/resume', [SubscriptionController::class, 'resume'])->name('settings.subscription.resume');
    Route::delete('/settings/subscription/pending', [SubscriptionController::class, 'cancelPendingDowngrade'])->name('settings.subscription.cancel-pending');
    Route::get('/settings/subscription/portal', [SubscriptionController::class, 'portal'])->name('settings.subscription.portal');

    // Usuarios del restaurante
    Route::get('/settings/users', [UserController::class, 'index'])->name('settings.users');
    Route::get('/settings/users/create', [UserController::class, 'create'])->name('settings.users.create');
    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::get('/settings/users/{user}/edit', [UserController::class, 'edit'])->name('settings.users.edit');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [UserController::class, 'destroy'])->name('settings.users.destroy');

    // Menú
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

    // Modifier Catalog
    Route::get('/modifier-catalog', [ModifierCatalogController::class, 'index'])->name('modifier-catalog.index');
    Route::post('/modifier-catalog', [ModifierCatalogController::class, 'store'])->name('modifier-catalog.store');
    Route::put('/modifier-catalog/{modifierGroupTemplate}', [ModifierCatalogController::class, 'update'])->name('modifier-catalog.update');
    Route::delete('/modifier-catalog/{modifierGroupTemplate}', [ModifierCatalogController::class, 'destroy'])->name('modifier-catalog.destroy');
    Route::patch('/modifier-catalog/{modifierGroupTemplate}/toggle', [ModifierCatalogController::class, 'toggle'])->name('modifier-catalog.toggle');
    Route::patch('/modifier-catalog/reorder', [ModifierCatalogController::class, 'reorder'])->name('modifier-catalog.reorder');

    // ─── Gastos (admin only) ────────────────────────────────────────────────
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::delete('/expenses/attachments/{attachment}', [ExpenseController::class, 'destroyAttachment'])->name('expenses.attachments.destroy');

    // Categorías y subcategorías de gastos
    Route::get('/settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('/settings/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('/settings/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::patch('/settings/expense-categories/{category}/toggle', [ExpenseCategoryController::class, 'toggle'])->name('expense-categories.toggle');
    Route::delete('/settings/expense-categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');
    Route::post('/settings/expense-categories/{category}/subcategories', [ExpenseCategoryController::class, 'storeSubcategory'])->name('expense-subcategories.store');
    Route::put('/settings/expense-subcategories/{subcategory}', [ExpenseCategoryController::class, 'updateSubcategory'])->name('expense-subcategories.update');
    Route::patch('/settings/expense-subcategories/{subcategory}/toggle', [ExpenseCategoryController::class, 'toggleSubcategory'])->name('expense-subcategories.toggle');
    Route::delete('/settings/expense-subcategories/{subcategory}', [ExpenseCategoryController::class, 'destroySubcategory'])->name('expense-subcategories.destroy');

    // Cupones
    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [CouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit');
    Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
    Route::patch('/coupons/{coupon}/toggle-active', [CouponController::class, 'toggleActive'])->name('coupons.toggle-active');

    // Promociones
    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::patch('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::patch('/promotions/reorder', [PromotionController::class, 'reorder'])->name('promotions.reorder');

    // Sucursales
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
        Route::put('/restaurants/{restaurant}/reset-password', [SuperAdminRestaurantController::class, 'resetAdminPassword'])->name('restaurants.reset-password');
        Route::post('/restaurants/{restaurant}/send-verification', [SuperAdminRestaurantController::class, 'sendVerification'])->name('restaurants.send-verification');
        Route::patch('/restaurants/{restaurant}/slug', [SuperAdminRestaurantController::class, 'renameSlug'])->name('restaurants.rename-slug');

        // Platform settings (global, not tenant-scoped)
        Route::get('/platform-settings', [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'index'])->name('platform-settings');
        Route::put('/platform-settings', [\App\Http\Controllers\SuperAdmin\PlatformSettingsController::class, 'update'])->name('platform-settings.update');

        // Planes
        Route::get('/plans', [SuperAdminPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [SuperAdminPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [SuperAdminPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [SuperAdminPlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [SuperAdminPlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{plan}/toggle', [SuperAdminPlanController::class, 'toggle'])->name('plans.toggle');
        Route::post('/plans/sync-stripe', [SuperAdminPlanController::class, 'syncStripe'])->name('plans.sync-stripe');

        // Billing Settings
        Route::get('/billing-settings', [BillingSettingsController::class, 'index'])->name('billing-settings');
        Route::put('/billing-settings', [BillingSettingsController::class, 'update'])->name('billing-settings.update');

        // Billing mode transitions
        Route::post('/restaurants/{restaurant}/start-grace', [SuperAdminRestaurantController::class, 'startGracePeriod'])->name('restaurants.start-grace');
        Route::post('/restaurants/{restaurant}/extend-grace', [SuperAdminRestaurantController::class, 'extendGrace'])->name('restaurants.extend-grace');

        Route::get('/profile', [SuperAdminProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [SuperAdminProfileController::class, 'update'])->name('profile.update');

        Route::get('/statistics', [SuperAdminStatisticsController::class, 'index'])->name('statistics');
    });
});
