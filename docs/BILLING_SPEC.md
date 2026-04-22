# Billing & Suscripciones — Spec de Diseño

> Fecha: 2026-03-30
> Estado: Aprobado para implementación
> Rama: `pagos_stripe`

---

## 1. Resumen ejecutivo

Evolucionar GuisoGo de gestión manual de límites a un modelo SaaS con planes fijos, suscripciones recurrentes y pagos automáticos vía Stripe.

**Decisiones clave:**

| Decisión | Respuesta |
|---|---|
| Modelo de pricing | Planes fijos con tiers (Básico, Pro, Enterprise) |
| Ciclos de facturación | Mensual y anual (con descuento anual) |
| Trial | No. Grace period funcional al inicio |
| Onboarding | SuperAdmin crea restaurante → restaurante elige plan y paga |
| Grace period | Configurable globalmente por SuperAdmin |
| Features por plan | No — todos los planes tienen todas las features. Solo varía pedidos y sucursales |
| Moneda | Solo MXN |
| Restaurantes existentes | Migran a plan con pago |
| Facturación fiscal | Solo recibos de Stripe por ahora (CFDI a futuro) |
| Override SuperAdmin | No hay overrides individuales — planes fijos para todos |
| Cambio de plan | El admin del restaurante lo hace self-service desde su panel |
| Reactivación | Self-service — el restaurante paga y se reactiva solo |

---

## 2. Enfoque técnico

**Cashier + capa local de planes.**

- `laravel/cashier` maneja toda la comunicación con Stripe (suscripciones, webhooks, checkout, portal, payment methods)
- Tabla `plans` local con los límites de negocio (pedidos, sucursales)
- Cada plan local se mapea 1:1 a un Stripe Product + Prices
- Los límites se resuelven siempre localmente (nunca consultando Stripe en runtime)
- Stripe es el motor de cobro, la BD local es la fuente de verdad para límites operativos

**Por qué este enfoque:**

1. Performance — la API de orders valida límites con `lockForUpdate()`, consultar Stripe en ese hot path es inaceptable
2. Cashier no se desperdicia — sigue manejando webhooks, checkout, portal, invoices, proration
3. Migración gradual — los campos legacy coexisten con `plan_id` hasta completar la migración

---

## 3. Modelo de datos

### 3.1 Tabla `plans`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | "Básico", "Pro", "Enterprise" |
| `slug` | string unique | "basico", "pro", "enterprise" |
| `description` | text nullable | Para mostrar en página de pricing |
| `orders_limit` | integer | Pedidos máximos por periodo de facturación |
| `max_branches` | integer | Sucursales permitidas |
| `monthly_price` | decimal(10,2) | Precio mensual en MXN |
| `yearly_price` | decimal(10,2) | Precio anual en MXN |
| `stripe_product_id` | string nullable | Stripe Product ID |
| `stripe_monthly_price_id` | string nullable | Stripe Price ID mensual |
| `stripe_yearly_price_id` | string nullable | Stripe Price ID anual |
| `is_default_grace` | boolean default false | true = plan de gracia (solo uno) |
| `is_active` | boolean default true | Visible para nuevas suscripciones |
| `sort_order` | integer default 0 | Orden en pricing |
| `timestamps` | | |

El plan de gracia es un registro normal con `is_default_grace = true`. No tiene Stripe IDs porque no se cobra.

### 3.2 Tabla `billing_settings`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `key` | string unique | Nombre de la configuración |
| `value` | string | Valor |
| `timestamps` | | |

Keys iniciales:
- `initial_grace_period_days` → "14"
- `payment_grace_period_days` → "7"
- `reminder_days_before_expiry` → "3,1"

### 3.3 Tabla `billing_audits`

| Columna | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | |
| `restaurant_id` | FK nullable → restaurants | |
| `actor_type` | string | 'super_admin', 'restaurant_admin', 'system', 'stripe' |
| `actor_id` | bigint nullable | ID del actor |
| `action` | string | Ver lista abajo |
| `payload` | jsonb | `{old: {...}, new: {...}}` |
| `ip_address` | string nullable | |
| `created_at` | timestamp | Solo created_at |

