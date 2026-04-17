# Módulo 17 — Billing SaaS (Stripe + Cashier)

> **Tipo:** Módulo de infraestructura / SuperAdmin
> **Estado:** Implementado (Mar–Abr 2026)
> **Spec de referencia:** `docs/BILLING_SPEC.md` (propósito, planes, flujos comerciales)

El módulo de billing convierte GuisoGo en una plataforma SaaS real. Cada restaurante tiene un **modo de cobro** (`manual` o `subscription`), un **plan** asignado, un **status** (active, grace, past_due, suspended, etc.) y un **gate operacional** (`canOperate()`) que controla si puede seguir recibiendo pedidos.

Está construido sobre **Laravel Cashier** — el `Restaurant` es el `Billable`, no el `User`, porque un restaurante puede tener varios usuarios.

---

## Conceptos clave

### Modos de cobro (`billing_mode`)

- **`manual`** — el SuperAdmin define un **período** (`orders_limit_start` / `orders_limit_end`) y un `orders_limit` manual. No hay cobro automatizado. Útil para clientes en demo, con convenios especiales o fuera de Stripe.
- **`subscription`** — el restaurante tiene una suscripción Stripe activa. El plan, el período y los límites se sincronizan desde Stripe (webhooks).

### Estados (`status`)

| Estado | Descripción |
| --- | --- |
| `active` | Operando normalmente |
| `grace_period` | Falla de pago reciente o gracia inicial; sigue operando con fecha límite (`grace_period_ends_at`) |
| `past_due` | Falla de pago sin gracia explícita; Stripe reintentará |
| `incomplete` | Checkout iniciado pero no completado |
| `suspended` | Gracia expiró o SuperAdmin lo suspendió manualmente; no opera |
| `canceled` | Suscripción cancelada; opera hasta `subscription_ends_at` |
| `disabled` | Deshabilitado por SuperAdmin; no opera nunca |

### Gate operacional (`canOperate()`)

El método más importante del módulo. Decide si un restaurante puede crear pedidos manuales/POS en este momento.

Bloquea si:

1. `status ∈ {disabled, suspended, incomplete, past_due}`, o
2. Modo subscription y `subscription_ends_at < now()`, o
3. Modo manual y `orders_limit_end < now()` (período vencido) o `orders_limit_start > now()` (no iniciado)

**No bloquea** por alcanzar `orders_limit`. Eso es una preocupación de capacidad, no de estado del servicio. El POS tampoco se bloquea por `orders_limit_reached` (ver `14-pos.md`).

---

## Schema

### Migraciones de Cashier

- `2026_03_30_161250_create_subscriptions_table.php` — tabla estándar de Cashier (`user_id`, `stripe_id`, `stripe_status`, `stripe_price`, `quantity`, `trial_ends_at`, `ends_at`, `type`)
- `2026_03_30_161251_create_subscription_items_table.php` — items múltiples por suscripción

> ⚠️ En este proyecto, **el `user_id` de Cashier en realidad apunta a `restaurants.id`** porque `Restaurant` es el `Billable`.

- `2026_04_06_082039_add_billing_period_to_subscriptions_table.php` — agrega `current_period_start` y `current_period_end` sincronizados con Stripe

### Tablas propias

**`plans`** (catálogo global de planes):

| Columna | Tipo | Notas |
| --- | --- | --- |
| `name`, `slug` | string | |
| `orders_limit`, `max_branches` | int | límites del plan |
| `monthly_price`, `yearly_price` | decimal | en MXN |
| `stripe_product_id` | string | |
| `stripe_monthly_price_id`, `stripe_yearly_price_id` | string | |
| `is_default_grace` | boolean | marca el plan usado como "plan de gracia" cuando se fuerza gracia |
| `is_active` | boolean | |
| `sort_order` | int | |

**`billing_settings`** — key-value global: `grace_period_days`, `payment_grace_days`, `reminder_days`, etc.

**`billing_audits`** — log inmutable con `restaurant_id`, `actor_type`, `actor_id`, `action`, `payload` (jsonb), `ip_address`, `created_at`.

**`stripe_webhook_events`** — deduplicación:

| Columna | Tipo | Notas |
| --- | --- | --- |
| `stripe_event_id` | string | **unique** |
| `type` | string | |
| `processed_at` | timestamp | |

### ALTER `restaurants`

Agrega columnas de billing:

- `plan_id` (FK), `pending_plan_id` (FK) — plan actual y pending downgrade
- `status` (string) — ver tabla de estados arriba
- `billing_mode` — `'manual'` | `'subscription'` (default `manual`)
- `pending_billing_cycle` — nullable (para downgrades programados)
- `grace_period_ends_at`, `subscription_ends_at` — timestamps nullables
- Se conservan `orders_limit`, `orders_limit_start`, `orders_limit_end` del esquema original

---

## Modelos

### `app/Models/Restaurant.php`

Usa el trait `Laravel\Cashier\Billable`. Métodos propios del módulo billing:

| Método | Propósito |
| --- | --- |
| `isManualMode(): bool` | `billing_mode === 'manual'` |
| `isSubscriptionMode(): bool` | `billing_mode === 'subscription'` |
| `canOperate(LimitService): bool` | `operationalBlockReason() === null` |
| `operationalBlockReason(LimitService): ?string` | Ver tabla de razones más abajo |
| `canReceiveOrders(): bool` | Permite recibir pedidos del cliente (similar pero orientado a API pública) |
| `canAccessPanel(): bool` | Permite entrar al admin |
| `transitionTo($status, ...)` | Cambio de status con audit automático |
| `transitionToManual()` / `transitionToSubscription()` | Cambios de modo |
| `assignPlan(Plan, ...)` | Asigna plan y sincroniza límites efectivos |
| `startGracePeriod($days)` | Fuerza gracia (usado por SuperAdmin) |
| `getEffectiveOrdersLimit(): int` | Plan vs override manual |
| `getEffectiveMaxBranches(): int` | Plan vs override manual |

**Razones de bloqueo** (`operationalBlockReason()`):

- `'disabled'`, `'suspended'`, `'incomplete'`, `'past_due'`
- `'subscription_expired'` — `subscription_ends_at < now()`
- `'period_expired'` — `orders_limit_end < now()` en modo manual
- `'period_not_started'` — `orders_limit_start > now()` en modo manual

### `app/Models/Plan.php`

Métodos: `gracePlan()` (retorna el plan con `is_default_grace=true`), `purchasable()` (scope para planes visibles al cliente).

### `app/Models/BillingSetting.php`

```php
BillingSetting::get('grace_period_days', 14);   // key-value
BillingSetting::getInt('reminder_days', 3);
BillingSetting::set('payment_grace_days', 7);
```

### `app/Models/BillingAudit.php`

```php
BillingAudit::log(
    restaurant: $restaurant,
    actor: $user,                       // puede ser null (system)
    action: 'grace_period_started',
    payload: ['days' => 14, 'reason' => '...'],
    ipAddress: request()->ip(),
);
```

Inmutable — no expone `update()`. Solo `log()` y `scopeForRestaurant()`.

---

## Controllers

### `SubscriptionController.php` (admin)

Panel para el dueño del restaurante:

- `index()` → `Settings/Subscription.vue` (plan actual, período, órdenes consumidas, sucursales activas, %)
- `initiateSubscription()` → transición manual → subscription + aplica gracia inicial (default 14 días desde `billing_settings.grace_period_days`)
- `checkout(Plan)` → Stripe Checkout. **Rechaza si `isManualMode()` o ya tiene suscripción activa** (evita estado inconsistente).
- `swap(Plan)` → cambio de plan con `swapAndInvoice()` (prorrateado)
- `applyUpgrade()` / `scheduleDowngrade()` → upgrade inmediato / downgrade al final del período
- `cancelPendingDowngrade()`
- `cancel()` / `resume()` — cancelación con Cashier
- `portal()` — Stripe Customer Portal

### `StripeWebhookController.php`

Extiende el controller de Cashier. Ver sección "Webhooks" abajo.

### `SuperAdmin/BillingSettingsController.php`

CRUD global de `billing_settings`:

- `index()` / `update()` — gestión de `grace_period_days`, `payment_grace_days`, `reminder_days`

### `SuperAdmin/RestaurantController.php` (métodos relevantes)

- **`startGracePeriod($id)`** — fuerza gracia manualmente.
  - Valida días (1–90)
  - **Si el restaurante está subscripto**, cancela la suscripción Stripe con `cancelNow()` antes de forzar gracia local (evita divergencia con Stripe)
  - Asigna `plan_id = gracePlan`, `billing_mode = 'subscription'`
  - Transición → `grace_period` con `grace_period_ends_at = now() + days`
  - `BillingAudit::log()` con `stripe_subscription_canceled` flag si aplicó

