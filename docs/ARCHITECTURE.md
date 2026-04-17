# PideAqui — Arquitectura del Sistema

> Decisiones técnicas y arquitectónicas del MVP.
> Versión: Fase 6 — Febrero 2026

---

## Visión General

PideAqui es una plataforma SaaS multi-restaurante con tres interfaces:

```
┌─────────────────────────────────────────────────────────────────┐
│                         PideAqui SaaS                           │
├──────────────────┬──────────────────────┬───────────────────────┤
│  client/         │  admin/              │  admin/ (SuperAdmin)  │
│  Vue 3 SPA       │  Laravel + Inertia   │  Laravel + Inertia    │
│  (por restaurante)│  + Vue 3            │  + Vue 3              │
└──────────────────┴──────────────────────┴───────────────────────┘
        ↕ REST API (token)      ↕ Inertia (SSR bridge)
                      Backend Laravel 12
                      PostgreSQL
```

---

## Decisiones Arquitectónicas Clave

### 1. Admin Panel: Laravel + Inertia.js + Vue 3 (SSR Bridge)

**Decisión:** Los paneles de administración (Admin Restaurante y SuperAdmin) usan Inertia.js como puente entre Laravel y Vue 3. Los controladores retornan `Inertia::render('PageName', $data)` en lugar de `view()`.

**Razón:**
- Sin necesidad de una API REST separada para el admin.
- El servidor mantiene el control del routing y autenticación.
- Vue 3 maneja la reactividad y UI del lado del cliente.
- Aprovecha las características de Laravel (middleware, policies, guards) sin duplicación.

**Estructura:**
```
admin/resources/js/
├── Pages/          ← Páginas Inertia (un .vue por ruta)
├── Components/     ← Componentes reutilizables Vue 3
└── app.js          ← Entry point (createInertiaApp)
```

**Patrón de controlador:**
```php
return Inertia::render('Menu/Index', [
    'categories' => CategoryResource::collection($categories),
]);
```

---

### 2. Frontend del Cliente: Vue 3 SPA + REST API (Token-based)

**Decisión:** El frontend del cliente es un proyecto independiente (`client/`) construido con Vite + Vue 3 que se comunica con el backend exclusivamente mediante API REST.

**Razón:**
- Se despliega una instancia por restaurante.
- El backend identifica al restaurante por el `access_token` en el header de cada request.
- Desacoplamiento total: el cliente puede actualizarse independientemente del backend.
- Permite alojar el frontend en CDN/static hosting.

**Autenticación:**
- Cada restaurante tiene un `access_token` único generado al crearse.
- El cliente SPA envía el token en cada request a la API: `Authorization: Bearer {token}`.
- El backend resuelve el restaurante y aplica el contexto de tenant.

---

### 3. Base de Datos: PostgreSQL — Multitenancy por `restaurant_id` FK

**Decisión:** Multitenancy de tipo "row-level" (discriminador por columna), no schema-level ni base de datos separada.

**Implementación:**
- Todas las tablas del dominio tienen una FK `restaurant_id` que apunta al restaurante propietario.
- Un `TenantMiddleware` inyecta el `Restaurant` del usuario autenticado en todas las queries del panel admin.
- En la API pública, el tenant se resuelve desde el `access_token` del restaurante.

**Razón:**
- Simplicidad operacional: una sola base de datos, una sola migración, un solo backup.
- PostgreSQL con índices en `restaurant_id` garantiza performance adecuada para el volumen esperado.
- Los Policies de Laravel garantizan que ningún admin pueda acceder a datos de otro restaurante.

---

### 4. Guards de Autenticación

**Dos guards:**

| Guard | Usuario | Acceso |
|---|---|---|
| `web` | `User` (Admin Restaurante) | Panel de administración de su restaurante |
| `superadmin` | `SuperAdmin` (modelo separado) | Panel SuperAdmin completo |

