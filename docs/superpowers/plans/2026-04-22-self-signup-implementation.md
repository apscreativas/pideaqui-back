# Self-signup público de restaurantes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Habilitar registro público de restaurantes con plan de gracia auto-asignado, reutilizando la lógica de provisioning vía un servicio compartido. Email verification solo obligatoria para self-signup.

**Architecture:** Extract inline logic from `SuperAdmin/RestaurantController@store` into `RestaurantProvisioningService`. Public `/register` and SuperAdmin manual creation both delegate to it. Differentiation via `restaurants.signup_source` column. Users created by SuperAdmin pre-verified; self-signup users require email verification.

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL 18, Inertia.js v2, Vue 3, Tailwind CSS v4, Laravel Sail, PHPUnit.

**Not a git repo:** "Checkpoint" markers replace commits. User decides when to snapshot changes.

**Existing state confirmed:**
- `DB::transaction` ALREADY wraps `store()` (verified at `RestaurantController.php:65`) — Phase 0 adds a defensive rollback test to protect this.
- `slug` UNIQUE index exists (`2026_02_26_204346_create_restaurants_table.php:14`).
- `access_token` generated via `hash('sha256', Str::random(40))` returning 64-char string — existing test asserts length (`SuperAdminTest.php:140`).
- `generateUniqueSlug()` helper exists (`RestaurantController.php:356-370`).
- `Plan::gracePlan()` exists (`Plan.php:37`).
- `BillingSetting::getInt('initial_grace_period_days', 14)` is the config source.
- `UserFactory` default sets `email_verified_at = now()` (Laravel standard) → existing tests unaffected when we add `MustVerifyEmail`.
- Existing `test_superadmin_can_create_restaurant` (`SuperAdminTest.php:115-150`) passes `'password' => 'password123'` — this password does NOT meet `Password::defaults()` strength rules. **Decision:** SuperAdmin form keeps `min:8,confirmed` (lax); self-signup form uses `Password::defaults()` (strict). No regression.

---

## File Structure

**New files (13):**

| Path | Responsibility |
|---|---|
| `app/Services/Onboarding/RestaurantProvisioningService.php` | Single orchestrator of restaurant+user+payment_methods+audit creation |
| `app/Services/Onboarding/Dto/ProvisionRestaurantData.php` | Immutable input for the service |
| `app/Http/Controllers/Auth/RegisterController.php` | Public `/register` GET+POST |
| `app/Http/Controllers/Auth/VerifyEmailController.php` | `/email/verify*` endpoints (Laravel standard) |
| `app/Http/Requests/Auth/RegisterRestaurantRequest.php` | Public signup validation (strict) |
| `resources/js/Pages/Auth/Register.vue` | Public signup form |
| `resources/js/Pages/Auth/VerifyEmail.vue` | "Check your email" screen |
| `resources/js/Components/GracePeriodBanner.vue` | Dashboard banner with days remaining |
| `database/migrations/2026_04_22_090000_add_signup_source_to_restaurants_table.php` | `signup_source` column |
| `database/migrations/2026_04_22_091000_backfill_email_verified_at_on_users.php` | Grandfather existing users |
| `tests/Unit/RestaurantProvisioningServiceTest.php` | Unit tests for the service |
| `tests/Feature/Auth/RegisterTest.php` | Feature tests for `/register` |
| `tests/Feature/Auth/EmailVerificationTest.php` | Feature tests for verification flow |

**Modified files (10):**

| Path | Change |
|---|---|
| `app/Http/Controllers/SuperAdmin/RestaurantController.php` | `store()` becomes thin wrapper calling service; adds `sendVerification` action |
| `app/Models/User.php` | `implements MustVerifyEmail` |
| `app/Models/Restaurant.php` | Add `signup_source` to `$fillable` |
| `app/Models/BillingAudit.php` | Docblock allows `actor_type='self_signup'`, new actions `verification_email_sent_manually` |
| `app/Http/Controllers/Auth/LoginController.php` | Redirect unverified user to `/email/verify` |
| `routes/web.php` | Add guest `/register`, auth `/email/verify*`, superadmin `send-verification` |
| `resources/js/Pages/Auth/Login.vue` | Link "Crear cuenta" |
| `resources/js/Pages/Dashboard.vue` | Mount `<GracePeriodBanner>` |
| `resources/js/Pages/SuperAdmin/Restaurants/Show.vue` | Button "Enviar correo de verificación" |
| `database/factories/RestaurantFactory.php` | States `grace()` and `selfSignup()` |
| `tests/Feature/SuperAdminTest.php` | Assertions for `signup_source` and `email_verified_at` |

---

## Phase 0 — Defensive regression test (no feature change)

**Goal:** Add a rollback test to protect the existing `DB::transaction` from future regressions. Confirm the full suite still passes.

### Task 0.1: Add rollback test for SuperAdmin restaurant creation

**Files:**
- Test: `tests/Feature/SuperAdminTest.php` (add test method)

- [ ] **Step 1: Write the failing test**

Edit `tests/Feature/SuperAdminTest.php`, add this test method after `test_create_restaurant_fails_with_duplicate_admin_email` (around line 196):

```php
public function test_restaurant_creation_rolls_back_when_billing_audit_fails(): void
{
    $superAdmin = $this->createSuperAdmin();

    // Force BillingAudit::log() to throw by deleting the billing_audits table mid-request.
    \Illuminate\Support\Facades\Schema::drop('billing_audits');

    try {
        $this->actingAs($superAdmin, 'superadmin')->post(route('super.restaurants.store'), [
            'name' => 'Rollback Test Restaurant',
            'admin_name' => 'Rollback Admin',
            'admin_email' => 'rollback@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'billing_mode' => 'grace',
        ]);
    } catch (\Throwable $e) {
        // Expected: the transaction throws because audit log can't persist.
    }

    $this->assertDatabaseMissing('restaurants', ['name' => 'Rollback Test Restaurant']);
    $this->assertDatabaseMissing('users', ['email' => 'rollback@test.com']);
}
```

- [ ] **Step 2: Run test to verify it PASSES (transaction already in place)**

Run:
```bash
./vendor/bin/sail artisan test --compact --filter=test_restaurant_creation_rolls_back_when_billing_audit_fails
```

Expected: PASS. (If it fails, there is a real bug — the transaction is not wrapping everything. Investigate before continuing.)