Acciones: `restaurant_created`, `plan_changed`, `subscription_started`, `subscription_canceled`, `payment_succeeded`, `payment_failed`, `grace_period_started`, `grace_period_extended`, `suspended`, `reactivated`, `disabled`, `enabled`

### 3.4 Tablas de Cashier (generadas automáticamente)

- `subscriptions` — Suscripciones activas
- `subscription_items` — Items de suscripción

### 3.5 Cambios a tabla `restaurants`

| Cambio | Detalle |
|---|---|
| **Agregar** `plan_id` | FK nullable → plans |
| **Agregar** `status` | string default 'incomplete'. Valores: `active`, `past_due`, `grace_period`, `suspended`, `canceled`, `incomplete`, `disabled` |
| **Agregar** `grace_period_ends_at` | timestamp nullable |
| **Agregar** `subscription_ends_at` | timestamp nullable |
| **Agregar** columnas Cashier | `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at` |
| **Mantener temporalmente** | `is_active`, `orders_limit`, `orders_limit_start`, `orders_limit_end`, `max_branches` |
| **Eliminar al final** | Los 5 campos anteriores (fase 6 de migración) |

### 3.6 Relaciones

```
Plan hasMany Restaurant
Restaurant belongsTo Plan
Restaurant hasMany BillingAudit
Restaurant hasOne Subscription (via Cashier Billable trait)
```

---

## 4. Estados del restaurante

### 4.1 Tabla de estados

| Estado | ¿Recibe pedidos? | ¿Admin accede al panel? | ¿Cómo se llega? |
|---|---|---|---|
| `active` | Sí | Sí | Pago exitoso |
| `past_due` | Sí | Sí (banner warning) | Webhook `invoice.payment_failed` |
| `grace_period` | Sí | Sí (banner urgente) | Reintentos agotados / restaurante recién creado |
| `suspended` | **No** | Sí (solo billing) | Grace vencido / canceled + periodo vencido |
| `canceled` | Sí (hasta fin periodo) | Sí | Admin o SuperAdmin cancela |
| `incomplete` | **No** | Sí (solo ve "completa suscripción") | Creado por SuperAdmin, nunca pagó |
| `disabled` | **No** | **No** | SuperAdmin toggle manual |

### 4.2 Diagrama de transiciones

```
SuperAdmin crea restaurante
  │
  ▼
┌──────────────┐
│ grace_period  │──── Elige plan + paga ────→┌──────────┐
└──────┬───────┘                             │  active   │
       │                                     └────┬─────┘
  Grace vence                                     │
  sin pago                             invoice.payment_failed
       │                                          │
       ▼                                          ▼
┌──────────────┐                          ┌─────────────┐
│  suspended    │←── Grace vence ─────────│  past_due    │
└──────────────┘                          └──────┬──────┘
       │                                         │
  Paga → active                          Reintentos agotados
                                                 │
                                                 ▼
                                          ┌─────────────┐
                                          │ grace_period │
                                          └──────┬──────┘
                                                 │
                                           Grace vence
                                                 │
                                                 ▼
                                          ┌─────────────┐
                                          │  suspended   │
                                          └─────────────┘

┌──────────────┐
│   canceled    │──── Fin periodo pagado ──→ suspended
└──────────────┘

┌──────────────┐
│   disabled    │←── SuperAdmin toggle (cualquier estado)
└──────────────┘
```

### 4.3 Resolución de permisos

```
canReceiveOrders() = status IN (active, past_due, grace_period)
                     OR (status == canceled AND subscription_ends_at > now())

canAccessPanel()   = status != disabled

mustShowBilling()  = status IN (suspended, incomplete)
```

---

## 5. Integración con Stripe

### 5.1 Mapeo de entidades