- El guard `web` autentica Admins de restaurante. Cada `User` está asociado a un `Restaurant`.
- El guard `superadmin` usa el modelo `SuperAdmin` con credenciales separadas. Sin registro público.
- El login de SuperAdmin está en `/super/login`, completamente separado del login de Admin.

---

### 5. Tenant Middleware + Global Query Scope

**Middleware:** `EnsureTenantContext` (`app/Http/Middleware/EnsureTenantContext.php`)

**Comportamiento:**
- Se aplica a todas las rutas del panel admin (excepto login/logout).
- Verifica que el usuario autenticado tenga `restaurant_id`. Si no, retorna 403.
- Registrado como alias `tenant` en `bootstrap/app.php`.

**Global Query Scope:** `TenantScope` (`app/Models/Scopes/TenantScope.php`)

- Se activa automáticamente en todos los modelos con el trait `BelongsToTenant`.
- Filtra por `restaurant_id` del usuario autenticado en el guard `web`.
- No se aplica en CLI, rutas de API, ni cuando el guard `superadmin` está activo.

```php
// app/Models/Scopes/TenantScope.php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->guard('web')->check()) {
            $builder->where($model->getTable().'.restaurant_id', auth()->guard('web')->user()->restaurant_id);
        }
    }
}
```

**Trait:** `BelongsToTenant` (`app/Models/Concerns/BelongsToTenant.php`)

Aplicado a los modelos con `restaurant_id` directo:
- `Branch`, `Category`, `Product`
- `PaymentMethod`, `DeliveryRange`, `Order`

Los modelos sin `restaurant_id` directo (`ModifierGroup`, `ModifierOption`, `OrderItem`, `OrderItemModifier`) se acceden siempre a través de su relación padre, que ya está scopeada. `RestaurantSchedule` tiene `restaurant_id` directo y usa `BelongsToTenant`.

**Para el panel SuperAdmin** (Fase 9), usar `withoutGlobalScope(TenantScope::class)` cuando se necesite acceso cross-tenant.

---

### 6. Cálculo de Delivery: Haversine + Google Distance Matrix

**Flujo de detección de sucursal más cercana:**

```
Cliente → Coordenadas GPS
    ↓
Pre-filtro Haversine (sin costo, en PHP/DB)
    → Descarta sucursales lejanas
    → Conserva top 1 candidata (MAX_CANDIDATES=1)
    ↓
Google Distance Matrix API (solo para candidata)
    → Distancia real por calles
    → Tiempo estimado
    → Sin fallback: DomainException si Google falla
    ↓
Sucursal más cercana asignada
    ↓
Costo de envío por rango de distancia
```

**Razón:**
- Haversine es gratis y rápido (distancia en línea recta), ideal para pre-filtrar.
- Google Distance Matrix tiene costo por elemento consultado; se minimiza consultando solo 1 candidata.
- El costo de envío se calcula buscando en qué rango de `delivery_ranges` cae la distancia real.
- No hay fallback a Haversine: si Google Maps falla o retorna distancia inalcanzable (`PHP_FLOAT_MAX`), se lanza `DomainException`. Esto garantiza que los costos de envío siempre se basan en distancia real de conducción.

**Nota:** Si el restaurante tiene una sola sucursal activa, se asigna directamente sin pre-filtro Haversine, pero se llama a Google Maps para obtener la distancia real.

---

### 7. Dirección del Cliente: Sin Geocoding Inverso

**Decisión:** La dirección del cliente se ingresa **manualmente** en un formulario de texto. El pin del mapa (Google Maps) solo obtiene coordenadas.

**Razón:**
- El geocoding inverso (coordenadas → dirección de texto) tiene costo adicional en la API de Google.
- La dirección obtenida por geocoding inverso suele ser imprecisa en México (colonias, calles locales).
- El cliente conoce mejor su dirección que la API.

