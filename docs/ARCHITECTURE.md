# PideAquí — Arquitectura del Sistema

> Versión: v3.0 — Abril 2026
> Refleja el estado post-MVP con billing Stripe, POS, cupones, edición de pedidos, fechas especiales y gastos.
> Para diagramas Mermaid (ER, rutas, flujos), ver [BACKEND_ARCHITECTURE_DIAGRAMS.md](./BACKEND_ARCHITECTURE_DIAGRAMS.md).

---

## 1. Visión general

PideAquí es una plataforma SaaS multi-restaurante. Tres interfaces + un backend:

```
┌──────────────────┬──────────────────────┬──────────────────────────┐
│  client/         │  admin/ (restaurante)│  admin/super (SaaS owner)│
│  Vue 3 SPA       │  Inertia + Vue 3     │  Inertia + Vue 3         │
│  (mobile first)  │  (desktop)           │  (desktop)               │
└────────┬─────────┴─────────┬────────────┴─────────┬────────────────┘
         │ REST + token      │ Inertia (SSR bridge) │ Inertia (SSR bridge)
         └───────────────────┴──────────────────────┘
                         ↓
                ┌──────────────────────┐
                │  Laravel 12 (PHP 8.4)│
                │  ├── Policies/Guards │
                │  ├── OrderService    │
                │  ├── PosSaleService  │
                │  ├── DeliveryService │
                │  ├── OrderEditService│
                │  ├── LimitService    │
                │  ├── CancellationSv. │
                │  └── StatisticsSv.   │
                └──────────┬───────────┘
                           ↓
         ┌─────────────────┼───────────────────────┐
         ↓                 ↓                       ↓
  ┌─────────────┐  ┌──────────────────┐  ┌──────────────────┐
  │PostgreSQL 18│  │ Laravel Reverb   │  │ Stripe (Cashier) │
  │(multitenant)│  │ WebSockets       │  │ Billing + Portal │
  └─────────────┘  └──────────────────┘  └──────────────────┘
```

Servicios externos: **Google Maps JS API** (frontend admin), **Google Distance Matrix API** (backend), **S3** (imágenes en producción), **SMTP** (NewOrderNotification).

---

## 2. Decisiones arquitectónicas clave

### 2.1 Admin + SuperAdmin: Laravel + Inertia + Vue 3 (sin API REST separada)

Los dos paneles administrativos comparten backend y emiten HTML server-driven con payload JSON vía Inertia. Esto evita duplicar validación, auth y policies en un API layer.

### 2.2 Cliente final: Vue 3 SPA universal + REST API resuelta por slug

El SPA del cliente (`pideaqui-front`) es un repo independiente, **un único bundle universal** desplegado para todos los restaurantes. El tenant se resuelve en runtime del URL `/r/{slug}`.

- Backend: middleware `ResolveTenantFromSlug` sobre el grupo `/api/public/{slug}/*` inyecta el `Restaurant` en `$request->attributes['restaurant']`. 404 si el slug no existe, 410 si el restaurante no puede recibir pedidos (`canReceiveOrders() === false`).
- Cliente: `axios` interceptor en `client/src/services/api.js` reescribe `/api/restaurant` → `/api/public/{slug}/restaurant` usando el slug actual de `window.location.pathname`.
- Aislamiento client-side: `AbortController` por tenant cancela requests en vuelo al cambiar de slug; `router.beforeEach` async bloquea navegación hasta `bootstrapTenant()` completo; guards de slug en stores (cart, order, restaurant) evitan contaminación cross-tenant entre navegaciones rápidas.
- El patrón legacy por `access_token` + header `X-Restaurant-Token` fue removido (columna dropeada 2026-04-22).

### 2.3 Landing: Nuxt 4 desacoplada (`landing-pideaqui`)

Sitio promocional en repo propio. No consume el backend. Se deploya estático en Vercel.

### 2.4 Multi-tenancy row-level (no schema-level)

