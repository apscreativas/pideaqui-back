---
title: Self-signup público de restaurantes
date: 2026-04-22
status: draft
owner: sebas
---

# Self-signup público de restaurantes — Diseño

## 1. Resumen

Habilitar que un nuevo dueño de restaurante se registre por sí mismo en PideAquí, creando su cuenta y restaurante con plan de gracia automático (14 días, 50 pedidos, 1 sucursal, `billing_mode='subscription'` + `status='grace_period'`). El flujo manual de SuperAdmin se preserva intacto, reutilizando la lógica de provisioning extraída a un servicio compartido.

## 2. Objetivos

- Ruta pública `/register` con formulario mínimo (nombre restaurante, nombre admin, email, password, accept terms).
- Verificación de correo obligatoria antes de acceder al dashboard.
- Creación atómica (Restaurant + User + PaymentMethods + BillingAudit) vía `RestaurantProvisioningService`.
- Plan de gracia auto-asignado (mismos defaults que el flujo SuperAdmin con `billing_mode='grace'`).
- Diferenciación en BD vía `restaurants.signup_source = 'self_signup' | 'super_admin'`.
- Banner de gracia en dashboard indicando días restantes.

## 3. No objetivos (YAGNI)

- Wizard de onboarding multi-paso (crear sucursal, subir logo, horarios).
- Captcha / reCAPTCHA en v1 (sólo throttle + email verification).
- Flujo de baja self-service de cuenta.
- Link en landing Nuxt (pendiente para fase posterior).
- Invitaciones multi-usuario al restaurante (fuera de alcance).

## 4. Arquitectura

### 4.1 Capas

```
Controllers
├── Auth/RegisterController         ← nueva ruta pública /register
└── SuperAdmin/RestaurantController ← delgado, llama al service

Services
└── Onboarding/
    ├── RestaurantProvisioningService   ← ORQUESTADOR único
    └── Dto/ProvisionRestaurantData     ← DTO con origen + campos

FormRequests
├── Auth/RegisterRestaurantRequest         ← validación pública (campos mínimos)
└── SuperAdmin/CreateRestaurantRequest     ← intacto (campos admin completos)
```

### 4.2 Service — contrato

```php
final class RestaurantProvisioningService
{
    public function __construct(
        private readonly BillingSettingRepository $billingSettings,
    ) {}

    /**
     * Crea Restaurant + User admin + PaymentMethods + BillingAudit en una transacción.
     * Retorna el Restaurant ya persistido (con User relacionado cargado).
     */
    public function provision(ProvisionRestaurantData $data): Restaurant;

    private function generateUniqueSlug(string $name): string;      // reubicado desde RestaurantController
    private function generateAccessToken(): string;                  // hash('sha256', Str::random(40))
    private function seedPaymentMethods(int $restaurantId): void;    // 3 rows cash/terminal/transfer
}
```

### 4.3 DTO

```php
final readonly class ProvisionRestaurantData
{
    public function __construct(
        public string $source,              // 'self_signup' | 'super_admin'
        public string $restaurantName,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,       // plaintext — cast 'hashed' lo cifra
        public string $billingMode,         // 'grace' | 'manual'
        public ?int $ordersLimit = null,    // solo manual
        public ?int $maxBranches = null,    // solo manual
        public ?Carbon $ordersLimitStart = null,
        public ?Carbon $ordersLimitEnd = null,
        public ?int $actorId = null,        // super_admin id si source=super_admin
        public ?string $ipAddress = null,
    ) {}
}
```

### 4.4 Mapping `billing_mode` input → DB

| Input form | DB `billing_mode` | DB `status` | `plan_id` | Fechas |
|---|---|---|---|---|
| `'grace'` | `'subscription'` | `'grace_period'` | `Plan::gracePlan()->id` | `grace_period_ends_at = now()+N days` |
| `'manual'` | `'manual'` | `'active'` | `null` | Límites del DTO |

(Comportamiento ya implementado en SuperAdmin controller; se preserva al migrarlo al service.)

## 5. Cambios de modelo de datos

### 5.1 Migración: `signup_source`

```php
// 2026_04_22_xxxxxx_add_signup_source_to_restaurants.php
Schema::table('restaurants', function (Blueprint $t) {
    $t->string('signup_source', 32)->nullable()->after('billing_mode')->index();
});
```

- Nullable para no romper registros existentes (backfill opcional: `super_admin`).
- Values permitidos (enforced en service, no DB-level): `'self_signup'`, `'super_admin'`.