- [ ] **Step 3: Run full SuperAdmin suite**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/SuperAdminTest.php
```

Expected: All tests pass (15+ methods).

- [ ] **Step 4: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 5: Checkpoint**

Phase 0 complete. Snapshot is optional; feature changes start in Phase 1.

---

## Phase 1 — Extract `RestaurantProvisioningService`

**Goal:** Move the inline logic of `SuperAdmin/RestaurantController@store` into a reusable service. Behavior remains byte-identical; all existing tests pass unchanged.

### Task 1.1: Create the DTO

**Files:**
- Create: `app/Services/Onboarding/Dto/ProvisionRestaurantData.php`

- [ ] **Step 1: Create the DTO file**

```php
<?php

namespace App\Services\Onboarding\Dto;

use Carbon\Carbon;

final readonly class ProvisionRestaurantData
{
    public function __construct(
        public string $source,                  // 'self_signup' | 'super_admin'
        public string $restaurantName,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,           // plaintext; User 'password' cast hashes
        public string $billingMode,             // 'grace' | 'manual'
        public ?int $ordersLimit = null,        // only when billingMode='manual'
        public ?int $maxBranches = null,        // only when billingMode='manual'
        public ?Carbon $ordersLimitStart = null,
        public ?Carbon $ordersLimitEnd = null,
        public ?int $actorId = null,            // superadmin id when source='super_admin'
        public ?string $ipAddress = null,
    ) {}
}
```

- [ ] **Step 2: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 1.2: Write service unit tests (test-first)

**Files:**
- Test: `tests/Unit/RestaurantProvisioningServiceTest.php` (create)

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Unit;

use App\Models\BillingAudit;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\Onboarding\RestaurantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private RestaurantProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RestaurantProvisioningService::class);
        // Ensure grace plan exists (seeded in RefreshDatabase via seeders).
        if (! Plan::gracePlan()) {
            Plan::factory()->create([
                'name' => 'Gracia',
                'is_default_grace' => true,
                'orders_limit' => 50,
                'max_branches' => 1,
            ]);
        }
    }

    private function graceDto(array $overrides = []): ProvisionRestaurantData
    {
        return new ProvisionRestaurantData(
            source: $overrides['source'] ?? 'super_admin',
            restaurantName: $overrides['restaurantName'] ?? 'Test Restaurant',
            adminName: $overrides['adminName'] ?? 'Admin Test',
            adminEmail: $overrides['adminEmail'] ?? 'admin@test.com',
            adminPassword: $overrides['adminPassword'] ?? 'password123',
            billingMode: $overrides['billingMode'] ?? 'grace',
            actorId: $overrides['actorId'] ?? 1,
            ipAddress: $overrides['ipAddress'] ?? '127.0.0.1',
        );
    }

    public function test_provisions_grace_restaurant_with_correct_defaults(): void
    {
        $restaurant = $this->service->provision($this->graceDto());

        $this->assertEquals('subscription', $restaurant->billing_mode);
        $this->assertEquals('grace_period', $restaurant->status);
        $this->assertNotNull($restaurant->grace_period_ends_at);
        $this->assertEquals(50, $restaurant->orders_limit);
        $this->assertEquals(1, $restaurant->max_branches);
        $this->assertTrue($restaurant->is_active);
        $this->assertFalse($restaurant->allows_delivery);
        $this->assertTrue($restaurant->allows_pickup);
        $this->assertFalse($restaurant->allows_dine_in);
    }

    public function test_creates_admin_user_with_role_and_restaurant_id(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['adminEmail' => 'new@test.com']));

        $user = User::where('email', 'new@test.com')->firstOrFail();

        $this->assertEquals('admin', $user->role);
        $this->assertEquals($restaurant->id, $user->restaurant_id);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    public function test_seeds_three_payment_methods(): void
    {
        $restaurant = $this->service->provision($this->graceDto());

        $this->assertEquals(3, PaymentMethod::where('restaurant_id', $restaurant->id)->count());
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'cash', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'terminal', 'is_active' => false,
        ]);
        $this->assertDatabaseHas('payment_methods', [
            'restaurant_id' => $restaurant->id, 'type' => 'transfer', 'is_active' => false,
        ]);
    }

    public function test_logs_billing_audit_with_correct_actor_type(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'super_admin']));

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'super_admin',
        ]);
    }

    public function test_logs_self_signup_actor_type_when_source_self_signup(): void
    {
        $restaurant = $this->service->provision($this->graceDto(['source' => 'self_signup']));

        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'self_signup',
        ]);
    }

    public function test_provisions_manual_restaurant_with_provided_limits(): void
    {
        $dto = new ProvisionRestaurantData(
            source: 'super_admin',
            restaurantName: 'Manual Test',
            adminName: 'Admin',
            adminEmail: 'manual@test.com',
            adminPassword: 'password123',
            billingMode: 'manual',
            ordersLimit: 999,
            maxBranches: 7,
            ordersLimitStart: now()->startOfMonth(),
            ordersLimitEnd: now()->endOfMonth(),
            actorId: 1,
            ipAddress: '127.0.0.1',
        );

        $restaurant = $this->service->provision($dto);

        $this->assertEquals('manual', $restaurant->billing_mode);
        $this->assertEquals('active', $restaurant->status);
        $this->assertNull($restaurant->plan_id);
        $this->assertEquals(999, $restaurant->orders_limit);
        $this->assertEquals(7, $restaurant->max_branches);
    }

    public function test_generates_unique_slug_on_name_collision(): void
    {
        Restaurant::factory()->create(['name' => 'Colliding', 'slug' => 'colliding']);

        $restaurant = $this->service->provision($this->graceDto([
            'restaurantName' => 'Colliding',
            'adminEmail' => 'another@test.com',
        ]));

        $this->assertNotEquals('colliding', $restaurant->slug);
        $this->assertStringStartsWith('colliding-', $restaurant->slug);
    }

    public function test_generates_64_char_access_token(): void
    {
        $restaurant = $this->service->provision($this->graceDto());

        $this->assertNotNull($restaurant->access_token);
        $this->assertEquals(64, strlen($restaurant->access_token));
    }

    public function test_rolls_back_when_audit_fails(): void
    {
        \Illuminate\Support\Facades\Schema::drop('billing_audits');

        try {
            $this->service->provision($this->graceDto(['adminEmail' => 'rollback@test.com']));
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@test.com']);
        $this->assertDatabaseMissing('restaurants', ['name' => 'Test Restaurant']);
    }
}
```

- [ ] **Step 2: Run and verify tests FAIL**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/RestaurantProvisioningServiceTest.php
```

Expected: All tests fail with class-not-found error for `RestaurantProvisioningService`.

### Task 1.3: Implement `RestaurantProvisioningService`

**Files:**
- Create: `app/Services/Onboarding/RestaurantProvisioningService.php`

- [ ] **Step 1: Create the service file**

```php
<?php

namespace App\Services\Onboarding;