Una sola base de datos. Toda tabla del dominio tiene FK `restaurant_id`. El aislamiento se asegura con:

1. **`TenantScope`** (global scope aplicado a modelos con el trait `BelongsToTenant`): filtra automáticamente por `restaurant_id` del usuario autenticado en el guard `web`. No se aplica en CLI, rutas de API, ni con guard `superadmin` activo.
2. **`EnsureTenantContext` middleware** (`tenant`): exige `restaurant_id` no nulo en el user autenticado; 403 si falta.
3. **Policies** en cada modelo del dominio: `user.restaurant_id == resource.restaurant_id`.

**Comportamiento cross-tenant:** retorna **404** (no 403) para ocultar la existencia del recurso — elegido a propósito.

### 2.5 Guards de autenticación

| Guard | Modelo | Tabla | Uso |
|---|---|---|---|
| `web` | `User` | `users` | Admin de restaurante y operadores (rol `admin` u `operator`) |
| `superadmin` | `SuperAdmin` | `super_admins` | Dueño de la plataforma SaaS |

El login SuperAdmin vive en `/super/login`, aislado del admin.

**Registro público (desde Abr 2026):** existe la ruta `POST /register` (guard `guest` + `throttle:3,1`) que permite a dueños de restaurante auto-registrarse en plan de gracia (14 días, 50 pedidos, 1 sucursal). Requiere verificación de correo obligatoria antes de acceder al dashboard — aplicada solo a `signup_source='self_signup'`; admins creados por SuperAdmin entran pre-verificados. Ver módulo `docs/modules/01-auth.md`.

### 2.6 Roles dentro del tenant

Dos roles en `users.role`:

- `admin`: acceso total al panel del restaurante.
- `operator`: acceso limitado. Ve pedidos y POS (de su sucursal asignada), pero no Settings, Cupones, Gastos, Suscripción, Estadísticas avanzadas. `branch_id` del user puede restringir qué sucursal ve.

### 2.7 Billing: Laravel Cashier con `Restaurant` como Billable

Decisión clave: el `Restaurant` es el `Billable`, no el `User`. Un restaurante puede tener múltiples usuarios; la suscripción pertenece al restaurante. Esto implica que `subscriptions.user_id` (columna estándar de Cashier) en realidad almacena `restaurants.id` — la migración `rename_user_id_to_restaurant_id_in_subscriptions_table` renombra la columna. Ver [modules/17-billing.md](./modules/17-billing.md).

Modos de cobro coexistentes:

- **`manual`**: SuperAdmin define `orders_limit` + período (`orders_limit_start`, `orders_limit_end`). Sin Stripe.
- **`subscription`**: plan + Stripe; límites y período vienen del plan + webhook Stripe.

El gate `Restaurant::canOperate()` decide si el restaurante puede recibir/registrar pedidos manualmente y POS. **No** bloquea al alcanzar `orders_limit` (capacidad ≠ estado del servicio).

**Política de `status=suspended` (post grace expiration):** decisión de diseño intencional — un restaurante suspendido **NO puede operar** (recibir pedidos del menú público, crear órdenes manuales desde admin, crear POS sales) pero **SÍ puede preparar su negocio**: editar el catálogo (productos, precios, categorías), branding (logo, colores), horarios, fechas especiales, cupones, promociones y gestionar su equipo. El objetivo es reducir la fricción de reactivación: cuando el dueño paga y vuelve a `status=active`, arranca con su catálogo al día en lugar de tener que reconstruir cambios. Los bloqueos efectivos al suspender viven en los servicios que sí gatean `canOperate()` / `canReceiveOrders()`: `OrderService::store`, `PosSaleService::store`, y el middleware `ResolveTenantFromSlug` que responde 410 en `/api/public/{slug}/*`. Los controllers de catálogo/branding/horarios intencionalmente **no** gatean canOperate. El paywall visible en el panel (prop `billing.must_show_billing` de `HandleInertiaRequests`) señala al usuario dónde está la acción pendiente sin restringirle las pantallas de preparación.