### 5.2 Migración: backfill `email_verified_at` para usuarios existentes

```php
// 2026_04_22_yyyyyy_backfill_email_verified_at.php
DB::table('users')
    ->whereNull('email_verified_at')
    ->update(['email_verified_at' => DB::raw('created_at')]);
```

Sin esta migración, habilitar `MustVerifyEmail` bloquearía a todos los admins manuales existentes. Corre antes del deploy que añade `implements MustVerifyEmail`.

### 5.3 User implements `MustVerifyEmail`

`app/Models/User.php`:

```php
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    // ...
}
```

Trait `MustVerifyEmail` (Laravel nativo) añade columnas esperadas (`email_verified_at` ya existe) y método `sendEmailVerificationNotification()`.

### 5.4 Pre-verificación para users creados por SuperAdmin

**Regla:** la verificación por correo es obligatoria **solo para self-signup**. Usuarios creados por SuperAdmin entran pre-verificados (el SuperAdmin "actúa como" verificador, ya validó al cliente fuera de banda).

La decisión vive en un único punto — el service — para no contaminar middleware ni runtime:

```php
// RestaurantProvisioningService::provision(...)
$user = new User([...]);
$user->restaurant_id = $restaurant->id;
$user->role = 'admin';

if ($data->source === 'super_admin') {
    $user->email_verified_at = now();   // pre-verificado
}
$user->save();

if ($data->source === 'self_signup') {
    event(new Registered($user));        // dispara correo de verificación
}
```

Laravel middleware `verified` usa `$user->hasVerifiedEmail()` (binario: hay timestamp o no). Cero condicionales custom en runtime; el estado del user queda self-contained. `signup_source` es señal de auditoría/métricas, **no se consulta en auth**.

**Factory:** `UserFactory` default de Laravel ya retorna `email_verified_at = now()` (tests existentes no se rompen). Para tests específicos de self-signup usaremos `UserFactory::unverified()` state built-in.

## 6. Flujo: self-signup

```
1. GET /register (guest)            → Inertia render Pages/Auth/Register.vue
2. POST /register (guest, throttle:3,1)
   → RegisterRestaurantRequest valida
   → RestaurantProvisioningService::provision(source='self_signup', billing_mode='grace')
       DB::transaction:
         a. Restaurant::create([...grace defaults + signup_source='self_signup'])
         b. User::create([...], luego $user->restaurant_id = $r->id; $user->role='admin'; $user->save())
         c. seedPaymentMethods($r->id)
         d. BillingAudit::log(action='restaurant_created', restaurantId=$r->id,
                              actorType='self_signup', actorId=$user->id,
                              payload=['billing_mode'=>'grace','plan'=>..., 'signup_source'=>'self_signup'])
   → event(new Registered($user))        // dispara SendEmailVerificationNotification
   → Auth::guard('web')->login($user)
   → Redirect a /email/verify
3. Usuario abre link de verificación en email
   → GET /email/verify/{id}/{hash} (middleware signed, throttle:6,1)
   → User::markEmailAsVerified()
   → Redirect a /dashboard
4. Dashboard carga con banner "Tu periodo de gracia termina en N días"
```

## 7. Flujo: email verification

- `Auth/VerifyEmailController` (Laravel standard layout):
  - `@notice` — GET `/email/verify` muestra "revisa tu correo"
  - `@verify` — GET `/email/verify/{id}/{hash}` valida firma + marca verificado
  - `@send` — POST `/email/verification-notification` reenvía (throttle:6,1)
- Middleware `verified` en grupo `['auth','tenant']` — bloquea solo a users con `email_verified_at = null`. Admins backfilled y admins creados por SuperAdmin (pre-verificados) pasan sin fricción.
- LoginController: si usuario loguea correctamente pero `email_verified_at` es null → redirige a `/email/verify` (no a dashboard). Solo aplica a self-signup sin verificar.

## 8. Cambios de UI

### 8.1 Login
`resources/js/Pages/Auth/Login.vue`: agregar link al final del form:
```vue
<div class="mt-4 text-center text-sm">
  ¿No tienes cuenta? <Link :href="route('register')">Crear cuenta</Link>
</div>
```

### 8.2 Register (nuevo)
`resources/js/Pages/Auth/Register.vue` — 1 columna, layout similar a Login:
- `restaurant_name` (text)
- `admin_name` (text)
- `email` (email, lowercase onBlur)
- `password` (+ hint de fortaleza)
- `password_confirmation`
- `accept_terms` (checkbox con link a `/terms` y `/privacy` — placeholders por ahora)
- Botón "Crear cuenta"
- Link "¿Ya tienes cuenta? Iniciar sesión"

