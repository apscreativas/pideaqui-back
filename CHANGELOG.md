# CHANGELOG — PideAquí Backend (`pideaqui-back`)

> Historial consolidado por fecha descendente. Reemplaza las 34 entradas cronológicas que vivían en `STATUS.md`.
> Para estado actual, ver [STATUS.md](./STATUS.md).

---

## Abril 2026

### 2026-04-17

- **Documentación reorganizada**: docs se migraron al repo `admin/` (anterior carpeta `docs/` raíz del workspace eliminada). `ARCHITECTURE.md`, `PRD.md`, `DATABASE.md` consolidados bajo `admin/docs/`.
- **Landing page** (Nuxt 4) creada en repo independiente `landing-pideaqui`.

### 2026-04-15

- **Auto-generación de slug en SuperAdmin**: `RestaurantController::generateUniqueSlug()` aplica `Str::slug()` + sufijo `-2/-3` ante colisiones. Input manual de slug removido. Columna `slug` preservada (UNIQUE, expuesta en `RestaurantResource`). Tests actualizados.
- **Settings General sin redes sociales**: inputs de Instagram/Facebook/TikTok removidos de `Settings/General.vue`. Columnas DB preservadas (nullable, sin uso). 29 tests de Settings verdes.
- **Hardening Stripe**:
  - Deduplicación de webhooks vía tabla `stripe_webhook_events` (unique constraint).
  - `startGracePeriod()` cancela suscripción Stripe activa con `cancelNow()` antes de forzar gracia.
  - `SubscriptionController::checkout` rechaza restaurantes en modo manual o ya suscritos.
  - Fallback subscription-sin-plan genera `BillingAudit` además del log.
  - 9 tests nuevos en `StripeWebhookTest`. Total: 614 tests.
- **Gate operacional para canales internos**: `Restaurant::canOperate()` bloquea creación manual/POS cuando status ∈ {suspended, disabled, past_due, incomplete} o período manual vencido/no iniciado. POS preserva cerrar ventas en curso. **No bloquea por `orders_limit`.** Helper `BillingMessages` para textos. Banners rojos + botones disabled en UI. 605 tests pasando (+36).

### 2026-04-14

- **Escalabilidad POS + Cancelaciones**: cursor pagination POS, paginador clásico en Cancelaciones. KPIs en 1 query agregada. Fix `byBranch` con filtro de sucursal. Eliminación N+1. 3 índices nuevos (`orders(restaurant_id,cancelled_at)`, `pos_sales(restaurant_id,cancelled_at)`, `pos_payments(pos_sale_id,payment_method_type)`). 569 tests pasando.

### 2026-04-13

- **Módulo POS (caja mostrador)**: entidad separada (`pos_sales`, `pos_sale_items`, `pos_sale_item_modifiers`, `pos_payments`), pagos mixtos, ticket imprimible, Kanban POS, reporte de ventas. No consume `orders_limit`. Broadcast en canal aislado `restaurant.{id}.pos`.
- **Pedidos manuales desde Tablero admin**: campo `orders.source` = `api | manual`. Admin puede crear pedido sin pasar por SPA del cliente.

---

## Marzo 2026

### 2026-03-24 — Edición de pedidos post-creación

- Admins pueden editar pedidos en status `received` o `preparing`. Bloqueado en `on_the_way/delivered/cancelled`.
- Tabla nueva `order_audits` (action, changes JSON, reason, totales antes/después, IP, user_id).
- `OrderEditService` con `lockForUpdate` + optimistic lock vía `expected_updated_at` (409 Conflict si stale).
- Tres tipos de cambio: items, dirección, método de pago.
- `OrderUpdated` broadcast event (ShouldBroadcastNow).
- `UpdateOrderRequest`, `OrderPolicy::edit()`, página `Orders/Edit.vue`, historial collapsible en Show, badge "editado" en Index.
- 29 tests nuevos, 342 total.