- **`updateLimits($id)`** — al cambiar de manual → subscription, si hay Stripe sub activa, la cancela también.

- **`regenerateToken($id)`** — no billing, pero vive aquí.

---

## Helper `BillingMessages`

`app/Support/BillingMessages.php`

Un único método estático:

```php
BillingMessages::operational(Restaurant $r, ?string $reason): ?string
```

Retorna mensaje en español según la razón:

- `'disabled'` → "Tu restaurante está desactivado. Contacta a soporte."
- `'suspended'` → "Tu plan está suspendido. Regulariza tu pago para volver a operar."
- `'incomplete'` → "Completa el pago en Stripe para activar tu suscripción."
- `'past_due'` → "Tu último cobro falló. Actualiza tu método de pago."
- `'subscription_expired'` → "Tu suscripción venció el [fecha]. Renueva para seguir operando."
- `'period_expired'` → "Tu periodo expiró el [fecha]. Contacta al administrador."
- `'period_not_started'` → "Tu periodo inicia el [fecha]."

---

## Webhooks de Stripe

`app/Http/Controllers/StripeWebhookController.php`

### Deduplicación

Antes de procesar cualquier evento:

```php
$inserted = StripeWebhookEvent::insertOrIgnore([
    'stripe_event_id' => $event->id,
    'type' => $event->type,
    'processed_at' => null,
]);

if ($inserted === 0) {
    return response('duplicate', 200);
}
```

Si el mismo evento llega dos veces (reintento de Stripe), el segundo retorna 200 sin reprocesar.

### Eventos manejados

| Evento Stripe | Acción |
| --- | --- |
| `checkout.session.completed` | Resuelve `price_id → Plan`, `assignPlan()`, transición → `active` |
| `customer.subscription.created` | Sincroniza `current_period_*`, asigna plan |
| `invoice.paid` | Aplica `pending_downgrade` si es nuevo período; restaura de `grace_period`/`past_due`/`suspended` → `active` |
| `invoice.payment_failed` | `active` → `grace_period` (usando `payment_grace_days` de settings) |
| `customer.subscription.updated` | Sincroniza período; safety-net para transicionar a `past_due` |
| `customer.subscription.deleted` | Transición → `suspended` |

Cada rama registra en `BillingAudit`.

### Fallback: suscripción sin plan

Si llega un webhook con un `price_id` que no corresponde a ningún `Plan` en DB (por ejemplo, un plan creado directamente en Stripe sin reflejarse), el controller:

- Asigna la suscripción pero deja `plan_id = null`
- Registra en `BillingAudit` con `action = 'subscription_assigned_without_plan'` para que el SuperAdmin lo vea
- Loggea warning al canal de logs

---

## Middleware — Props Inertia

`app/Http/Middleware/HandleInertiaRequests.php`

Inyecta en `props.billing` cuando el usuario autenticado tiene `restaurant_id`:

```json
{
  "status": "grace_period",
  "billing_mode": "subscription",
  "plan_name": "Premium",
  "grace_period_ends_at": "2026-04-28T00:00:00.000000Z",
  "subscription_ends_at": null,
  "must_show_billing": false,
  "can_operate": true,
  "block_reason": null,
  "block_message": null
}
```

- **`can_operate`** — resultado de `Restaurant::canOperate()` — disponible globalmente en todas las páginas Inertia
- **`block_reason`** / **`block_message`** — usados por los banners rojos y para deshabilitar botones en `Orders/Index.vue` y `Pos/Index.vue` (ver `14-pos.md`)
- **`must_show_billing`** — flag para el guard de ruta que redirige a `Settings/Subscription` en `suspended` / `incomplete`

---

## Rutas

`routes/web.php`:

```
# Webhook público (sin auth)
POST   /stripe/webhook                         → StripeWebhookController@handleWebhook

# Admin (requiere guard web)
GET    /settings/subscription                  → SubscriptionController@index
POST   /settings/subscription/initiate         → SubscriptionController@initiateSubscription
POST   /settings/subscription/checkout         → SubscriptionController@checkout
POST   /settings/subscription/swap             → SubscriptionController@swap
POST   /settings/subscription/cancel           → SubscriptionController@cancel
POST   /settings/subscription/resume           → SubscriptionController@resume
POST   /settings/subscription/portal           → SubscriptionController@portal
POST   /settings/subscription/cancel-downgrade → SubscriptionController@cancelPendingDowngrade

# SuperAdmin (guard superadmin)
GET    /super-admin/billing-settings           → SuperAdmin\BillingSettingsController@index
POST   /super-admin/billing-settings           → SuperAdmin\BillingSettingsController@update
POST   /super-admin/restaurants/{id}/grace     → SuperAdmin\RestaurantController@startGracePeriod
```