### 8.3 VerifyEmail (nuevo)
`resources/js/Pages/Auth/VerifyEmail.vue`:
- Mensaje explicativo
- Botón "Reenviar correo" (POST `/email/verification-notification`)
- Link "Cerrar sesión"

### 8.4 Dashboard banner (grace)
`resources/js/Pages/Dashboard.vue`: banner condicional si `restaurant.status === 'grace_period'`:
```
🎁 Estás en periodo de gracia — te quedan {{ daysRemaining }} días
   [Ver planes]
```

### 8.5 Escape hatch opcional en SuperAdmin Show
`resources/js/Pages/SuperAdmin/Restaurants/Show.vue`: botón secundario **"Enviar correo de verificación al admin"** que dispara `Notification::send($admin, new VerifyEmail)` de forma voluntaria.

Útil cuando:
- SuperAdmin sospecha que tipeó mal el email y quiere que el admin confirme recepción.
- Política interna pide doble-check por política de compliance.

Ruta: `POST /super/restaurants/{id}/send-verification`. Audit entry: `BillingAudit` action `verification_email_sent_manually`, actor `super_admin`.

**No modifica `email_verified_at`** — solo envía el correo; si el admin no hace click, su estado sigue verificado (fue pre-verificado al crearse).

## 9. Retrocompatibilidad

| Flujo | Estado | Cambios |
|---|---|---|
| SuperAdmin crea restaurant manual | ✅ Igual | Controller thin-wrapper del service; `signup_source='super_admin'`; admin creado pre-verificado (`email_verified_at=now()`) — sin fricción de verificación |
| SuperAdmin `toggleActive` | ✅ Sin cambio | N/A |
| SuperAdmin `startGracePeriod` | ✅ Sin cambio | N/A |
| Login admin existente | ✅ Sin cambio | Backfill `email_verified_at = created_at` en migración one-shot; middleware `verified` los deja pasar |
| Login admin creado por SuperAdmin post-deploy | ✅ Sin cambio | Service setea `email_verified_at=now()`; middleware `verified` los deja pasar |
| Login self-signup sin verificar | 🆕 Nuevo | Redirige a `/email/verify` — único flujo bloqueado por verification |
| Login SuperAdmin | ✅ Sin cambio | N/A |
| Stripe webhooks | ✅ Sin cambio | Subscription creada en `checkout.session.completed` igual que antes |
| `canOperate()` / `LimitService` | ✅ Sin cambio | `status='grace_period'` ya soportado |
| TenantScope | ✅ Sin cambio | Activa al login post-registro |
| `transitionToManual()` | ✅ Sin cambio | SuperAdmin puede cambiar a manual un self-signup igual que uno manual |
| Tests existentes | 🟡 Revisar | `SuperAdminTest::test_creates_restaurant_with_grace` debe seguir pasando; asserts sobre PaymentMethods y BillingAudit idénticos |

## 10. Seguridad

| Vector | Mitigación |
|---|---|
| Email enumeration | Error genérico "no pudimos registrarte" sin decir "email ya existe" (o mensaje neutro) |
| Spam / bots creando restaurantes | Email verification obligatoria + throttle 3/min por IP |
| Password weak | `Password::defaults()->min(8)->letters()->mixedCase()->numbers()->uncompromised()` |
| CSRF | Token estándar Inertia |
| Case-sensitive email collision | `prepareForValidation()` → `strtolower($email)` |
| Race condition doble submit | DB unique en `users.email` + transacción rollback → 422 al segundo |
| Slug collision | `generateUniqueSlug()` con loop de sufijos (ya existe) |
| BillingAudit actor_type nuevo | Valores permitidos: `'super_admin'|'admin'|'system'|'self_signup'` — actualizar docblock en `BillingAudit::log()` |
| Enum-like `signup_source` sin CHECK | Enforced en PHP (service), no en DB — ganamos agilidad, perdemos hard guarantee; aceptable |

## 11. Casos edge resueltos