### 2.8 POS desacoplado del flujo de pedidos

El módulo POS (`/pos`) vive en tablas separadas (`pos_sales`, `pos_sale_items`, `pos_sale_item_modifiers`, `pos_payments`). **No consume `orders_limit`**. No usa `Customer`, `Coupon`, `Promotion`, `DeliveryService`. Sus eventos WebSocket viven en un canal privado distinto (`restaurant.{id}.pos`) para no contaminar el Kanban de pedidos.

### 2.9 WebSockets: Laravel Reverb, broadcast síncrono

Kanban de pedidos y POS se actualizan en tiempo real. Eventos marcados con `ShouldBroadcastNow` (síncrono, sin cola) para garantizar delivery inmediato. Si Reverb cae, admin wraps en try/catch: el cambio de status se persiste igualmente, solo se pierde la actualización en vivo.

Canales privados:

- `restaurant.{restaurantId}` — `OrderCreated`, `OrderStatusChanged`, `OrderUpdated`, `OrderCancelled`
- `restaurant.{restaurantId}.pos` — `PosSaleCreated`, `PosSaleStatusChanged`, `PosSaleCancelled`

Autorización en `routes/channels.php` (cada admin solo escucha su restaurante).

### 2.10 Delivery: pre-filtro Haversine + Google Distance Matrix

Decisión de costo: evitar múltiples llamadas a Distance Matrix.

- **1 sucursal activa** → 1 sola llamada a Google.
- **2+ sucursales** → Haversine (gratis) rankea por cercanía en línea recta → 1 sola llamada a Google para la sucursal top-1 para obtener distancia real por calles.
- **Sin fallback a Haversine** si Google falla. Lanza `DomainException` — preferimos fallar claro que cobrar envíos mal calculados.

### 2.11 Anti-tampering de precios

`OrderService` **siempre** recalcula `subtotal`, `delivery_cost`, `discount_amount` y `total` server-side. El `unit_price` enviado por el cliente se valida contra el snapshot del producto con tolerancia ±$0.01 (redondeo). `production_cost` nunca se expone a la API pública.

### 2.12 Snapshots históricos en order_items

`order_items.product_name`, `order_items.production_cost`, `order_item_modifiers.modifier_option_name`, `order_item_modifiers.production_cost` se copian al crear el pedido. Si el menú cambia después (precio, nombre, costo), los pedidos históricos conservan sus datos reales. `StatisticsService::netProfit()` siempre usa snapshots.

### 2.13 Concurrencia: `lockForUpdate` + optimistic lock

- **Límite de pedidos**: `LimitService::isOrderLimitReached()` se consulta dentro de una transacción con `lockForUpdate()` sobre el `Restaurant`. Evita la race condition clásica "dos pedidos simultáneos justo en el borde del límite".
- **Edición de pedidos**: `OrderEditService` usa `lockForUpdate()` + optimistic lock vía `expected_updated_at`. Si el cliente envía una versión stale, responde `409 Conflict`.

### 2.14 Auditoría

- **`order_audits`**: cada edición de pedido guarda `action`, `changes` (JSON diff), `reason`, totales antes/después, IP y user_id.
- **`billing_audits`**: cambios críticos de billing (gate bloqueado, fallback sin plan, grace extendido, etc.).
- **`stripe_webhook_events`** con unique constraint: deduplicación de webhooks idempotente. Si Stripe reenvía el mismo evento, no se aplica dos veces.

### 2.15 Límites tras el boundary

Solo se validan inputs externos (API pública, formularios admin, webhooks Stripe). No se revalidan datos internos que ya pasaron por Form Request o vienen de BD.

---

## 3. Módulos del dominio

Para detalle granular, ver [docs/modules/INDEX.md](./modules/INDEX.md). Resumen de qué vive dónde:

| Módulo | Áreas | Archivo |
|---|---|---|
| 01 — Auth | Login admin + superadmin, recuperación password | [01-auth.md](./modules/01-auth.md) |
| 02 — Dashboard | KPIs, utilidad neta (revenue − cost − expenses) | [02-dashboard.md](./modules/02-dashboard.md) |
| 03 — Pedidos | Kanban, detalle, edición, audit trail, manuales | [03-orders.md](./modules/03-orders.md) |
| 04 — Menú | Categorías, productos, modifiers inline + catálogo | [04-menu.md](./modules/04-menu.md) |
| 05 — Sucursales | CRUD + geolocalización, branch activo obligatorio | [05-branches.md](./modules/05-branches.md) |
| 06 — Configuración | General, branding, horarios, fechas especiales, suscripción, usuarios | [06-settings.md](./modules/06-settings.md) |
| 07 — SuperAdmin | CRUD restaurantes, planes, billing settings, reset token | [07-superadmin.md](./modules/07-superadmin.md) |
| 08 — Flujo cliente | SPA Vue 3, carrito, checkout, WhatsApp | [08-customer-flow.md](./modules/08-customer-flow.md) |
| 09 — Delivery | Haversine + Distance Matrix, rangos de envío | [09-delivery-service.md](./modules/09-delivery-service.md) |
| 10 — API pública | REST endpoints autenticados por token | [10-api.md](./modules/10-api.md) |
| 11 — Cancelaciones | Analytics de pedidos cancelados | [11-cancellations.md](./modules/11-cancellations.md) |
| 12 — Mapa operativo | Google Maps con markers de pedidos y sucursales | [12-map.md](./modules/12-map.md) |
| 13 — WebSockets | Reverb + Echo | [13-websockets.md](./modules/13-websockets.md) |
| 14 — POS | Caja mostrador, pagos mixtos, ticket imprimible | [14-pos.md](./modules/14-pos.md) |
| 15 — Promociones | Standalone (no descuento sobre productos) | [15-promotions.md](./modules/15-promotions.md) |
| 16 — Cupones | Código, fixed/percentage, min_purchase, max_uses | [16-coupons.md](./modules/16-coupons.md) |
| 17 — Billing | Stripe + Cashier, gate operacional, planes | [17-billing.md](./modules/17-billing.md) |
| 18 — Gastos | Registro operacional + categorías jerárquicas | [18-expenses.md](./modules/18-expenses.md) |

---

## 4. Capa de servicios (`app/Services`)

| Servicio | Responsabilidad |
|---|---|
| `OrderService` | Creación de pedidos: validación, anti-tampering, límites, totales, cupones, WhatsApp message, broadcast `OrderCreated` |
| `OrderEditService` | Edición post-creación con audit trail, lock optimista, rollback en `409` |
| `PosSaleService` | CRUD de ventas POS, pagos parciales, cierre automático cuando se paga el total |
| `DeliveryService` | Orquesta `HaversineService` + `GoogleMapsService`, aplica `DeliveryRange` |
| `HaversineService` | Distancia en línea recta entre coordenadas (pre-filtro, gratis) |
| `GoogleMapsService` | Wrapper de Distance Matrix API (distancia real por calles) |
| `LimitService` | Source of truth de límites: `isOrderLimitReached`, `limitReason`, `orderCountInPeriod` |
| `CancellationService` | Agregaciones de cancelaciones combinando orders + pos_sales |
| `StatisticsService` | KPIs del dashboard: revenue, costo, utilidad neta (cruza con expenses) |
| `PosTicketNumberService` | Generación secuencial de números de ticket con prefijo por sucursal |
| `Onboarding\RestaurantProvisioningService` | **Orquestador único del onboarding de restaurantes.** Crea Restaurant + User admin + 3 PaymentMethod stub (cash activo, terminal+transfer inactivos) + BillingAudit en una transacción. Reutilizado por `SuperAdmin\RestaurantController@store` (source `super_admin`) y `Auth\RegisterController@store` (source `self_signup`). Pre-verifica email cuando `source='super_admin'`. Acepta DTO `Onboarding\Dto\ProvisionRestaurantData` con `billingMode` (grace \| manual), `signup_source`, límites opcionales, slug opcional (valida disponibilidad via `SlugSuggester`). Maneja colisiones de slug con retry sobre `QueryException` de unique violation. |