use App\Models\BillingAudit;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Support\BillingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RestaurantProvisioningService
{
    public function provision(ProvisionRestaurantData $data): Restaurant
    {
        return DB::transaction(function () use ($data): Restaurant {
            $restaurant = $this->createRestaurant($data);
            $this->createAdminUser($restaurant, $data);
            $this->seedPaymentMethods($restaurant->id);
            $this->logCreation($restaurant, $data);

            return $restaurant;
        });
    }

    private function createRestaurant(ProvisionRestaurantData $data): Restaurant
    {
        $slug = $this->generateUniqueSlug($data->restaurantName);
        $token = $this->generateAccessToken();

        if ($data->billingMode === 'manual') {
            return Restaurant::create([
                'name' => $data->restaurantName,
                'slug' => $slug,
                'access_token' => $token,
                'is_active' => true,
                'billing_mode' => 'manual',
                'plan_id' => null,
                'status' => 'active',
                'orders_limit' => $data->ordersLimit,
                'orders_limit_start' => $data->ordersLimitStart,
                'orders_limit_end' => $data->ordersLimitEnd,
                'max_branches' => $data->maxBranches,
                'allows_delivery' => false,
                'allows_pickup' => true,
                'allows_dine_in' => false,
            ]);
        }

        $gracePlan = Plan::gracePlan();
        $graceDays = BillingSetting::getInt('initial_grace_period_days', 14);

        return Restaurant::create([
            'name' => $data->restaurantName,
            'slug' => $slug,
            'access_token' => $token,
            'is_active' => true,
            'billing_mode' => 'subscription',
            'plan_id' => $gracePlan?->id,
            'status' => 'grace_period',
            'grace_period_ends_at' => now()->addDays($graceDays),
            'orders_limit' => $gracePlan?->orders_limit ?? 50,
            'orders_limit_start' => now()->startOfMonth(),
            'orders_limit_end' => now()->endOfMonth(),
            'max_branches' => $gracePlan?->max_branches ?? 1,
            'allows_delivery' => false,
            'allows_pickup' => true,
            'allows_dine_in' => false,
        ]);
    }

    private function createAdminUser(Restaurant $restaurant, ProvisionRestaurantData $data): User
    {
        $user = new User([
            'name' => $data->adminName,
            'email' => $data->adminEmail,
            'password' => $data->adminPassword,
        ]);
        $user->role = 'admin';
        $user->restaurant_id = $restaurant->id;
        $user->save();

        return $user;
    }

    private function seedPaymentMethods(int $restaurantId): void
    {
        PaymentMethod::insert([
            ['restaurant_id' => $restaurantId, 'type' => 'cash', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => $restaurantId, 'type' => 'terminal', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['restaurant_id' => $restaurantId, 'type' => 'transfer', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function logCreation(Restaurant $restaurant, ProvisionRestaurantData $data): void
    {
        BillingAudit::log(
            action: 'restaurant_created',
            restaurantId: $restaurant->id,
            actorType: $data->source,
            actorId: $data->actorId,
            payload: [
                'billing_mode' => $data->billingMode,
                'plan' => $restaurant->plan?->name ?? 'manual',
            ],
            ipAddress: $data->ipAddress,
        );
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Restaurant::query()->withoutGlobalScope(TenantScope::class)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function generateAccessToken(): string
    {
        return hash('sha256', Str::random(40));
    }
}
```

- [ ] **Step 2: Run unit tests to verify they pass**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/RestaurantProvisioningServiceTest.php
```

Expected: All 9 tests pass.

- [ ] **Step 3: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 1.4: Refactor `SuperAdmin/RestaurantController@store` to use the service

**Files:**
- Modify: `app/Http/Controllers/SuperAdmin/RestaurantController.php:59-140`

- [ ] **Step 1: Replace the `store()` method**

Find the `store(CreateRestaurantRequest $request)` method (starts at line 59 in the original). Replace its body with:

```php
public function store(
    CreateRestaurantRequest $request,
    \App\Services\Onboarding\RestaurantProvisioningService $provisioning,
): RedirectResponse {
    $data = $request->validated();

    $dto = new \App\Services\Onboarding\Dto\ProvisionRestaurantData(
        source: 'super_admin',
        restaurantName: $data['name'],
        adminName: $data['admin_name'],
        adminEmail: $data['admin_email'],
        adminPassword: $data['password'],
        billingMode: $data['billing_mode'] ?? 'grace',
        ordersLimit: $data['orders_limit'] ?? null,
        maxBranches: $data['max_branches'] ?? null,
        ordersLimitStart: isset($data['orders_limit_start']) ? \Carbon\Carbon::parse($data['orders_limit_start']) : null,
        ordersLimitEnd: isset($data['orders_limit_end']) ? \Carbon\Carbon::parse($data['orders_limit_end']) : null,
        actorId: $request->user('superadmin')->id,
        ipAddress: $request->ip(),
    );

    $restaurant = $provisioning->provision($dto);

    return redirect()->route('super.restaurants.show', $restaurant)
        ->with('success', 'Restaurante creado exitosamente.');
}
```

- [ ] **Step 2: Remove now-dead private helper `generateUniqueSlug`**

Delete the private method `generateUniqueSlug()` at lines 356-370 (check current line numbers; it's at the bottom of the class). The service owns this now.

- [ ] **Step 3: Clean up unused imports**

If `Str`, `DB`, `PaymentMethod`, `BillingAudit`, `BillingSetting`, `Plan`, `User` imports at top of `RestaurantController.php` are no longer used by any remaining method, remove them. Verify first by searching:

```bash
grep -n "Str::\|DB::transaction\|PaymentMethod::\|BillingAudit::\|BillingSetting::\|Plan::\|new User(" app/Http/Controllers/SuperAdmin/RestaurantController.php
```

Leave any import that still has a match. Remove imports with zero matches.

- [ ] **Step 4: Run SuperAdmin feature tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/SuperAdminTest.php
```

Expected: All tests still pass (no behavior change).

- [ ] **Step 5: Run full test suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All tests green.

- [ ] **Step 6: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 7: Checkpoint**

Phase 1 complete. Behavior identical; logic relocated. Safe to snapshot.

---

## Phase 2 — Add `signup_source` column

**Goal:** Persist origin of each restaurant creation (`super_admin` | `self_signup`). Backfill historical records to `super_admin`.

### Task 2.1: Create migration

**Files:**
- Create: `database/migrations/2026_04_22_090000_add_signup_source_to_restaurants_table.php`

- [ ] **Step 1: Generate the migration**

```bash
./vendor/bin/sail artisan make:migration add_signup_source_to_restaurants_table --table=restaurants --no-interaction
```

- [ ] **Step 2: Edit the generated file**

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('signup_source', 32)->nullable()->after('billing_mode');
            $table->index('signup_source');
        });

        DB::statement("UPDATE restaurants SET signup_source = 'super_admin' WHERE signup_source IS NULL");
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['signup_source']);
            $table->dropColumn('signup_source');
        });
    }
};
```

- [ ] **Step 3: Run migration**

```bash
./vendor/bin/sail artisan migrate --no-interaction
```

Expected: Migration runs successfully.

### Task 2.2: Add `signup_source` to Restaurant fillable

**Files:**
- Modify: `app/Models/Restaurant.php` (fillable array)

- [ ] **Step 1: Add to `$fillable`**

Open `app/Models/Restaurant.php`. Find the `$fillable` array (lines 20-46). Add `'signup_source'` to the array:

```php
protected $fillable = [
    // ... existing entries
    'pending_billing_cycle',
    'signup_source',  // NEW
];
```

- [ ] **Step 2: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 2.3: Service sets `signup_source` from DTO

**Files:**
- Modify: `app/Services/Onboarding/RestaurantProvisioningService.php`
- Test: `tests/Unit/RestaurantProvisioningServiceTest.php`

- [ ] **Step 1: Add failing test**

Add to `RestaurantProvisioningServiceTest.php`:

```php
public function test_sets_signup_source_super_admin(): void
{
    $restaurant = $this->service->provision($this->graceDto(['source' => 'super_admin']));
    $this->assertEquals('super_admin', $restaurant->signup_source);
}

public function test_sets_signup_source_self_signup(): void
{
    $restaurant = $this->service->provision($this->graceDto(['source' => 'self_signup']));
    $this->assertEquals('self_signup', $restaurant->signup_source);
}
```

- [ ] **Step 2: Run test — verify failure**

```bash
./vendor/bin/sail artisan test --compact --filter=test_sets_signup_source
```

Expected: Both fail (column not set by service).

- [ ] **Step 3: Update `createRestaurant()` to include `signup_source`**

In `RestaurantProvisioningService::createRestaurant()`, add `'signup_source' => $data->source` to BOTH the manual and the grace `Restaurant::create([...])` calls.

Example (grace branch):
```php
return Restaurant::create([
    'name' => $data->restaurantName,
    // ... existing fields
    'allows_dine_in' => false,
    'signup_source' => $data->source,  // NEW
]);
```

Do the same in the manual branch.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/RestaurantProvisioningServiceTest.php
```

Expected: All pass.

### Task 2.4: Update SuperAdmin test to assert `signup_source`

**Files:**
- Modify: `tests/Feature/SuperAdminTest.php`

- [ ] **Step 1: Enhance existing test**

In `test_superadmin_can_create_restaurant()`, after the existing `assertDatabaseHas('restaurants', ...)` call, add:

```php
$this->assertEquals('super_admin', $restaurant->fresh()->signup_source);
```

- [ ] **Step 2: Run SuperAdmin tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/SuperAdminTest.php
```

Expected: Pass.

### Task 2.5: Add `selfSignup` state to RestaurantFactory (for later tests)

**Files:**
- Modify: `database/factories/RestaurantFactory.php`

- [ ] **Step 1: Add factory state**

At the end of `RestaurantFactory` class, add:

```php
public function selfSignup(): static
{
    return $this->state(fn () => ['signup_source' => 'self_signup']);
}

public function grace(): static
{
    return $this->state(fn () => [
        'billing_mode' => 'subscription',
        'status' => 'grace_period',
        'grace_period_ends_at' => now()->addDays(14),
        'orders_limit' => 50,
        'max_branches' => 1,
    ]);
}
```

- [ ] **Step 2: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 3: Run full suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All green.

- [ ] **Step 4: Checkpoint**

Phase 2 complete.

---

## Phase 3 — Email verification (self-signup only)

**Goal:** Enable `MustVerifyEmail`. Backfill existing users. Pre-verify SuperAdmin-created users. Self-signup users must verify.

### Task 3.1: Backfill migration for existing users

**Files:**
- Create: `database/migrations/2026_04_22_091000_backfill_email_verified_at_on_users.php`

- [ ] **Step 1: Generate migration**

```bash
./vendor/bin/sail artisan make:migration backfill_email_verified_at_on_users --no-interaction
```

- [ ] **Step 2: Edit file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL'
        );
    }

    public function down(): void
    {
        // No-op: we don't un-backfill.
    }
};
```

- [ ] **Step 3: Do NOT run migration yet**

We'll run it together with Task 3.2 so that `MustVerifyEmail` takes effect only after backfill. Proceed to 3.2.

### Task 3.2: User implements `MustVerifyEmail` + service pre-verifies super_admin

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Services/Onboarding/RestaurantProvisioningService.php`
- Test: `tests/Unit/RestaurantProvisioningServiceTest.php`

- [ ] **Step 1: Add failing tests to `RestaurantProvisioningServiceTest.php`**

```php
public function test_super_admin_source_pre_verifies_email(): void
{
    $restaurant = $this->service->provision($this->graceDto([
        'source' => 'super_admin',
        'adminEmail' => 'preverified@test.com',
    ]));

    $user = User::where('email', 'preverified@test.com')->firstOrFail();
    $this->assertNotNull($user->email_verified_at);
    $this->assertTrue($user->hasVerifiedEmail());
}

public function test_self_signup_source_does_not_pre_verify(): void
{
    $restaurant = $this->service->provision($this->graceDto([
        'source' => 'self_signup',
        'adminEmail' => 'unverified@test.com',
    ]));

    $user = User::where('email', 'unverified@test.com')->firstOrFail();
    $this->assertNull($user->email_verified_at);
    $this->assertFalse($user->hasVerifiedEmail());
}
```

- [ ] **Step 2: Run tests — verify failure**

```bash
./vendor/bin/sail artisan test --compact --filter=test_super_admin_source_pre_verifies_email
./vendor/bin/sail artisan test --compact --filter=test_self_signup_source_does_not_pre_verify
```

Expected: Both fail (method `hasVerifiedEmail()` not on User yet).

- [ ] **Step 3: Modify User model to implement MustVerifyEmail**

In `app/Models/User.php`:

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    // ... existing body unchanged
}
```

Laravel's base `Authenticatable` already uses the `MustVerifyEmail` trait via `Illuminate\Auth\MustVerifyEmail` — actually the trait needs explicit `use`. Verify by checking `app/Models/User.php`. If not using the trait, add:

```php
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;
    // ...
}
```

- [ ] **Step 4: Modify service to pre-verify SuperAdmin users**

In `app/Services/Onboarding/RestaurantProvisioningService.php`, change `createAdminUser()`:

```php
private function createAdminUser(Restaurant $restaurant, ProvisionRestaurantData $data): User
{
    $user = new User([
        'name' => $data->adminName,
        'email' => $data->adminEmail,
        'password' => $data->adminPassword,
    ]);
    $user->role = 'admin';
    $user->restaurant_id = $restaurant->id;

    if ($data->source === 'super_admin') {
        $user->email_verified_at = now();
    }

    $user->save();

    return $user;
}
```

- [ ] **Step 5: Run migrations (backfill + any pending)**

```bash
./vendor/bin/sail artisan migrate --no-interaction
```

Expected: `backfill_email_verified_at_on_users` runs.

- [ ] **Step 6: Run unit tests**

```bash
./vendor/bin/sail artisan test --compact tests/Unit/RestaurantProvisioningServiceTest.php
```

Expected: All pass.

- [ ] **Step 7: Run full suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All green. `UserFactory` default `email_verified_at = now()` keeps existing tests unaffected.

- [ ] **Step 8: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 3.3: Add VerifyEmailController and routes

**Files:**
- Create: `app/Http/Controllers/Auth/VerifyEmailController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/EmailVerificationTest.php` (create)

- [ ] **Step 1: Create the test file first**

Create `tests/Feature/Auth/EmailVerificationTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedUser(): User
    {
        $restaurant = Restaurant::factory()->grace()->selfSignup()->create();
        $user = User::factory()->unverified()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
        ]);

        return $user;
    }

    public function test_unverified_user_sees_verify_notice_page(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertStatus(200);
    }

    public function test_signed_link_verifies_email(): void
    {
        Event::fake();
        $user = $this->unverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect(route('dashboard').'?verified=1');
        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_invalid_hash_rejects_verification(): void
    {
        $user = $this->unverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'wrong-hash'],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_resend_notification_is_throttled(): void
    {
        $user = $this->unverifiedUser();

        for ($i = 0; $i < 7; $i++) {
            $response = $this->actingAs($user)->post(route('verification.send'));
        }

        $response->assertStatus(429);
    }

    public function test_backfilled_old_users_are_not_blocked(): void
    {
        // Simulates an admin who existed before the verification requirement.
        $restaurant = Restaurant::factory()->create(['signup_source' => 'super_admin']);
        $oldUser = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'role' => 'admin',
            'email_verified_at' => now()->subYear(),
        ]);

        $response = $this->actingAs($oldUser)->get(route('dashboard'));

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run tests — verify all fail**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php
```

Expected: Route-not-found errors.

- [ ] **Step 3: Create VerifyEmailController**

Create `app/Http/Controllers/Auth/VerifyEmailController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController extends Controller
{
    public function notice(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard').'?verified=1';
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard').'?verified=1');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
```

- [ ] **Step 4: Add routes**

Edit `routes/web.php`. In the `auth` middleware group (the one containing `/dashboard`), add inside the group:

```php
Route::get('/email/verify', [VerifyEmailController::class, 'notice'])
    ->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
    ->middleware('throttle:6,1')
    ->name('verification.send');
```

Add at the top of `routes/web.php`:

```php
use App\Http\Controllers\Auth\VerifyEmailController;
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php
```

Expected: 4 of 5 pass. `test_unverified_user_sees_verify_notice_page` needs Inertia page to exist — create a minimal page in next task.

### Task 3.4: Create `VerifyEmail.vue` page

**Files:**
- Create: `resources/js/Pages/Auth/VerifyEmail.vue`

- [ ] **Step 1: Create the Vue page**

```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    status: String,
})