### 2026-03-24 — Cupones de descuento por restaurante

- 2 tablas: `coupons` + `coupon_uses`. 3 columnas en `orders`: `coupon_id` (FK nullable), `coupon_code` (string snapshot), `discount_amount`.
- Fórmula: `total = subtotal - discount_amount + delivery_cost`. Descuento aplica SOLO al subtotal.
- `discount_amount` calculado server-side (anti-tampering).
- Admin CRUD con toggle, delete modal. API `POST /api/coupons/validate`.
- OrderEditService recalcula descuento al editar items (remueve si `min_purchase` no se cumple).
- Un cupón por pedido. Tracking por `customer_phone` (sin cuentas).
- Cancelled orders mantienen `coupon_use` (no se libera).
- 40 tests nuevos, 382 total.

### 2026-03-23 — Catálogo de modificadores reutilizables

- Sistema híbrido: inline (HasMany per producto) + catálogo a nivel restaurante.
- 3 tablas nuevas: `modifier_group_templates`, `modifier_option_templates`, `product_modifier_group_template` (pivot).
- Columnas agregadas: `is_active`, `max_selections` en `modifier_groups`/`modifier_options`.
- `Product::getAllModifierGroups()` mergea ambas fuentes con campo `source`.
- API acepta `modifier_option_id` (inline) o `modifier_option_template_id` (catalog). `order_item_modifiers` sin cambios — snapshot preserva datos.
- 269 tests (17 nuevos).

### 2026-03-23 — Fechas especiales y días festivos

- Tabla `restaurant_special_dates` con `date`, `type` (closed|special), opens_at, closes_at, label, is_recurring.
- `Restaurant::getResolvedScheduleForDate(Carbon)` con cadena de prioridad: special_date > regular schedule. Recurring matchea mes+día.
- API expone `closure_reason`, `closure_label`, `today_schedule`, `upcoming_closures`.
- OrderService valida `scheduled_at` contra horario resuelto.
- Admin Settings/Schedules con sub-sección + modal CRUD + botón "Festivos comunes" (7 holidays mexicanos).
- 16 tests nuevos.

### 2026-03-18 — Promociones rediseñadas

- **Cambio de modelo**: las promociones ya NO son descuentos sobre productos. Son items standalone con name, description, price, production_cost, active_days, starts_at/ends_at, is_active, sort_order.
- Eliminada tabla pivote `promotion_product`. Eliminados campos `discount_type`, `discount_value`.
- Agregado `promotion_id` FK nullable en `order_items` (`product_id` ahora nullable). Un order_item tiene product_id O promotion_id.
- API retorna promos en categoría virtual "Promociones" con `is_promotion: true`.
- `Cart.js` detecta `product.is_promotion` para enviar `promotion_id`.
- Componente `TimePicker.vue` reutilizable.

### 2026-03-13 — Snapshot histórico + Haversine fallback eliminado

- `order_items`: columnas `product_name`, `production_cost`. `order_item_modifiers`: `modifier_option_name`, `production_cost`.
- `StatisticsService::netProfit()` usa snapshot (no joins a tablas live).
- WhatsApp message usa snapshot names.
- `DeliveryService`: 1 sucursal → 1 Google call; 2+ → Haversine pre-filtra TOP 1 → 1 Google call. **Sin fallback a Haversine** — lanza `DomainException` si Google falla.
- Orders/Show.vue muestra `distance_km`, ganancia por item (verde), ganancia neta total.

### 2026-03-12 — Cancelaciones + WebSockets + Mapa + Email