| Sistema local | Stripe | Relación |
|---|---|---|
| Restaurant (Billable) | Customer | 1:1 via `stripe_id` |
| Plan | Product | 1:1 via `stripe_product_id` |
| Plan precio mensual | Price (recurring/month) | 1:1 via `stripe_monthly_price_id` |
| Plan precio anual | Price (recurring/year) | 1:1 via `stripe_yearly_price_id` |
| Suscripción | Subscription | 1:1 via Cashier |

### 5.2 Webhooks

| Evento Stripe | Acción |
|---|---|
| `checkout.session.completed` | Asignar plan_id, status → active, registrar audit |
| `invoice.paid` | Si past_due/grace_period → status → active. Registrar audit |
| `invoice.payment_failed` | Status → past_due. Notificar admin. Registrar audit |
| `customer.subscription.updated` | Si reintentos agotados → status → grace_period, calcular grace_period_ends_at |
| `customer.subscription.deleted` | Status → suspended. Registrar audit |

### 5.3 Idempotencia

Cada webhook handler:
1. Verifica firma de Stripe (Cashier automático)
2. Checa si `event.id` ya fue procesado (via billing_audits o tabla dedicada)
3. Si duplicado → responde 200 sin acción
4. Si nuevo → procesa + registra
5. Siempre responde 200

### 5.4 Cron jobs

| Comando | Frecuencia | Función |
|---|---|---|
| `billing:check-grace` | Diario | grace_period con grace_period_ends_at < now() → suspended |
| `billing:check-canceled` | Diario | canceled con subscription_ends_at < now() → suspended |
| `billing:send-reminders` | Diario | Envía emails X días antes de vencer grace |
| `billing:reconcile` | Diario | Compara status local vs Stripe API → corrige discrepancias |

---

## 6. Flujos principales

### 6.1 SuperAdmin crea restaurante (nuevo flujo)

```
SuperAdmin → /super/restaurants/create (sin campos de límites)
  → Crea Restaurant + User + PaymentMethods
  → plan_id = plan de gracia (is_default_grace)
  → status = 'grace_period'
  → grace_period_ends_at = now() + billing_settings.initial_grace_period_days
  → billing_audit: restaurant_created
```

### 6.2 Restaurante elige plan y paga

```
Admin → Settings/Subscription
  → Ve planes disponibles (is_active = true, is_default_grace = false)
  → Elige plan + ciclo (mensual/anual)
  → Backend genera Stripe Checkout Session
  → Redirect a Stripe Checkout
  → Paga → webhook checkout.session.completed
  → plan_id = plan elegido, status = active
  → billing_audit: subscription_started
```

### 6.3 Cambio de plan (self-service)

```
Admin → Settings/Subscription → "Cambiar plan"
  → Elige nuevo plan + ciclo
  → Cashier: $restaurant->subscription('default')->swap(nuevo_price_id)
  → Proration automática (Stripe/Cashier)
  → plan_id = nuevo plan (inmediato)
  → Nuevos límites aplican inmediatamente
  → billing_audit: plan_changed
```

### 6.4 Cancelación

```
Admin → Settings/Subscription → "Cancelar"
  → Cashier: $restaurant->subscription('default')->cancel()
  → Cancela al final del periodo (no inmediato)
  → status = canceled, subscription_ends_at = fin de periodo
  → Sigue operando hasta subscription_ends_at
  → Cron detecta vencimiento → status = suspended
```

### 6.5 Cobro fallido → grace → suspensión

```
Stripe intenta cobrar → falla
  → Webhook: invoice.payment_failed → status = past_due
  → Stripe reintenta (~3 veces en 7-10 días)
  → Si reintento exitoso → invoice.paid → status = active
  → Si todos fallan → status = grace_period
  → grace_period_ends_at = now() + billing_settings.payment_grace_period_days
  → Cron diario: si grace_period_ends_at < now() → suspended
```

### 6.6 Reactivación self-service

```
Admin de restaurante suspendido → entra al panel
  → Ve página de billing (único acceso)
  → Elige plan (si no tenía) o actualiza método de pago
  → Stripe Checkout → paga
  → Webhook: invoice.paid → status = active
```

---

## 7. Estrategia de migración