const form = useForm({})

const verificationLinkSent = computed(() => props.status === 'verification-link-sent')

function submit() {
    form.post(route('verification.send'))
}

function logout() {
    form.post(route('logout'))
}
</script>

<template>
    <Head title="Verifica tu correo" />

    <div class="min-h-screen flex items-center justify-center bg-[#FAFAFA] px-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-sm p-8 text-center">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#FF5722]/10 mb-4">
                <span class="material-symbols-outlined text-[#FF5722] text-3xl">mark_email_read</span>
            </div>

            <h1 class="text-xl font-semibold text-neutral-900 mb-2">Verifica tu correo</h1>

            <p class="text-sm text-neutral-600 leading-relaxed mb-6">
                Enviamos un enlace de verificación al correo que registraste.
                Haz clic en el enlace para activar tu cuenta.
            </p>

            <div v-if="verificationLinkSent" class="bg-green-50 text-green-800 text-sm rounded-lg p-3 mb-4">
                Enlace reenviado. Revisa tu bandeja de entrada.
            </div>

            <button
                type="button"
                :disabled="form.processing"
                class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold py-3 rounded-xl transition disabled:opacity-60"
                @click="submit"
            >
                Reenviar correo
            </button>

            <button
                type="button"
                class="mt-3 text-sm text-neutral-500 hover:text-neutral-700 underline"
                @click="logout"
            >
                Cerrar sesión
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Run tests again**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php
```

Expected: All 5 tests pass.

### Task 3.5: Apply `verified` middleware + Login redirect

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`