Ningún servicio depende de `Request` o `Auth` directamente. Reciben los modelos como argumentos (testeable).

### 4.1 Scope `Restaurant::withPeriodOrdersCount()`

Para listados del SuperAdmin que necesitan contar pedidos del período por restaurante sin N+1, el modelo `Restaurant` expone el scope `withPeriodOrdersCount()` que agrega la columna virtual `period_orders_count` via subquery correlacionado:

```sql
SELECT restaurants.*, (
  SELECT COUNT(*) FROM orders
  WHERE orders.restaurant_id = restaurants.id
    AND orders.created_at BETWEEN restaurants.orders_limit_start AND restaurants.orders_limit_end
) AS period_orders_count
FROM restaurants
```

Usado en `SuperAdmin\RestaurantController@index` y en la métrica `alerts.orders_near_limit` del dashboard. Reemplaza el patrón previo de iterar en PHP con `LimitService::orderCountInPeriod()` por restaurante.

---

## 5. Middleware custom

| Middleware | Alias | Aplicación |
|---|---|---|
| `EnsureTenantContext` | `tenant` | Rutas admin autenticadas (exige `restaurant_id` en user) |
| `EnsureRole` | `role:admin` / `role:operator` | Rutas restringidas por rol dentro del tenant |
| `ResolveTenantFromSlug` | `tenant.slug` | Rutas `/api/public/{slug}/*`, resuelve tenant por URL (404 si no existe, 410 si no puede operar) |
| `HandleInertiaRequests` | default | Props compartidos: auth user, billing state, flash messages |

Registrados en `bootstrap/app.php` (no `Http/Kernel.php` — Laravel 12).

---

## 6. Eventos de broadcast

Todos implementan `ShouldBroadcastNow` (sin cola):

| Evento | Canal | Payload principal |
|---|---|---|
| `OrderCreated` | `restaurant.{id}` | id, status, delivery_type, totales, cliente, sucursal |
| `OrderStatusChanged` | `restaurant.{id}` | order + `previous_status` |
| `OrderUpdated` | `restaurant.{id}` | order completo (tras edición) |
| `OrderCancelled` | `restaurant.{id}` | order + `cancellation_reason` |
| `PosSaleCreated` | `restaurant.{id}.pos` | id, ticket_number, status, cajero, sucursal |
| `PosSaleStatusChanged` | `restaurant.{id}.pos` | sale + `previous_status` |
| `PosSaleCancelled` | `restaurant.{id}.pos` | sale + motivo |

El admin envuelve `broadcast()` en try/catch — si Reverb cae, el cambio de status se persiste igual.

---

## 7. Tareas programadas

Definidas en `routes/console.php` y ejecutadas por `schedule:run`:

| Comando | Frecuencia | Propósito |
|---|---|---|
| `billing:check-grace` | Diario 06:00 | Suspende restaurantes con gracia expirada |
| `billing:check-canceled` | Diario 06:05 | Suspende suscripciones canceladas cuyo período terminó |
| `billing:send-reminders` | Diario 08:00 | Email de recordatorio antes de expiración de gracia |
| `billing:reconcile` | Diario 03:00 | Sincroniza estado local con Stripe (detecta inconsistencias) |
| `billing:apply-pending-downgrades` | Cada hora | Aplica downgrades programados cuando pasan su fecha |

Comandos manuales (no schedule): `billing:sync-stripe` (crea Products/Prices), `billing:backfill-plans` (asigna planes a restaurantes legacy).

---

## 8. Stack técnico