### Fase 1 — Preparar infraestructura

- Crear tablas: plans, billing_settings, billing_audits
- Correr migraciones de Cashier
- Agregar columnas nuevas a restaurants (plan_id, status, grace_period_ends_at, subscription_ends_at)
- Seed: plan de gracia + planes comerciales + billing_settings
- **No se tocan los campos legacy**

### Fase 2 — LimitService dual

Refactorizar LimitService:
```
si restaurant.plan_id → usar plan.orders_limit / plan.max_branches
sino → usar restaurant.orders_limit / restaurant.max_branches (legacy)
```

### Fase 3 — Backfill restaurantes existentes

- Comando artisan `billing:backfill-plans`
- Asigna plan más cercano según límites actuales
- Actualiza status según is_active
- Registra billing_audits

### Fase 4 — Activar billing para existentes

- SuperAdmin envía invitaciones por email
- Restaurantes completan pago vía Stripe Checkout
- Grace period configurable para la migración

### Fase 5 — Activar flujo nuevo

- Formulario de creación del SuperAdmin sin campos de límites
- Plan de gracia + grace_period para restaurantes nuevos
- Settings/Subscription activo
- Cron jobs corriendo

### Fase 6 — Limpieza legacy

- Eliminar campos: orders_limit, orders_limit_start, orders_limit_end, max_branches, is_active
- Eliminar fallback en LimitService
- Eliminar Settings/Limits.vue y LimitsController
- Actualizar SuperAdmin UI

---

## 8. Cambios por componente

### Backend (Laravel)

- Instalar `laravel/cashier`
- Trait `Billable` en modelo Restaurant
- Modelo Plan, BillingSetting, BillingAudit
- Refactorizar LimitService
- StripeWebhookController (extends Cashier)
- PlanController (SuperAdmin CRUD)
- SubscriptionController (admin restaurante)
- Comandos artisan: billing:check-grace, billing:check-canceled, billing:send-reminders, billing:reconcile, billing:backfill-plans
- Notificaciones: GraceExpiringNotification, PaymentFailedNotification, SuspendedNotification
- Modificar `ResolveTenantFromSlug` (middleware de la API pública, alias `tenant.slug`): usar `canReceiveOrders()` — responde 410 si el restaurante no puede operar.
- Modificar `EnsureTenantContext`: redirigir a billing si suspended
- Modificar `HandleInertiaRequests`: compartir status y subscription info

### SuperAdmin panel

- Nueva sección: Planes (CRUD)
- Modificar creación de restaurante (sin límites manuales)
- Modificar vista de restaurante (mostrar suscripción, plan, estado billing)
- Nueva sección: Billing Settings (grace periods)
- Eliminar edición directa de límites

### Admin restaurante panel

- Nueva página: Settings/Subscription (elegir plan, cambiar plan, cancelar, ver facturas)
- Banner persistente según status (grace_period, past_due, suspended)
- Eliminar Settings/Limits.vue (reemplazado por Subscription)

### Client SPA

- Sin cambios en el código del client
- El middleware `ResolveTenantFromSlug` maneja la suspensión devolviendo **410 Gone** con `{code: "tenant_unavailable"}`. El SPA universal renderiza `TenantUnavailable.vue` al recibir ese código.
- El client ya muestra errores de API al usuario

---

## 9. Riesgos y mitigaciones

| Riesgo | Severidad | Mitigación |
|---|---|---|
| Desincronización BD/Stripe | Alta | Cron billing:reconcile + webhooks idempotentes |
| Webhooks perdidos/desordenados | Alta | Idempotencia + reconciliación + logging |
| Registro self-service abandonado | Media | No aplica — SuperAdmin crea todo |
| Testing de billing complejo | Media | Stripe test mode + factory helpers |
| Restaurant model pesado con Billable | Baja | Aceptable, monitorear |
| TenantScope + Cashier | Baja | No poner BelongsToTenant en tablas de Cashier |
| Grandfathering de precios | Baja | Política: suscriptores existentes mantienen precio hasta que cambien plan |