- [ ] **Step 1: Add `verified` middleware to admin routes**

In `routes/web.php`, find the authenticated admin group (currently `['auth','tenant']`). Change to `['auth', 'verified', 'tenant']`.

Leave the `/email/verify*` routes INSIDE this group but WITHOUT `verified` — they need `auth` but not `verified` (otherwise users can't reach the verify page). Solution: split into sub-groups.

Current structure (simplified):
```php
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', ...);
    // ... many routes
});
```

New structure:
```php
Route::middleware('auth')->group(function () {
    // verification routes — no 'verified' middleware required
    Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/dashboard', ...);
    // ... all existing admin routes
});
```

- [ ] **Step 2: Add redirect from LoginController for unverified users**

Open `app/Http/Controllers/Auth/LoginController.php`. Find the admin (guard `web`) success branch (around line 47). After a successful login check `hasVerifiedEmail`:

```php
if (Auth::guard('web')->attempt(...)) {
    $user = Auth::guard('web')->user();

    if (! $user->restaurant_id) {
        Auth::guard('web')->logout();
        // ... existing error
    }

    if (! $user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    return redirect()->intended(route('dashboard'));
}
```

(Adapt to actual structure of `LoginController`.)

- [ ] **Step 3: Add test for unverified self-signup blocked from dashboard**

Add to `EmailVerificationTest.php`:

```php
public function test_self_signup_user_cannot_access_dashboard_until_verified(): void
{
    $user = $this->unverifiedUser();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
}

public function test_super_admin_created_user_accesses_dashboard_directly(): void
{
    $restaurant = Restaurant::factory()->create(['signup_source' => 'super_admin']);
    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php
./vendor/bin/sail artisan test --compact tests/Feature/AuthTest.php
./vendor/bin/sail artisan test --compact tests/Feature/SuperAdminTest.php
```

Expected: All green.

- [ ] **Step 5: Run full suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All green. If any test fails because it uses `actingAs($unverifiedUser)`, either mark the user verified or use an explicitly-verified fixture.

- [ ] **Step 6: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 3.6: SuperAdmin "Send verification" escape hatch

**Files:**
- Modify: `app/Http/Controllers/SuperAdmin/RestaurantController.php` (new method)
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/SuperAdmin/Restaurants/Show.vue`
- Test: `tests/Feature/SuperAdminTest.php`

- [ ] **Step 1: Add failing test**

Add to `tests/Feature/SuperAdminTest.php`:

```php
public function test_superadmin_can_send_verification_email_to_admin(): void
{
    \Illuminate\Support\Facades\Notification::fake();

    $superAdmin = $this->createSuperAdmin();
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'role' => 'admin',
        'email_verified_at' => now(),  // pre-verified
    ]);

    $response = $this->actingAs($superAdmin, 'superadmin')
        ->post(route('super.restaurants.send-verification', $restaurant));

    $response->assertRedirect();

    \Illuminate\Support\Facades\Notification::assertSentTo(
        $admin,
        \Illuminate\Auth\Notifications\VerifyEmail::class,
    );

    // Verified timestamp is NOT cleared.
    $this->assertNotNull($admin->fresh()->email_verified_at);
}
```

- [ ] **Step 2: Run — verify failure**

```bash
./vendor/bin/sail artisan test --compact --filter=test_superadmin_can_send_verification_email_to_admin
```

Expected: Route-not-found.

- [ ] **Step 3: Add controller method**

In `RestaurantController.php`, add:

```php
public function sendVerification(Request $request, Restaurant $restaurant): RedirectResponse
{
    $admin = User::query()
        ->where('restaurant_id', $restaurant->id)
        ->where('role', 'admin')
        ->firstOrFail();

    $admin->notify(new \Illuminate\Auth\Notifications\VerifyEmail);

    BillingAudit::log(
        action: 'verification_email_sent_manually',
        restaurantId: $restaurant->id,
        actorType: 'super_admin',
        actorId: $request->user('superadmin')->id,
        payload: ['admin_email' => $admin->email],
        ipAddress: $request->ip(),
    );

    return back()->with('success', 'Correo de verificación enviado.');
}
```

Add imports:
```php
use App\Models\BillingAudit;
use Illuminate\Http\Request;
```

- [ ] **Step 4: Add route**

In `routes/web.php`, inside the SuperAdmin group (auth:superadmin):

```php
Route::post('/restaurants/{restaurant}/send-verification',
    [RestaurantController::class, 'sendVerification'])->name('restaurants.send-verification');