| Caso | Comportamiento |
|---|---|
| Email ya existe | 422 validación `unique:users,email` (antes de transaction) |
| Doble submit exacto mismo ms | Segundo falla por unique constraint en DB → rollback → 422 |
| Password sin confirmación | 422 |
| accept_terms=false | 422 |
| Falla insert PaymentMethod | Rollback completo (transacción) — nada persiste |
| Falla BillingAudit::log | Rollback completo |
| Usuario registra pero no verifica email | Puede re-loguear pero siempre va a `/email/verify` hasta verificar |
| Usuario registra y abandona — grace expira sin verificar | `email_verified_at=null` + `grace_period_ends_at` pasado; no puede operar igualmente. Cleanup manual por SuperAdmin (o job futuro) |
| SuperAdmin cambia self-signup a manual | `transitionToManual()` funciona idéntico — `signup_source` se preserva como marca histórica |
| Self-signup contrata Stripe después | Flujo checkout estándar; `signup_source` no interfiere |
| SuperAdmin manual luego | Mismo provisioning service, `signup_source='super_admin'` |

## 12. Plan de fases (rollout seguro)

### Fase 0 — Hardening previo (NO feature)
- Envolver `SuperAdmin/RestaurantController@store` en `DB::transaction`
- Tests: confirmar `SuperAdminTest` sigue verde

### Fase 1 — Extract service (NO feature)
- Crear `RestaurantProvisioningService` + `ProvisionRestaurantData`
- Migrar `@store` a llamar al service (comportamiento idéntico)
- Mover helpers `generateUniqueSlug()` y generación de `access_token` al service
- Tests: `RestaurantProvisioningServiceTest` unit + `SuperAdminTest` sin cambios

### Fase 2 — Columna `signup_source`
- Migration añade `signup_source`
- Service lo setea según `$data->source`
- SuperAdmin form pasa `'super_admin'`
- Backfill opcional: `UPDATE restaurants SET signup_source='super_admin' WHERE signup_source IS NULL` (histórico)

### Fase 3 — Email verification (solo afecta self-signup)
- Migration backfill `email_verified_at = created_at WHERE email_verified_at IS NULL` para users existentes
- **Mismo commit/release:** `User implements MustVerifyEmail` + service setea `email_verified_at=now()` cuando `source='super_admin'`
- Rutas `/email/verify*` + VerifyEmailController (Laravel standard)
- Pages/Auth/VerifyEmail.vue
- Middleware `verified` en grupo `['auth','tenant']`
- LoginController redirect condicional a `/email/verify` si `!hasVerifiedEmail()`
- (Opcional en misma fase) endpoint `POST /super/restaurants/{id}/send-verification` + botón en Show
- Tests:
  - admin existente (backfilled) entra sin verificar
  - admin creado por SuperAdmin post-deploy entra sin verificar (pre-verified)
  - self-signup sin verificar es redirigido a `/email/verify`

**Orden de deploy crítico:** la migración de backfill DEBE correr antes de que `MustVerifyEmail` tome efecto. `php artisan migrate` corre antes que los requests — siempre que ambos cambios estén en el mismo release, el orden queda garantizado.

### Fase 4 — Ruta pública `/register`
- `RegisterRestaurantRequest`
- `Auth/RegisterController`
- `Pages/Auth/Register.vue` + link en Login
- Throttle `register,3,1`
- Tests feature completos

### Fase 5 — Banner grace + polish
- Componente `<GracePeriodBanner>` en dashboard
- Exposición de `daysRemaining` en props de Inertia

Cada fase se entrega en un commit separado y debe pasar la suite antes del siguiente.

## 13. Archivos afectados

**Nuevos:**
- `app/Services/Onboarding/RestaurantProvisioningService.php`
- `app/Services/Onboarding/Dto/ProvisionRestaurantData.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Requests/Auth/RegisterRestaurantRequest.php`
- `resources/js/Pages/Auth/Register.vue`
- `resources/js/Pages/Auth/VerifyEmail.vue`
- `resources/js/Components/GracePeriodBanner.vue`
- `database/migrations/2026_04_22_xxxxxx_add_signup_source_to_restaurants.php`
- `database/migrations/2026_04_22_yyyyyy_backfill_email_verified_at.php`
- `tests/Unit/RestaurantProvisioningServiceTest.php`
- `tests/Feature/Auth/RegisterTest.php`
- `tests/Feature/Auth/EmailVerificationTest.php`

**Modificados:**
- `app/Http/Controllers/SuperAdmin/RestaurantController.php` — thin wrapper del service + acción `sendVerification`
- `app/Models/User.php` — `implements MustVerifyEmail`
- `app/Models/BillingAudit.php` — docblock permite `actor_type='self_signup'`; actions nuevas `verification_email_sent_manually`
- `app/Http/Controllers/Auth/LoginController.php` — redirect a `/email/verify` si no verificado
- `routes/web.php` — nuevas rutas register + verify-email + super send-verification
- `resources/js/Pages/Auth/Login.vue` — link "Crear cuenta"
- `resources/js/Pages/Dashboard.vue` — incluye `<GracePeriodBanner>`
- `resources/js/Pages/SuperAdmin/Restaurants/Show.vue` — botón "Enviar correo de verificación"
- `database/factories/RestaurantFactory.php` — states `grace()` y `selfSignup()`
- `tests/Feature/SuperAdminTest.php` — asserts de `signup_source` y `email_verified_at` en admin creado