**Implementación:**
- El mapa muestra un pin arrastrable para que el cliente ajuste su ubicación exacta.
- Las coordenadas del pin se usan para calcular distancia y asignar sucursal.
- El formulario tiene campos: calle/número/colonia/ciudad (texto libre) + campo de referencias.

---

### 8. Billing y Suscripciones: Cashier + Planes Locales

**Decisión:** Modelo SaaS con planes fijos, suscripciones recurrentes y pagos automáticos vía Stripe. Laravel Cashier maneja la comunicación con Stripe. Una tabla `plans` local almacena los límites de negocio.

**Implementación** (completa, ver [docs/modules/17-billing.md](./modules/17-billing.md)):
- `plans`: catálogo de planes con `orders_limit`, `max_branches`, precios, Stripe IDs.
- **`Restaurant` es el `Billable` de Cashier** (no el `User`) — un restaurante tiene varios admins.
- `LimitService` refactorizado: consulta plan primero, fallback a campos legacy.
- `billing_settings`: configuración global (grace period days, payment grace days).
- `billing_audits`: registro inmutable de todas las acciones de billing.
- `stripe_webhook_events`: deduplicación de webhooks (unique `stripe_event_id`, `insertOrIgnore`).
- Dos modos: `billing_mode ∈ {manual, subscription}`. Manual usa `orders_limit_start/end`; subscription sincroniza con Stripe.
- Estados del restaurante: `active`, `past_due`, `grace_period`, `suspended`, `canceled`, `incomplete`, `disabled`.
- Spec completo: `docs/BILLING_SPEC.md`

**Razón:**
- Los límites se resuelven localmente (sin consultar Stripe en runtime).
- Cashier maneja webhooks, checkout, portal, proration — no se reimplementa.
- Migración gradual: campos legacy coexisten con plan_id hasta completar la transición.
- Sin overrides individuales — planes fijos para todos (simplicidad).

**Flujo de onboarding:**
1. SuperAdmin crea restaurante → se asigna plan de gracia + grace period.
2. Restaurante opera con límites bajos durante el grace period.
3. Admin elige plan y paga vía Stripe Checkout.
4. Cobros automáticos recurrentes (mensual/anual).
5. Cobro fallido → past_due → grace_period → suspended.

---

### 8b. Gate Operacional: `Restaurant::canOperate()`

**Decisión:** Hay un único punto de decisión (`Restaurant::canOperate()`) que determina si un restaurante puede crear pedidos/ventas a través de los canales internos (admin manual, POS). La decisión se expone a Inertia vía `HandleInertiaRequests` como props globales (`billing.can_operate`, `billing.block_reason`, `billing.block_message`), y también se enforcea en el backend en los controllers correspondientes.

**Bloquea si:**
- `status ∈ {disabled, suspended, incomplete, past_due}`
- Modo subscription y `subscription_ends_at < now()`
- Modo manual y `orders_limit_end < now()` (período vencido) o `orders_limit_start > now()` (no iniciado)

**No bloquea** por alcanzar `orders_limit` — eso es una preocupación de capacidad, no de estado operativo. El POS tampoco se bloquea por límite; sólo el canal de API pública sí.

**Preservación de ventas en curso:** el módulo POS permite **cerrar ventas ya iniciadas** (cobrar, imprimir, marcar como pagadas) aunque el restaurante se suspenda mientras hay carritos abiertos. Sólo se bloquea la creación de ventas *nuevas*.

**Razón:** separar "puedo cobrar" de "estoy al día en mi cuenta" permite que el equipo de cocina no se vea interrumpido por un problema de facturación en mitad de un servicio, y evita que un admin quede atrapado con ventas imposibles de cerrar.

---

### 8c. Dedup de Webhooks de Stripe

**Decisión:** Stripe reintenta webhooks ante cualquier respuesta distinta a 2xx. El controller dedup´a cada evento usando la tabla `stripe_webhook_events` con unique constraint en `stripe_event_id`:

```php
$inserted = StripeWebhookEvent::insertOrIgnore([
    'stripe_event_id' => $event->id,
    'type' => $event->type,
]);
if ($inserted === 0) return response('duplicate', 200);
```

**Razón:** sin dedup, eventos como `invoice.paid` podían aplicarse dos veces (contando doble un período, re-asignando plan, etc.). El patrón es más simple que locks distribuidos y sobrevive a reinicios de la app.

**Fallback suscripción-sin-plan:** si llega un webhook cuyo `price_id` no está en `plans`, se asigna la suscripción pero se deja `plan_id = null` y se registra en `BillingAudit` con acción `subscription_assigned_without_plan` — visible al SuperAdmin para resolución manual.

---

### 9. Capa de Servicios y DTOs

**Decisión:** La lógica de negocio compleja vive en `app/Services/`. Los datos de retorno estructurados usan DTOs `readonly` en `app/DTOs/`.

**Servicios implementados:**
| Servicio | Responsabilidad |
|---|---|
| `HaversineService` | Distancia en línea recta entre coordenadas (usado solo para pre-filtro en DeliveryService) |
| `GoogleMapsService` | Wrapper de Google Distance Matrix API (inyectable, mockeable en tests) |
| `DeliveryService` | Orquesta el flujo de 7 pasos para calcular sucursal + costo + horario. Sin fallback: falla si Google Maps no responde |
| `LimitService` | Verifica límites de pedidos y sucursales (plan → legacy fallback) |
| `OrderService` | Crea pedidos (API + manuales): valida, calcula totales en backend, snapshots, valida cupón con `lockForUpdate`, persiste, genera mensaje WhatsApp |
| `OrderEditService` | Edita pedidos existentes: optimistic lock (`expected_updated_at`) + `lockForUpdate`, re-snapshot de precios, diff estructurado en `order_audits`, recalculo de cupón |
| `CancellationService` | KPIs de cancelaciones: tasa, motivo top, desglose por razón/sucursal/día |
| `StatisticsService` | KPIs del dashboard y ganancia neta. Usa snapshot columns de order_items/order_item_modifiers (no joins a catálogo) |
| `Support\BillingMessages` | Helper para textos en español de `block_reason` (usado por canOperate + Inertia props) |

**DTOs `readonly`:**
| DTO | Uso |
|---|---|
| `DeliveryResult` | Resultado del cálculo de delivery (branch, distancia, costo, cobertura, horario) |
| `OrderCreatedResult` | Resultado de creación de pedido (order + whatsapp_message) |

**Patrón de inyección:** Los servicios se inyectan en controllers vía constructor. `GoogleMapsService` se puede mockear en tests con `$this->instance(GoogleMapsService::class, $mock)`.

**Anti-tampering en OrderService:** Los precios del request se validan contra la DB (tolerancia ±$0.01). El subtotal y total siempre se recalculan en backend — nunca se confía en los valores del cliente.

**Snapshot pattern:** Al crear un pedido, `OrderService` copia `product_name` y `production_cost` a `order_items`, y `modifier_option_name` y `production_cost` a `order_item_modifiers`. Esto desacopla los reportes financieros y el detalle de pedido de cambios posteriores en el catálogo (renombrar producto, ajustar costo). El mismo patrón aplica a promociones (se copia igual en `order_items`) y a modifiers del catálogo (`modifier_option_id = null` + snapshot de nombre/precio/costo). `StatisticsService.netProfit()` y `Orders/Show.vue` usan exclusivamente estos snapshots.

**Optimistic + pesimistic lock en edición de pedidos:** `OrderEditService::update()` usa **ambos** niveles de lock:
- **Optimistic** — compara `$order->updated_at` con `$validated['expected_updated_at']`. Si difieren → `HttpException(409)`. Detecta ediciones concurrentes entre admins sin costo de DB.
- **Pesimistic** — dentro de la transacción, `lockForUpdate()` en la orden + re-valida estado. Protege la operación atómica de re-snapshot + actualización de totales + creación de audit.