- **Cancelaciones**: flujo completo backend + frontend con modal, razones predefinidas, columna en Kanban.
- **Reporte de Cancelaciones**: `CancellationService`, `CancellationController`, página con KPIs, motivos, por sucursal, por día, tabla. Sidebar con icon `cancel`. 12 tests.
- **WebSockets**: Laravel Reverb + Echo + pusher-js. 3 eventos (`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`). Canal privado `restaurant.{id}`. `BROADCAST_CONNECTION=reverb` en `.env`, `null` en `phpunit.xml`. 12 tests de broadcasting.
- **Mapa operativo**: `MapController`, `Map/Index.vue` con Google Maps JS API, markers coloreados por status, filtros, KPIs, info windows. 13 tests.
- **Email `NewOrderNotification`**: trait `Notifiable` + `routeNotificationForMail()` en Restaurant. Toggle `notify_new_orders`. 7 tests.
- **Multiple modifier selection** fix en cliente + validación cardinalidad backend.
- **Kanban UX**: horario programado + icono de pago en cards. Cliente: footer sticky, favicon dinámico, logo sin recorte.
- **Menú DnD reorder**: HTML5 nativo en Menu/Index.vue. `sort_order` auto en `max+1`. 10 tests nuevos en MenuTest.
- **Broadcast decoupling**: admin envuelve `broadcast()` en try/catch. Si Reverb cae, status se guarda igual.

### 2026-03-02 — Horarios por restaurante + Auditoría de seguridad

- **Horarios a nivel restaurante** (no sucursal): tabla `restaurant_schedules`. `Restaurant::isCurrentlyOpen()` con soporte overnight. API retorna `is_open` + `schedules`. DeliveryService usa `RestaurantSchedule`.
- **QR y token removidos del admin** (solo visibles en SuperAdmin).
- **Kanban drag-and-drop** HTML5 nativo con optimistic UI + validación transiciones.
- **Modifiers per-product** (inline): `modifier_groups.product_id` FK, `modifier_options.production_cost`. Pivote eliminado. Gestión inline en Products/Create y Edit.
- **Sucursal activa obligatoria**: `BranchController` valida no desactivar/eliminar la última activa.
- **Client SPA**: WhatsApp con Google Maps links, GPS error handling, time slots sin cap de 12, estado cerrado visual.
- **Payment method guard**: `UpdatePaymentMethodRequest` valida mínimo 1 método activo.
- **SuperAdmin tema claro**: sidebar y login unificados con estilo admin (bg-white). Filtros con `bg-[#FF5722]`.
- **Límites por periodo**: `max_monthly_orders` → `orders_limit` + `orders_limit_start/end` (date). `LimitService::limitReason()` retorna `null | period_not_started | period_expired | limit_reached`. API retorna `limit_reason`.
- **Auditoría de seguridad** (13+ fixes críticos):
  - Delivery cost server-side
  - TOCTOU fix con `lockForUpdate()`
  - Required modifiers validation
  - Inactive branch check
  - IDOR category_id scoped
  - Rate limiting login (`throttle:5,1`)
  - Password cast `hashed` (idempotente)
  - SVG uploads bloqueados (`mimes:jpeg,jpg,png,gif,webp`)
  - Overnight schedules soportados
  - Rate limiting API orders (`throttle:30,1`)
  - Cross-product modifier validation per-item
  - Duplicate `modifier_option_id` rechazado (`distinct`)
- `StoreOrderRequest` caps: `items max:50`, `quantity max:100`, `scheduled_at after:now`, `unit_price max:99999.99`.

---

## Febrero 2026

### 2026-02-27 (aprox.) — MVP completado

- Fases 1–10 entregadas. Backend Laravel + admin Inertia + SuperAdmin + cliente SPA + API pública.
- Integraciones: Google Maps JS, Google Distance Matrix, WhatsApp wa.me, S3, SMTP.
- Multi-tenancy row-level con `TenantScope` + `EnsureTenantContext` + 8 Policies.
- DeliveryService orquesta Haversine + Distance Matrix.
- OrderService con validación de límites, anti-tampering, WhatsApp message generation.
- Ziggy para `route()` en Vue.

---

_CHANGELOG — PideAquí Backend — Consolidado Abril 2026_