```

- [ ] **Step 5: Run test**

```bash
./vendor/bin/sail artisan test --compact --filter=test_superadmin_can_send_verification_email_to_admin
```

Expected: Pass.

- [ ] **Step 6: Add UI button in Show page**

Edit `resources/js/Pages/SuperAdmin/Restaurants/Show.vue`. Add a secondary action button (exact placement per existing button style in that page — usually in the header/actions section):

```vue
<button
    type="button"
    class="inline-flex items-center gap-2 bg-white hover:bg-neutral-50 border border-neutral-200 text-neutral-700 text-sm font-semibold px-4 py-2 rounded-xl transition"
    @click="sendVerification"
>
    <span class="material-symbols-outlined text-base">mark_email_read</span>
    Enviar correo de verificación
</button>
```

Add to the `<script setup>`:

```js
import { router } from '@inertiajs/vue3'

function sendVerification() {
    if (confirm('¿Enviar correo de verificación al administrador?')) {
        router.post(route('super.restaurants.send-verification', props.restaurant.id), {}, {
            preserveScroll: true,
        })
    }
}
```

- [ ] **Step 7: Run Pint + tests**

```bash
./vendor/bin/sail bin pint --dirty --format agent
./vendor/bin/sail artisan test --compact
```

Expected: All green.

- [ ] **Step 8: Checkpoint**

Phase 3 complete.

---

## Phase 4 — Public `/register` route

**Goal:** Expose the self-signup flow publicly. Strict password rules; rate-limited; redirects to email-verify.

### Task 4.1: Create `RegisterRestaurantRequest`

**Files:**
- Create: `app/Http/Requests/Auth/RegisterRestaurantRequest.php`

- [ ] **Step 1: Generate request class**

```bash
./vendor/bin/sail artisan make:request Auth/RegisterRestaurantRequest --no-interaction
```

- [ ] **Step 2: Replace contents**

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restaurant_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()->min(8)->letters()->mixedCase()->numbers()],
            'accept_terms' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'restaurant_name.required' => 'El nombre del restaurante es obligatorio.',
            'admin_name.required' => 'Tu nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'accept_terms.accepted' => 'Debes aceptar los términos y condiciones.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->input('email', ''))),
        ]);
    }
}
```

- [ ] **Step 3: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 4.2: Create `RegisterController` with tests

**Files:**
- Create: `app/Http/Controllers/Auth/RegisterController.php`
- Test: `tests/Feature/Auth/RegisterTest.php` (create)