---

## Vistas

### Admin
- `resources/js/Pages/Settings/Subscription.vue` — plan actual, stats, botones de cambio, acceso al portal

### SuperAdmin
- `resources/js/Pages/SuperAdmin/BillingSettings.vue` — ajustes globales (grace days, reminder days)
- `resources/js/Pages/SuperAdmin/Restaurants/Show.vue` — expone el botón "Forzar período de gracia" + audits recientes

---

## Commands (cron)

Comandos Artisan corridos por scheduler:

| Comando | Propósito |
| --- | --- |
| `billing:check-grace` | Suspende restaurantes cuyo `grace_period_ends_at < now()` |
| `billing:check-canceled` | Suspende restaurantes cuyo `subscription_ends_at < now()` |
| `billing:backfill-plans` | One-off: asigna planes a restaurantes legacy según sus límites previos |

Schedule en `routes/console.php` (diariamente).

---

## Configuración (`.env`)

```env
CASHIER_CURRENCY=mxn

STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Opcional
CASHIER_LOGGER=stack
```

`config/cashier.php` — Cashier usa `/stripe/webhook` y Dompdf para invoices.

---

## Flujos críticos

1. **Iniciar suscripción (manual → gracia)**
   Usuario en `Settings/Subscription.vue` → `initiateSubscription()` → `transitionToSubscription()` + `startGracePeriod(14)` + asigna plan de gracia + audit

2. **Comprar plan (Stripe Checkout)**
   `checkout(Plan)` → sesión Stripe → redirección → usuario paga → webhook `checkout.session.completed` → `assignPlan()` + transición → `active`

3. **Cambio de plan (upgrade inmediato)**
   `swap(Plan)` → `swapAndInvoice()` → webhook `invoice.paid` sincroniza período

4. **Downgrade programado**
   `scheduleDowngrade(Plan)` → marca `pending_plan_id` → espera final de período → webhook `invoice.paid` detecta nuevo período → aplica el downgrade

5. **Falla de pago**
   Webhook `invoice.payment_failed` → `active` → `grace_period` (usando `payment_grace_days` del setting) → Stripe reintenta automático → si paga: webhook `invoice.paid` → `active` → si no: `billing:check-grace` lo suspende

6. **SuperAdmin fuerza gracia**
   `startGracePeriod($id, $days)` → cancela Stripe sub con `cancelNow()` si existe → transición local a `grace_period` → audit con flag `stripe_subscription_canceled`

7. **Bloqueo operacional en canales internos**
   En `Orders/Index.vue` y `Pos/Index.vue`, si `billing.can_operate === false`: banner rojo con `block_message` + botones "Crear pedido" / "Nueva venta" deshabilitados. **POS preserva cerrar ventas en curso** aunque el restaurante se suspenda (ver `14-pos.md`).

---

## Tests

- `tests/Feature/BillingTest.php` — planes, settings, audits, `canReceiveOrders()`, `canAccessPanel()`, límites efectivos, `LimitService`
- `tests/Feature/BillingCommandsTest.php` — los tres commands de cron
- `tests/Feature/StripeWebhookTest.php` — deduplicación, `invoice.paid`, `invoice.payment_failed`, `subscription.deleted`, customer desconocido, eventos sin id (9+ tests de hardening de Abr 2026)
- `tests/Feature/OperationalGateTest.php` — gate operacional en canales internos (manual + POS)

---

## Relación con otros módulos

- **`03-orders.md`** — el gate `canOperate()` bloquea creación manual
- **`14-pos.md`** — el POS respeta el gate pero preserva ventas en curso
- **`06-settings.md`** → sección Subscription — la UI del usuario
- **`07-superadmin.md`** → gestión de planes, settings y forzado de gracia
- **`docs/BILLING_SPEC.md`** — spec comercial (planes, precios, política de cobro)

---

_PideAqui / GuisoGo — Módulo 17: Billing SaaS — Marzo–Abril 2026_