| Capa | Tecnología | Versión |
|---|---|---|
| Lenguaje | PHP | 8.4 |
| Framework | Laravel | 12 |
| Base de datos | PostgreSQL | 18 |
| Entorno de desarrollo | Laravel Herd | 1.28+ |
| WebSockets | Laravel Reverb | 1.8 |
| Billing | Laravel Cashier | Stripe |
| Bridge SSR admin | Inertia.js | 2 |
| Frontend admin | Vue 3 + Tailwind v4 | Vite |
| Ruteo JS → Laravel | Ziggy | 2.6 |
| SPA cliente | Vue 3 + Pinia + vue-router | Vite 7 |
| Landing | Nuxt 4 (SSG) | Node 22+ |
| Testing | PHPUnit (no Pest) | 11 |
| Formatter | Laravel Pint | 1.x |

---

## 9. Seguridad

Auditoría aplicada en marzo 2026 (ver [STATUS.md](../STATUS.md) → sección auditoría). Controles activos:

- **Rate limiting**: `throttle:5,1` en login/password reset, `throttle:30,1` en `POST /api/orders`, `throttle:60,1` en POS y preview delivery.
- **Password hashing**: cast `hashed` en `User::$casts` (idempotente, detecta si ya es hash).
- **Upload de imágenes**: `mimes:jpeg,jpg,png,gif,webp` — SVG bloqueado explícitamente para evitar XSS en assets.
- **Request size caps**: `items max:50`, `quantity max:100`, `unit_price max:99999.99`, `modifier_option_id distinct`.
- **Cross-product modifier validation**: cada item valida que sus modifiers pertenezcan al producto/promoción que eligió (no se acepta modifier_id de otro producto).
- **Coupon stacking**: un cupón por pedido. Validación `min_purchase` al editar items (cupón se remueve si baja del mínimo).
- **Stripe webhook dedup**: tabla `stripe_webhook_events` con unique constraint previene doble aplicación de eventos.
- **Tenant isolation test coverage**: cada policy tiene test cross-tenant explícito.

---

## 10. Testing

**619 funciones `test_*` en 31 archivos** (conteo auditable al Abr 17, 2026 vía `grep -c "public function test_" tests/Feature/*.php`).

Distribución aproximada:

- Auth + tenant isolation: `AuthTest`, `TenantContextTest`
- CRUD admin: `BranchTest`, `MenuTest`, `ProductTest` (implícito), `SettingsTest`, `BrandingTest`
- Pedidos: `OrderAdminTest`, `OrderApiTest`, `OrderEditTest`, `OrderManualTest`, `OrderNotificationTest`
- POS: `PosSaleTest`
- Billing: `BillingTest`, `BillingCommandsTest`, `DowngradeTest`, `StripeWebhookTest`, `RestaurantOperationalGateTest`
- SuperAdmin: `SuperAdminTest`
- Post-MVP: `CouponTest`, `PromotionTest`, `SpecialDateTest`, `ModifierCatalogTest`, `ExpenseTest`, `CancellationTest`, `MapControllerTest`, `BroadcastingTest`, `CategoryAvailabilityTest`
- Servicios: `DeliveryServiceTest`, `LimitServiceTest`, `DashboardTest`

Ver [CONTRIBUTING.md](../CONTRIBUTING.md) para cómo correr tests.

---

## 11. Deployment

Cubierto exhaustivamente en [README.md](../README.md) — 3 opciones:

1. **Laravel Cloud** (recomendado) — con WebSocket cluster gestionado.
2. **Docker Compose en VPS** — `Dockerfile.prod` + `docker-compose.prod.yml` de referencia.
3. **Servidor tradicional** (Nginx + PHP-FPM + systemd para Reverb/queue).

Operaciones corrientes (backups, rotación de secrets, Stripe keys, runbook de incidentes): ver [OPERATIONS.md](./OPERATIONS.md).

---

## 12. Historial de la arquitectura

Para el changelog detallado de cada decisión: [CHANGELOG.md](../CHANGELOG.md). Para diagramas visuales: [BACKEND_ARCHITECTURE_DIAGRAMS.md](./BACKEND_ARCHITECTURE_DIAGRAMS.md).

---

_PideAquí — Arquitectura v3.0 — Abril 2026_