## 14. Estrategia de tests

### Unit
- `RestaurantProvisioningServiceTest`:
  - `it_provisions_grace_restaurant_with_correct_defaults`
  - `it_provisions_manual_restaurant_with_provided_limits`
  - `it_rolls_back_on_payment_methods_failure`
  - `it_rolls_back_on_audit_failure`
  - `it_generates_unique_slug_on_collision`
  - `it_generates_unique_access_token`
  - `it_sets_signup_source_correctly`
  - `it_assigns_role_admin_and_restaurant_id_to_user`

### Feature
- `RegisterTest`:
  - `happy_path_creates_everything_and_redirects_to_verify`
  - `rejects_duplicate_email`
  - `rejects_without_accept_terms`
  - `rejects_weak_password`
  - `normalizes_email_to_lowercase`
  - `throttled_after_3_attempts`
  - `concurrent_same_email_only_one_succeeds`
  - `restaurant_has_signup_source_self_signup`
  - `billing_audit_logged_with_correct_actor`
- `EmailVerificationTest`:
  - `self_signup_user_cannot_access_dashboard_until_verified`
  - `self_signup_user_redirected_to_verify_notice_after_login`
  - `signed_link_verifies_email`
  - `expired_link_fails`
  - `resend_respects_throttle`
  - `backfilled_old_users_are_not_blocked`
  - `admin_created_by_superadmin_is_pre_verified_and_accesses_dashboard`
  - `superadmin_can_trigger_manual_verification_email_without_unverifying`
- `SuperAdminTest` (regresión):
  - Tests existentes pasan sin cambios
  - Nuevo: `manual_creation_sets_signup_source_super_admin`
  - Nuevo: `manual_creation_sets_email_verified_at_on_admin_user`

### Integración con features existentes (smoke tests)
- `LimitServiceTest`, `RestaurantOperationalGateTest`, `StripeWebhookTest` deben seguir verdes.

## 15. Plan de tests manuales

Enumerados en la sesión de brainstorming (21 ítems). Resumen:
- Regresión SuperAdmin (ítems 1-7)
- Happy path self-signup + verificación (8-15)
- Transición a Stripe / manual desde self-signup (16-19)
- Edge case sin verificar (20)
- Landing link opcional (21)

Documentar resultados esperados en `admin/docs/OPERATIONS.md` bajo sección "Onboarding self-signup runbook".

## 16. Decisiones pendientes (ninguna bloqueante)

- Texto exacto de `/terms` y `/privacy` — placeholders en v1, copy legal se añade después.
- Diseño del `<GracePeriodBanner>` — usa Design System del admin (naranja `#FF5722`).
- Email del remitente de verificación — usar `MAIL_FROM_ADDRESS` existente.

## 17. Riesgos residuales

- **Bots con correos desechables:** podrían crear cuentas y completar verificación. Mitigación futura: reCAPTCHA (fuera de alcance v1).
- **Volumen inesperado de self-signups:** alternativa futura introducir `status='pending_approval'` y moderación SuperAdmin. Por ahora, gratis con gracia limitada — riesgo aceptable.
- **Typo del SuperAdmin en email del admin creado manualmente:** sin verificación obligatoria, un typo no se detecta hasta que falla un password reset o `NewOrderNotification`. Mitigación: botón voluntario "Enviar correo de verificación" en Show (sección 8.5).
- **Cambio de email posterior no fuerza re-verificación (R2):** si un admin pre-verificado (creado por SuperAdmin) o un admin antiguo (backfilled) cambia su email en Settings, el nuevo email queda marcado como verificado sin validarse. **Decisión explícita:** fuera de alcance v1 — el endpoint de edición de email por el propio admin no se toca. Consecuencia: un admin malicioso podría cambiar su email a cualquier dirección sin validación. Mitigación operacional: monitorear cambios de email vía audit log; considerar resetear `email_verified_at` cuando cambia email como hardening futuro.

---

**Revisión requerida:** Por favor revisa este documento y confírmame ajustes antes de pasar a `writing-plans`.