El cupón se re-valida con `lockForUpdate()` en `coupons` y `coupon_uses` también, para evitar que el mismo cupón se aplique simultáneamente en dos pedidos cerca del límite.

---

### 10. Broadcasting Desacoplado (Reverb)

**Decisión:** Los broadcasts de eventos (`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`, `OrderUpdated`) son `ShouldBroadcastNow` (síncronos), pero los controllers envuelven cada llamada a `broadcast()` en `try/catch` y loggean warning si falla.

**Razón:** el panel admin depende de WebSockets sólo como UX — si Reverb no está corriendo (por ejemplo, durante un deploy o en un entorno donde no se quiera montar el daemon), los cambios de estatus se siguen guardando en DB y funcionan con recarga manual. El sistema nunca falla por un problema de transport.

**Canal privado:** `restaurant.{restaurantId}` — autenticado en `routes/channels.php` contra el `restaurant_id` del usuario.

**Echo en admin:** `Orders/Index.vue` y `Pos/Index.vue` se suscriben en `onMounted` y limpian en `onUnmounted`. El frontend usa `axios` como `authorizer` para el canal privado (CSRF meta tag + sesión).

---

### 11. Escalabilidad: Paginación por Cursor + Índices

**Decisión:** Las listas que crecen sin límite (historial de POS, pedidos cancelados, audits) usan **cursor pagination** en vez de offset. Los KPIs se resuelven en **una sola query agregada** (no N+1 ni post-procesamiento en PHP).

**Refactor de Abril 2026** (ver `memory/project_pos_cancellations_scalability.md`):
- 3 índices nuevos: `orders(restaurant_id, cancelled_at)`, `pos_sales(restaurant_id, cancelled_at)`, `pos_payments(pos_sale_id, payment_method_type)`
- Rangos de fechas con timestamps (no `whereDate()`), para que el planner use los índices
- Orden estable con tiebreaker `id DESC`
- Broadcast local sin `router.reload` (deduplica y actualiza sólo el card afectado)

---

## Estructura de Carpetas del Proyecto

```
PideAqui/
├── docs/               ← Documentación compartida (este directorio)
│   ├── PRD.md
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── CHANGELOG.md
│   ├── API-REFERENCE.md
│   └── modules/        ← 14 archivos de módulos
├── admin/              ← Laravel 12 + Inertia + Vue 3 (paneles admin)
│   ├── app/
│   │   ├── DTOs/           ← Value objects readonly (DeliveryResult, OrderCreatedResult)
│   │   ├── Services/       ← Lógica de negocio (DeliveryService, OrderService, etc.)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/  ← Controllers API pública
│   │   │   ├── Middleware/       ← AuthenticateRestaurantToken, EnsureTenantContext
│   │   │   ├── Requests/         ← Form Requests (validación)
│   │   │   └── Resources/        ← Eloquent API Resources
│   │   ├── Models/
│   │   │   ├── Concerns/   ← Traits (BelongsToTenant)
│   │   │   └── Scopes/     ← Query Scopes (TenantScope)
│   │   └── Policies/
│   ├── resources/
│   │   ├── js/
│   │   │   ├── Pages/      ← Páginas Inertia (.vue)
│   │   │   ├── Components/ ← Componentes Vue 3
│   │   │   └── app.js      ← Entry point Inertia
│   │   ├── css/app.css
│   │   └── views/
│   │       └── app.blade.php  ← Root template Inertia
│   └── vite.config.js
├── client/             ← Vite + Vue 3 SPA (frontend del cliente)
│   ├── src/
│   │   ├── views/
│   │   ├── components/
│   │   ├── router/
│   │   └── stores/    ← Pinia stores
│   └── vite.config.js
├── PRD.md
└── STATUS.md
```

---

_PideAqui — Arquitectura v1.2 — Marzo 2026_