- [ ] **Step 1: Create feature test file first**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Plan;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Plan::gracePlan()) {
            Plan::factory()->create([
                'name' => 'Gracia',
                'is_default_grace' => true,
                'orders_limit' => 50,
                'max_branches' => 1,
            ]);
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'restaurant_name' => 'Nuevo Taco',
            'admin_name' => 'Ana Pérez',
            'email' => 'ana@nuevotaco.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'accept_terms' => true,
        ], $overrides);
    }

    public function test_register_page_renders(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_happy_path_creates_everything_and_redirects_to_verify(): void
    {
        Event::fake();

        $response = $this->post(route('register.store'), $this->validPayload());

        $response->assertRedirect(route('verification.notice'));

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertEquals('self_signup', $restaurant->signup_source);
        $this->assertEquals('grace_period', $restaurant->status);

        $user = User::where('email', 'ana@nuevotaco.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals($restaurant->id, $user->restaurant_id);

        Event::assertDispatched(Registered::class);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->post(route('register.store'), $this->validPayload([
            'email' => 'existing@test.com',
        ]));

        $response->assertSessionHasErrors('email');
    }

    public function test_rejects_without_accept_terms(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload([
            'accept_terms' => false,
        ]));

        $response->assertSessionHasErrors('accept_terms');
    }

    public function test_rejects_weak_password(): void
    {
        $response = $this->post(route('register.store'), $this->validPayload([
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]));

        $response->assertSessionHasErrors('password');
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $this->post(route('register.store'), $this->validPayload([
            'email' => 'MiXeD@Test.COM',
        ]));

        $this->assertDatabaseHas('users', ['email' => 'mixed@test.com']);
    }

    public function test_logs_billing_audit_with_self_signup_actor(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertDatabaseHas('billing_audits', [
            'restaurant_id' => $restaurant->id,
            'action' => 'restaurant_created',
            'actor_type' => 'self_signup',
        ]);
    }

    public function test_seeds_three_payment_methods(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $restaurant = Restaurant::where('name', 'Nuevo Taco')->firstOrFail();
        $this->assertEquals(3, \App\Models\PaymentMethod::where('restaurant_id', $restaurant->id)->count());
    }

    public function test_user_is_logged_in_after_register(): void
    {
        $this->post(route('register.store'), $this->validPayload());

        $user = User::where('email', 'ana@nuevotaco.com')->firstOrFail();
        $this->assertTrue(auth()->guard('web')->check());
        $this->assertEquals($user->id, auth()->guard('web')->id());
    }

    public function test_throttled_after_three_attempts(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $response = $this->post(route('register.store'), $this->validPayload([
                'email' => "attempt{$i}@test.com",
            ]));
        }

        $response->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run tests — expect failure**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/RegisterTest.php
```

Expected: Route-not-found.

- [ ] **Step 3: Create RegisterController**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRestaurantRequest;
use App\Services\Onboarding\Dto\ProvisionRestaurantData;
use App\Services\Onboarding\RestaurantProvisioningService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(
        RegisterRestaurantRequest $request,
        RestaurantProvisioningService $provisioning,
    ): RedirectResponse {
        $data = $request->validated();

        $dto = new ProvisionRestaurantData(
            source: 'self_signup',
            restaurantName: $data['restaurant_name'],
            adminName: $data['admin_name'],
            adminEmail: $data['email'],
            adminPassword: $data['password'],
            billingMode: 'grace',
            actorId: null,
            ipAddress: $request->ip(),
        );

        $restaurant = $provisioning->provision($dto);

        $admin = $restaurant->users()->where('email', $data['email'])->firstOrFail();

        event(new Registered($admin));

        Auth::guard('web')->login($admin);

        return redirect()->route('verification.notice');
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/web.php`, in the guest block:

```php
Route::middleware('guest')->group(function () {
    // ... existing login/forgot/reset routes

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('register.store');
});
```

Add the import:
```php
use App\Http\Controllers\Auth\RegisterController;
```

- [ ] **Step 5: Run tests — verify partial pass**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/RegisterTest.php
```

Expected: All tests pass EXCEPT `test_register_page_renders` (needs Vue page).

### Task 4.3: Create `Register.vue` page + Login link

**Files:**
- Create: `resources/js/Pages/Auth/Register.vue`
- Modify: `resources/js/Pages/Auth/Login.vue`

- [ ] **Step 1: Create Register.vue**

```vue
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    restaurant_name: '',
    admin_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    accept_terms: false,
})

function submit() {
    form.post(route('register.store'))
}
</script>

<template>
    <Head title="Crear cuenta" />

    <div class="min-h-screen flex items-center justify-center bg-[#FAFAFA] px-4 py-10">
        <div class="w-full max-w-md">
            <div class="text-center mb-6">
                <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FF5722] mb-3">
                    <span class="material-symbols-outlined text-white text-3xl">local_fire_department</span>
                </div>
                <h1 class="text-2xl font-bold text-neutral-900">PideAquí</h1>
                <p class="text-sm text-neutral-500 mt-1">Crea tu cuenta en minutos</p>
            </div>

            <form class="bg-white rounded-2xl shadow-sm p-8 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Nombre del restaurante</label>
                    <input
                        v-model="form.restaurant_name"
                        type="text"
                        required
                        autocomplete="organization"
                        class="w-full px-3 py-2 border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FF5722]"
                    />
                    <p v-if="form.errors.restaurant_name" class="text-xs text-red-600 mt-1">{{ form.errors.restaurant_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Tu nombre</label>
                    <input
                        v-model="form.admin_name"
                        type="text"
                        required
                        autocomplete="name"
                        class="w-full px-3 py-2 border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FF5722]"
                    />
                    <p v-if="form.errors.admin_name" class="text-xs text-red-600 mt-1">{{ form.errors.admin_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Correo</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        class="w-full px-3 py-2 border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FF5722]"
                    />
                    <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Contraseña</label>
                    <input
                        v-model="form.password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-3 py-2 border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FF5722]"
                    />
                    <p class="text-xs text-neutral-500 mt-1">Mínimo 8 caracteres, mayúsculas, minúsculas y números.</p>
                    <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Confirmar contraseña</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full px-3 py-2 border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#FF5722]"
                    />
                </div>

                <div class="flex items-start gap-2">
                    <input
                        id="accept_terms"
                        v-model="form.accept_terms"
                        type="checkbox"
                        class="mt-1"
                    />
                    <label for="accept_terms" class="text-sm text-neutral-700">
                        Acepto los <a href="/terms" class="text-[#FF5722] underline">términos</a> y la
                        <a href="/privacy" class="text-[#FF5722] underline">política de privacidad</a>.
                    </label>
                </div>
                <p v-if="form.errors.accept_terms" class="text-xs text-red-600 -mt-2">{{ form.errors.accept_terms }}</p>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold py-3 rounded-xl transition disabled:opacity-60"
                >
                    Crear cuenta
                </button>

                <div class="text-center text-sm text-neutral-600 pt-2">
                    ¿Ya tienes cuenta?
                    <Link :href="route('login')" class="text-[#FF5722] font-semibold hover:underline">Iniciar sesión</Link>
                </div>
            </form>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Add "Crear cuenta" link to Login.vue**

Open `resources/js/Pages/Auth/Login.vue`. After the submit button (the form closing area), add:

```vue
<div class="text-center text-sm text-neutral-600 pt-4 border-t border-neutral-100 mt-4">
    ¿No tienes cuenta?
    <Link :href="route('register')" class="text-[#FF5722] font-semibold hover:underline">Crear cuenta</Link>
</div>
```

Ensure `Link` is imported from `@inertiajs/vue3` (it probably already is).

- [ ] **Step 3: Run all register tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/RegisterTest.php
```

Expected: All 10 tests pass.

- [ ] **Step 4: Run full suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All green.

- [ ] **Step 5: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 6: Manual smoke test**

Start the app (already running via Sail). Visit `http://localhost/register` in a browser.

- Verify page loads.
- Verify link "Iniciar sesión" works.
- Register a test restaurant — verify redirect to `/email/verify` and that user is logged in.
- Check `MAIL_MAILER=log` output: `storage/logs/laravel.log` should contain a verification URL.
- Copy the verification URL from the log; open it in the browser.
- Verify redirect to `/dashboard?verified=1` and user lands on dashboard.

- [ ] **Step 7: Checkpoint**

Phase 4 complete.

---

## Phase 5 — Grace period banner

**Goal:** Show users in grace period how many days remain, with a CTA to upgrade.

### Task 5.1: Expose `grace_days_remaining` in Inertia shared props

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add computed field**

Find `HandleInertiaRequests::share()` method. In the `billing` array (currently exposes `can_operate`, `block_reason`), add:

```php
'grace_days_remaining' => $this->computeGraceDaysRemaining($request),
```

Add a new private method in the same class:

```php
private function computeGraceDaysRemaining(\Illuminate\Http\Request $request): ?int
{
    $user = $request->user();
    if (! $user || ! $user->restaurant) {
        return null;
    }
    $restaurant = $user->restaurant;
    if ($restaurant->status !== 'grace_period' || ! $restaurant->grace_period_ends_at) {
        return null;
    }
    $days = now()->diffInDays($restaurant->grace_period_ends_at, false);

    return max(0, (int) ceil($days));
}
```

- [ ] **Step 2: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

### Task 5.2: Create `<GracePeriodBanner>` component

**Files:**
- Create: `resources/js/Components/GracePeriodBanner.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const daysRemaining = computed(() => page.props.billing?.grace_days_remaining ?? null)

const show = computed(() => daysRemaining.value !== null)

const urgent = computed(() => daysRemaining.value !== null && daysRemaining.value <= 3)
</script>

<template>
    <div
        v-if="show"
        class="rounded-2xl p-4 mb-6 flex items-center justify-between"
        :class="urgent ? 'bg-red-50 border border-red-200' : 'bg-[#FF5722]/10 border border-[#FF5722]/30'"
    >
        <div class="flex items-center gap-3">
            <span
                class="material-symbols-outlined text-2xl"
                :class="urgent ? 'text-red-600' : 'text-[#FF5722]'"
            >
                card_giftcard
            </span>
            <div>
                <p class="text-sm font-semibold text-neutral-900">
                    <template v-if="daysRemaining === 0">Tu periodo de gracia termina hoy</template>
                    <template v-else-if="daysRemaining === 1">Te queda 1 día de periodo de gracia</template>
                    <template v-else>Te quedan {{ daysRemaining }} días de periodo de gracia</template>
                </p>
                <p class="text-xs text-neutral-600 mt-0.5">
                    Suscríbete a un plan para seguir recibiendo pedidos sin interrupciones.
                </p>
            </div>
        </div>
        <Link
            :href="route('subscription.index')"
            class="bg-[#FF5722] hover:bg-[#D84315] text-white text-sm font-semibold px-4 py-2 rounded-xl transition whitespace-nowrap"
        >
            Ver planes
        </Link>
    </div>
</template>
```

### Task 5.3: Mount banner in Dashboard

**Files:**
- Modify: `resources/js/Pages/Dashboard.vue`

- [ ] **Step 1: Import and place banner**

At the top of the `<script setup>` block:

```js
import GracePeriodBanner from '@/Components/GracePeriodBanner.vue'
```

In the template, near the top of the main content area:

```vue
<GracePeriodBanner />
```

### Task 5.4: Test banner exposure

**Files:**
- Test: `tests/Feature/Auth/EmailVerificationTest.php` (or a new file)

- [ ] **Step 1: Add test**

Add to `EmailVerificationTest.php`:

```php
public function test_grace_days_remaining_exposed_in_inertia_props(): void
{
    $restaurant = Restaurant::factory()->grace()->create([
        'grace_period_ends_at' => now()->addDays(7),
    ]);
    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('billing.grace_days_remaining', fn ($v) => $v >= 6 && $v <= 7)
    );
}

public function test_grace_days_remaining_null_for_active_restaurant(): void
{
    $restaurant = Restaurant::factory()->create([
        'status' => 'active',
        'grace_period_ends_at' => null,
    ]);
    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('billing.grace_days_remaining', null)
    );
}
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/sail artisan test --compact tests/Feature/Auth/EmailVerificationTest.php
```

Expected: All pass.

- [ ] **Step 3: Run full suite**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: All green.

- [ ] **Step 4: Manual verification**

Log in as a self-signup user just created in Phase 4. Visit the dashboard. Banner should display "Te quedan 14 días de periodo de gracia" with CTA "Ver planes".

Simulate urgency: via tinker, set `grace_period_ends_at = now()->addDays(2)` on that restaurant, reload dashboard, verify the banner switches to red styling.

- [ ] **Step 5: Run Pint**

```bash
./vendor/bin/sail bin pint --dirty --format agent
```

- [ ] **Step 6: Checkpoint**

Phase 5 complete. Feature fully delivered.

---

## Final Verification

After all phases complete, run these end-to-end checks:

- [ ] **Run full test suite once more**

```bash
./vendor/bin/sail artisan test --compact
```

Expected: 619+ existing tests + ~30 new tests all green.

- [ ] **Manual smoke (all flows):**

1. Visit `http://localhost/login` — verify "Crear cuenta" link visible.
2. Click "Crear cuenta" — verify Register form loads.
3. Submit valid registration — verify redirect to email-verify, user logged in.
4. Check `storage/logs/laravel.log` for verification URL, open it — verify redirect to dashboard with banner.
5. Log out, log back in with same credentials — verify dashboard with banner.
6. Log in as SuperAdmin — verify existing `Crear restaurante` still works unchanged.
7. In SuperAdmin `Restaurants/Show`, click "Enviar correo de verificación" for any admin — verify notification log entry.
8. Verify `restaurants.signup_source` column has both `self_signup` and `super_admin` values across records.

- [ ] **Documentation updates (per CLAUDE.md rules):**

Update the following (optional but project convention):
- `admin/STATUS.md` — note self-signup feature live
- `admin/CHANGELOG.md` — add entry dated 2026-04-22
- `admin/docs/modules/01-auth.md` — document new `/register` + email verification
- `admin/docs/DATABASE.md` — note `restaurants.signup_source` column
- `admin/docs/ARCHITECTURE.md` — note `RestaurantProvisioningService` as onboarding orchestrator

These updates are not in scope of the implementation itself; apply them when the user approves the feature for production.
