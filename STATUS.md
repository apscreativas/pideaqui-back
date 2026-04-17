# PideAqui — Estado del Proyecto

> Documento de estado actualizado el 1 de marzo de 2026.
> Auditado contra el código real del repositorio `admin/`.
> Actualizado el 2 de marzo de 2026 — Mejoras admin + fix bugs cliente.
> Actualizado el 2 de marzo de 2026 (2) — Horarios por restaurante, QR removido, estado cerrado cliente.
> Actualizado el 2 de marzo de 2026 (3) — Modificadores por producto (inline), sucursal activa obligatoria.
> Actualizado el 2 de marzo de 2026 (4) — SuperAdmin tema claro, límites por periodo configurable.
> Actualizado el 2 de marzo de 2026 (5) — Auditoría de seguridad: 7 fixes críticos + 8 fixes de alta severidad.
> Actualizado el 2 de marzo de 2026 (6) — Auditoría de seguridad: 6 fixes de severidad media.
> Actualizado el 3 de marzo de 2026 — Mejoras UX admin settings + SuperAdmin consistencia + regenerar token.
> Actualizado el 12 de marzo de 2026 — Fix distancia de delivery: Google Maps en todos los flujos.
> Actualizado el 12 de marzo de 2026 (2) — UX: advertencias de configuración incompleta (horarios admin, sucursales cliente, error menú).
> Actualizado el 12 de marzo de 2026 (3) — UX: mejora de carga de imágenes (hints, validación client-side, mensajes en español).
> Actualizado el 12 de marzo de 2026 (4) — Fix: selección múltiple de modificadores en cliente + validación de cardinalidad en backend.
> Actualizado el 12 de marzo de 2026 (5) — Kanban: horario programado + icono de pago en cards. Cliente: footer sticky, favicon dinámico, logo sin recorte.
> Actualizado el 12 de marzo de 2026 (6) — Cancelación de pedidos: flujo completo backend + frontend con modal, razones predefinidas y columna en Kanban.
> Actualizado el 12 de marzo de 2026 (7) — Sección Cancelaciones: reporte con KPIs, motivos, por sucursal, por día y tabla de pedidos cancelados.
> Actualizado el 12 de marzo de 2026 (8) — WebSockets en tiempo real: Laravel Reverb + Echo, Kanban se actualiza al crear/avanzar/cancelar pedidos.
> Actualizado el 13 de marzo de 2026 (2) — Mapa interactivo: MapController + Map/Index.vue con Google Maps JS API, markers de pedidos/sucursales, KPIs, filtros.
> Actualizado el 13 de marzo de 2026 (3) — Snapshot columns en order_items/order_item_modifiers, Haversine fallback eliminado, profit en detalle de pedido.
> Actualizado el 13 de marzo de 2026 (4) — Broadcast decoupling, mapa operativo rediseño, menú drag-and-drop reorder.
> Actualizado el 24 de marzo de 2026 — Edición de pedidos post-creación + Audit trail.
> Actualizado el 13 de abril de 2026 — Pedidos manuales desde el Tablero (admin) + campo `orders.source`.
> Actualizado el 13 de abril de 2026 (2) — Módulo POS (caja rápida) — entidad separada (`pos_sales`/`pos_sale_items`/`pos_sale_item_modifiers`/`pos_payments`), pagos mixtos, ticket imprimible, kanban POS, reporte de ventas. NO consume límite del plan.
> Actualizado el 14 de abril de 2026 — Historial POS y Cancelaciones: refactor de escalabilidad. Cursor pagination en POS, paginación clásica en Cancelaciones, KPIs en 1 query agregada, fix `byBranch` con filtro de sucursal, eliminación de N+1, 3 índices nuevos (`orders(restaurant_id,cancelled_at)`, `pos_sales(restaurant_id,cancelled_at)`, `pos_payments(pos_sale_id,payment_method_type)`), rango de fechas con timestamps (no `whereDate`), orden estable con tiebreaker `id DESC`, broadcast local sin router.reload. **569 tests pasando**.
> Actualizado el 15 de abril de 2026 — Gate operacional en canales internos (manual orders + POS). `Restaurant::canOperate()` bloquea creación cuando status no operacional (suspended, disabled, past_due, incomplete) o período manual vencido/no iniciado. POS preserva cerrar ventas en curso aunque el restaurante se suspenda. Alcanzar `orders_limit` NO bloquea POS. Helper `BillingMessages` para textos en español. Prop global `billing.can_operate`/`block_reason`/`block_message` en Inertia. Banners rojos + botones disabled en Pages/Pos/Index.vue y Pages/Orders/Index.vue. **605 tests pasando (+36 nuevos)**.
> Actualizado el 15 de abril de 2026 (2) — Hardening de Stripe: deduplicación de webhooks vía tabla `stripe_webhook_events` con unique constraint (idempotencia ante reintentos), `startGracePeriod` ahora cancela suscripción Stripe activa con `cancelNow()` antes de forzar gracia, `SubscriptionController::checkout` rechaza restaurantes en modo manual o ya suscritos (evita estado inconsistente billing_mode+stripe), fallback subscription-sin-plan ahora genera BillingAudit visible al SuperAdmin además del log, 9 tests nuevos en `StripeWebhookTest.php` cubriendo dedup, invoice.paid/payment_failed, subscription.deleted, customer desconocido, eventos sin id. **614 tests pasando**.
> Actualizado el 15 de abril de 2026 (4) — **Refactor: slug auto-generado en SuperAdmin/Restaurants/Create**. Removido input "Slug (URL amigable)" del formulario (UI engañoso porque `pideaqui.com/{slug}` no existe como ruta). Backend auto-genera el slug desde `name` con `Str::slug()` + sufijo `-2`/`-3`/… si colisiona (helper `RestaurantController::generateUniqueSlug()`). Removida la regla `slug` de `CreateRestaurantRequest` y mensajes asociados. **Escenario B** — columna `slug` (UNIQUE) permanece en DB, sigue expuesta en `RestaurantResource` y visible como sub-label en SuperAdmin Show/Index. Cliente SPA nunca usó slug. Tests actualizados: `test_create_restaurant_fails_with_duplicate_slug` reescrito como `test_auto_generates_unique_slug_when_name_collides`; otros 3 tests del store endpoint dejaron de enviar `slug`. **621 tests pasando**. Doc: `07-superadmin.md` actualizada (campos del Create + Form Requests).
> Actualizado el 15 de abril de 2026 (3) — **Refactor: Settings General sin redes sociales**. Removidos inputs/labels de `instagram`/`facebook`/`tiktok` en `Settings/General.vue`, fuera de `useForm`. Backend: removidos del `$fillable` de `Restaurant`, de las reglas de `UpdateGeneralSettingsRequest`, y del `only()` en `SettingsController::general()`. **Opción A — columnas DB conservadas** (nullable, sin uso) porque la migración `add_branding_columns_to_restaurants_table` referencia `->after('tiktok')` posicionalmente. API pública nunca las expuso, factories/seeders/tests no las usaban. **0 tests rotos** (29 tests de Settings verdes). Actualizado `docs/modules/06-settings.md` agregando secciones faltantes: Personalización (`/settings/branding`), Usuarios (`/settings/users`) y Suscripción (`/settings/subscription`); modelos `Plan`/`BillingSetting`/`BillingAudit` añadidos a la tabla; rutas y Form Requests completos.

---

## Resumen Ejecutivo

El proyecto tiene **Fases 1–12 completadas** y el MVP está **100% funcional**. Incluye: base de datos, modelos Eloquent, autenticación, multitenancy, panel de administración completo, API pública, DeliveryService, panel SuperAdmin, SPA del cliente e integraciones externas. Además se realizaron mejoras post-MVP: modificadores por producto con gestión inline, production_cost en opciones de modificadores, guard de sucursal activa obligatoria, fix de payload de órdenes del cliente, simplificación del flujo de delivery, barras de acción inferiores en admin y validación de método de pago mínimo activo.

| Área | Estado | Avance |
|---|---|---|
| Infraestructura y entorno | ✅ Completo | 100% |
| Base de datos — migraciones (13 tablas dominio, sin pivote) | ✅ Completo | 100% |
| Modelos Eloquent (15 modelos con relaciones) | ✅ Completo | 100% |
| Autenticación Admin + SuperAdmin (guards) | ✅ Completo | 100% |
| Middleware multitenancy + 8 Policies | ✅ Completo | 100% |
| Vistas de login (Admin y SuperAdmin) | ✅ Completo | 100% |
| Ziggy — `route()` en Vue | ✅ Completo | 100% |
| Panel Admin — Menú (categorías + productos) | ✅ Completo | 100% |
| Panel Admin — Modificadores | ✅ Completo | 100% |
| Panel Admin — Sucursales | ✅ Completo | 100% |
| Panel Admin — Horarios del Restaurante | ✅ Completo | 100% |
| API pública — Restaurante, Menú, Sucursales | ✅ Completo | 100% |
| DeliveryService — Google Maps driving distance + Rangos | ✅ Completo | 100% |
| API — Crear Pedido + WhatsApp message | ✅ Completo | 100% |
| Panel Admin — Dashboard (KPIs, gráficos, últimos pedidos) | ✅ Completo | 100% |
| Panel Admin — Pedidos (Kanban + detalle + avance status) | ✅ Completo | 100% |
| Panel Admin — Configuración (6 secciones + horarios) | ✅ Completo | 100% |
| Panel SuperAdmin (dashboard, CRUD restaurantes, estadísticas) | ✅ Completo | 100% |
| SPA Cliente — Flujo completo (c_01–c_06) | ✅ Completo | 100% |
| Panel Admin — Mapa interactivo (pedidos + sucursales) | ✅ Completo | 100% |
| Panel Admin — Catálogo de Modificadores (CRUD + vincular a productos) | ✅ Completo | 100% |
| Fechas especiales y días festivos (holidays + special hours) | ✅ Completo | 100% |
| Panel Admin — Edición de pedidos + Audit trail | ✅ Completo | 100% |
| Panel Admin — Crear pedidos manuales desde el Tablero | ✅ Completo | 100% |
| Panel Admin — POS (caja rápida + kanban + reportes) | ✅ Completo | 100% |
| Tests backend (528 tests pasando) | ✅ Completo | 100% |
| Integraciones externas | ✅ Completo | 100% |
| Mejoras post-MVP (many-to-many, UX, bugs) | ✅ Completo | 100% |
| **Total del MVP** | **Completo** | **100%** |

---

## Stack Técnico

| Tecnología | Versión | Estado |
|---|---|---|
| PHP | 8.5 | Configurado en Docker |
| Laravel | v12 | Instalado |
| PostgreSQL | — | Corriendo en Sail |
| Laravel Sail | v1 | Activo |
| Laravel Boost | v2 | Instalado y configurado |
| Tailwind CSS | v4 | Instalado (Vite) |
| Inertia.js | v2 | Instalado (admin/) |
| Vue 3 | v3 | Instalado (admin/ + client/) |
| Ziggy | v2.6 | Instalado — `route()` disponible en Vue |
| @vitejs/plugin-vue | — | Instalado (admin/) |
| Vite + Vue 3 SPA | — | Scaffolded (client/) |
| PHPUnit | v11 | Instalado |
| Laravel Pint | v1 | Instalado |

### Reglas de programación activas (CLAUDE.md / Laravel Boost)

- Todo comando debe ejecutarse via `./vendor/bin/sail` (PHP, Artisan, Composer, NPM).
- Crear archivos con `sail artisan make:` siempre que sea posible.
- Pasar `--no-interaction` a todos los comandos Artisan.
- Middleware se configura en `bootstrap/app.php` (no en Kernel — Laravel 12).
- Sin `app/Console/Kernel.php` — usar `bootstrap/app.php` o `routes/console.php`.
- Validación siempre en **Form Request classes**, nunca inline en controllers.
- Usar **Eloquent** y relaciones tipadas; evitar `DB::`.
- Usar **API Resources** para todos los endpoints REST.
- Correr `./vendor/bin/sail bin pint --dirty --format agent` tras modificar PHP.
- Tests con PHPUnit (no Pest). La mayoría deben ser Feature tests.
- Casts de modelos en método `casts()`, no en propiedad `$casts`.
- Constructor property promotion en todos los `__construct()`.
- Siempre declarar tipos de retorno en métodos.

---

## Lo que está hecho

### Infraestructura
- [x] Proyecto Laravel 12 inicializado
- [x] Docker con Sail configurado (`compose.yaml`)
- [x] PostgreSQL corriendo como servicio
- [x] Tailwind CSS v4 + Vite configurados
- [x] Inertia.js v2 + Vue 3 instalados en `admin/`
- [x] `admin/resources/js/Pages/` creado (directorio Inertia)
- [x] `client/` scaffolded con Vite + Vue 3 SPA
- [x] `docs/` creado con documentación completa
- [x] Laravel Boost v2 instalado con MCP para Claude Code
- [x] Archivos de guías para agentes: `CLAUDE.md`, `AGENTS.md`, `GEMINI.md`
- [x] Configuración MCP: `.mcp.json`, `.cursor/mcp.json`
- [x] Ziggy v2.6 instalado — `@routes` en `app.blade.php`, `ZiggyVue` en `app.js`

### FASE 1 — Base de datos y modelos ✅ COMPLETA

#### Migraciones (18 archivos)
- [x] Sistema: `users`, `sessions`, `cache`, `jobs`
- [x] `restaurants` — con slug, access_token, límites
- [x] `branches` — con coordenadas, whatsapp
- [x] `branch_schedules` — horarios por día
- [x] `categories` — con sort_order
- [x] `products` — con production_cost, price
- [x] `modifier_groups` — single/multiple, is_required
- [x] `modifier_options` — con price_adjustment
- [x] `payment_methods` — cash/terminal/transfer + datos bancarios
- [x] `delivery_ranges` — rangos km/precio
- [x] `customers` — con token único
- [x] `orders` — delivery_type, status, coordenadas, totales
- [x] `order_items` — con unit_price snapshot
- [x] `order_item_modifiers` — price_adjustment snapshot
- [x] `super_admins` — tabla separada para SuperAdmin
- [x] `add_restaurant_id_to_users` — FK multitenancy en users

#### Modelos Eloquent (15 modelos)
- [x] `Restaurant`, `Branch`, `BranchSchedule`
- [x] `Category`, `Product`, `ModifierGroup`, `ModifierOption`
- [x] `PaymentMethod`, `DeliveryRange`
- [x] `Customer`, `Order`, `OrderItem`, `OrderItemModifier`
- [x] `User` (actualizado con restaurant_id)
- [x] `SuperAdmin` (modelo separado, guard propio)
- [x] `BelongsToTenant` concern + `TenantScope` global scope

#### Factories (15 factories con datos realistas)
- [x] Todas las factories con definiciones completas

### FASE 2 — Autenticación y Multitenancy ✅ COMPLETA

#### Guards y autenticación
- [x] Guard `web` → modelo `User` (Admin Restaurante)
- [x] Guard `superadmin` → modelo `SuperAdmin`
- [x] `LoginController` — login/logout Admin (GET/POST)
- [x] `ForgotPasswordController` — recuperar contraseña
- [x] `ResetPasswordController` — restablecer contraseña
- [x] `SuperAdmin\AuthController` — login/logout SuperAdmin
- [x] Vista `Auth/Login.vue` — diseño con color primario #FF5722
- [x] Vista `Auth/ForgotPassword.vue`
- [x] Vista `Auth/ResetPassword.vue`
- [x] Vista `SuperAdmin/Login.vue` — tema claro (unificado con admin)
- [x] Rutas: `/login`, `/forgot-password`, `/reset-password/{token}`
- [x] Rutas: `/super/login`, `/super/dashboard` (placeholder)
- [x] Rutas: `/dashboard` (placeholder con middleware auth+tenant)

#### Multitenancy
- [x] `EnsureTenantContext` middleware — verifica restaurant_id en user
- [x] `HandleInertiaRequests` — comparte auth user + flash messages
- [x] Alias `tenant` registrado en `bootstrap/app.php`
- [x] 8 Policies con validación de restaurant_id:
  - [x] BranchPolicy, CategoryPolicy, ProductPolicy
  - [x] ModifierGroupPolicy, ModifierOptionPolicy
  - [x] PaymentMethodPolicy, DeliveryRangePolicy, OrderPolicy

#### Tests
- [x] `AuthTest.php` — 11 tests (login admin, login superadmin, guards cruzados, logout)
- [x] `TenantContextTest.php` — 4 tests (acceso por restaurant_id)

### FASE 3 — Panel Admin: Menú y Sucursales ✅ COMPLETA

#### Layout y navegación
- [x] `AppLayout.vue` — sidebar fijo 260px, nav con íconos Material Symbols, user info, logout
- [x] `Dashboard/Index.vue` — implementado en Fase 7
- [x] Inter + Material Symbols Outlined cargados en `app.blade.php`

#### Gestión del Menú (3a)
- [x] `MenuController` — vista principal con categorías + productos
- [x] `CategoryController` — store, update, destroy, reorder
- [x] `ProductController` — create, store, edit, update, destroy, toggle, reorder + inline modifier CRUD
- [x] Form Requests: `StoreCategoryRequest`, `UpdateCategoryRequest`, `StoreProductRequest`, `UpdateProductRequest`
- [x] Vista `Menu/Index.vue` — acordeón de categorías con tabla de productos, KPIs, toggles
- [x] Vista `Menu/Partials/CategoryModal.vue` — modal crear/editar categoría con upload de imagen
- [x] Vista `Products/Create.vue` — formulario 3 columnas con upload de imagen + modificadores inline
- [x] Vista `Products/Edit.vue` — edición + modificadores inline con sync (agregar/quitar/editar)
- [x] Upload de imágenes al disco `public` (productos y categorías)

#### Gestión de Sucursales (3b)
- [x] `BranchController` — index, create, store, edit, update, destroy, toggle
- [x] Form Requests: `StoreBranchRequest`, `UpdateBranchRequest`
- [x] Validación de límite `max_branches` al crear sucursal
- [x] Vista `Branches/Index.vue` — tabla con barra de progreso del límite del plan
- [x] Vista `Branches/Create.vue` — formulario con coordenadas
- [x] Vista `Branches/Edit.vue` — edición de sucursal

#### Tests
- [x] `MenuTest.php` — 10 tests (CRUD categorías/productos, inline modifiers, multitenancy, imagen upload)
- [x] `BranchTest.php` — 9 tests (CRUD sucursales, límite de plan, multitenancy, guard sucursal activa)
- [x] **Total: 31 tests pasando, 0 fallando**

#### Notas técnicas Fase 3

- `TenantScope` retorna 404 (no 403) para recursos de otro tenant — comportamiento correcto (no revela que el recurso existe)
- Rutas placeholder `/orders` y `/settings` registradas para evitar errores en el sidebar

---

## Pasos de implementación pendientes

### FASE 4 — API Pública: Menú y Sucursales ✅ COMPLETA

> Depende de: Fase 3 ✅

- [x] `routes/api.php`
- [x] Middleware `AuthenticateRestaurantToken` — autenticación por `access_token`
- [x] `GET /api/restaurant` — info del restaurante + métodos de pago
- [x] `GET /api/menu` — categorías + productos activos (sin `production_cost`)
- [x] `GET /api/branches` — sucursales activas con horarios
- [x] 8 API Resources: RestaurantResource, PaymentMethodResource, MenuCategoryResource, MenuProductResource, ModifierGroupResource, ModifierOptionResource, BranchResource, BranchScheduleResource
- [x] `ApiTest.php` — 12 tests (auth, estructura, multitenancy, sin production_cost)

---

### FASE 5 — Servicio de Delivery ✅ COMPLETA

> Depende de: Fase 4 ✅

- [x] `app/Services/HaversineService.php` — distancia en línea recta (fórmula Haversine)
- [x] `app/Services/GoogleMapsService.php` — wrapper de Google Distance Matrix API (mockeable)
- [x] `app/Services/DeliveryService.php` — flujo 7 pasos: sucursales activas → Haversine pre-filtro → Google Distance Matrix (MAX_CANDIDATES=1) → rangos → cobertura → horario
- [x] `app/DTOs/DeliveryResult.php` — DTO readonly con todos los campos del resultado
- [x] `app/Http/Requests/DeliveryCalculateRequest.php` — validación lat/lng
- [x] `app/Http/Controllers/Api/DeliveryController.php`
- [x] `app/Http/Resources/DeliveryCalculationResource.php`
- [x] `POST /api/delivery/calculate` en `routes/api.php`
- [x] `config/services.php` — entrada `google_maps.key` (`GOOGLE_MAPS_API_KEY`)
- [x] `tests/Feature/DeliveryServiceTest.php` — 11 tests (validación, sucursal única, múltiples, cobertura, horarios, multitenancy)

**Notas técnicas:**
- 1 sucursal activa → 1 llamada a Google Maps para distancia real de conducción
- 2+ sucursales → Haversine pre-filtra TOP 1 → 1 request Google con 1 destino
- Sin fallback a Haversine: si Google Maps falla o retorna PHP_FLOAT_MAX, lanza `DomainException`
- `DomainException` cuando no hay sucursales activas → respuesta 422

---

### FASE 6 — API Pública: Crear Pedido ✅ COMPLETA

> Depende de: Fase 5 ✅

- [x] `POST /api/orders`
- [x] `app/Services/LimitService.php` — verifica límite por periodo configurable (orders_limit_start/end)
- [x] `app/Services/OrderService.php` — límite → customer → branch → products → modifiers → anti-tampering → totales backend → transaction → WhatsApp message
- [x] `app/DTOs/OrderCreatedResult.php` — DTO readonly
- [x] `app/Http/Requests/StoreOrderRequest.php` — validación completa
- [x] `app/Http/Controllers/Api/OrderController.php`
- [x] `app/Http/Resources/OrderConfirmationResource.php`
- [x] `tests/Feature/OrderApiTest.php` — 17 tests (happy paths, validación, límites, anti-tampering, multitenancy)

**Notas técnicas:** totales siempre en backend; anti-tampering precio ±$0.01; pickup/dine_in delivery_cost=0; distancia calculada server-side via Google Maps (sin fallback, lanza ValidationException si falla); snapshots de product_name, production_cost, modifier_option_name en order_items/order_item_modifiers

**Documentación de referencia:** `docs/modules/10-api.md`, `docs/modules/08-customer-flow.md`

---

### FASE 7 — Panel Admin: Pedidos y Dashboard ✅ COMPLETA

> Depende de: Fase 6 ✅

- [x] `app/Services/StatisticsService.php` — KPIs: hoy, ayer, preparando, mensual, ganancia neta, por sucursal, últimos 10
- [x] `DashboardController` — real con `StatisticsService`
- [x] `OrderController` (admin) — `index` Kanban, `show`, `advanceStatus`, `newCount`
- [x] `AdvanceOrderStatusRequest` — Form Request
- [x] Rutas: `/dashboard`, `/orders`, `/orders/{order}`, `/orders/{order}/status`, `/orders/new-count`
- [x] Vista `Dashboard/Index.vue` — 4 KPI cards, barras por sucursal, tabla últimos pedidos
- [x] Vista `Orders/Index.vue` — Kanban 4 columnas, filtros branch/date
- [x] Vista `Orders/Show.vue` — barra de progreso, comanda, info cliente, info entrega, botón avance
- [x] `tests/Feature/DashboardTest.php` — 5 tests
- [x] `tests/Feature/OrderAdminTest.php` — 13 tests

**Notas:** status solo avanza (recibido→preparando→camino→entregado); `newCount` para polling; ganancia neta = revenue − production_cost + modifier revenue

**Pantallas:** `ar_04`, `ar_05`, `ar_06`

---

### FASE 8 — Panel Admin: Configuración ✅ COMPLETA

> Depende de: Fase 2 ✅

- [x] Migración: `add_settings_fields_to_restaurants_table` — agrega `allows_delivery`, `allows_pickup`, `allows_dine_in`, `instagram`, `facebook`, `tiktok`
- [x] `SettingsController` — General: nombre, logo upload, redes sociales
- [x] `DeliveryMethodController` — Toggles de métodos de entrega (mínimo 1 activo)
- [x] `DeliveryRangeController` — CRUD de rangos de distancia/precio con validación
- [x] `PaymentMethodController` — Toggles + datos bancarios para transferencia
- [x] `SettingsController@schedules` — Horarios del restaurante (7 días, opens_at/closes_at, toggle cerrado)
- [x] `ProfileController` — Nombre, email, cambio de contraseña (con verificación)
- [x] `LimitsController` — Vista de solo lectura: pedidos y sucursales con barras
- [x] Form Requests: `UpdateGeneralSettingsRequest`, `UpdateDeliveryMethodsRequest`, `StoreDeliveryRangeRequest`, `UpdateDeliveryRangeRequest`, `UpdatePaymentMethodRequest`, `UpdateRestaurantScheduleRequest`, `UpdateProfileRequest`
- [x] Componente compartido `SettingsLayout.vue` — sub-navegación con 7 secciones
- [x] Vistas: `Settings/General.vue`, `Settings/Schedules.vue`, `Settings/DeliveryMethods.vue`, `Settings/ShippingRates.vue`, `Settings/PaymentMethods.vue`, `Settings/Profile.vue`, `Settings/Limits.vue`
- [x] `PaymentMethodFactory` completado con estados `cash()`, `terminal()`, `transfer()`
- [x] `tests/Feature/SettingsTest.php` — 27 tests
- [x] Validación: no se puede desactivar el último método de pago activo

**Pantallas:** `ar_14` a `ar_20`

---

### FASE 9 — Panel SuperAdmin ✅ COMPLETA

> Depende de: Fase 1 ✅, Fase 2 ✅

- [x] `SuperAdmin\DashboardController` — KPIs globales: restaurantes activos, pedidos mes, nuevos restaurantes, recientes
- [x] `SuperAdmin\RestaurantController` — index (paginado + filtro estado), create, store, show, updateLimits, toggleActive
- [x] `SuperAdmin\StatisticsController` — pedidos por día (30 días), top restaurantes del mes
- [x] Form Requests: `CreateRestaurantRequest`, `UpdateRestaurantLimitsRequest`
- [x] Layout `SuperAdminLayout.vue` — sidebar claro (bg-white) unificado con panel admin
- [x] Vista `SuperAdmin/Dashboard.vue` — 3 KPI cards + lista de restaurantes recientes
- [x] Vista `SuperAdmin/Restaurants/Index.vue` — tabla paginada con filtros, toggle activo/inactivo, link a detalle
- [x] Vista `SuperAdmin/Restaurants/Create.vue` — formulario completo con slug autosugerido, admin + contraseña, límites
- [x] Vista `SuperAdmin/Restaurants/Show.vue` — barras de uso, edición inline de límites, access_token con reveal/copiar/regenerar (modal confirmación)
- [x] Vista `SuperAdmin/Statistics.vue` — chart por barras + top restaurantes
- [x] `tests/Feature/SuperAdminTest.php` — 21 tests (auth, dashboard KPIs, CRUD restaurantes, toggle, límites, regenerar token, estadísticas, cross-guard)
- [x] Creación de restaurante en transacción DB: Restaurant → User → 3 PaymentMethods (cash activo por defecto, terminal/transfer inactivos)
- [x] Acceso cross-tenant con `withoutGlobalScope(TenantScope::class)`

**Documentación de referencia:** `docs/modules/07-superadmin.md`

---

### FASE 10 — Frontend del Cliente (SPA) ✅ COMPLETA

> Depende de: Fase 4, 5, 6

- [x] Tailwind CSS v4 + `@tailwindcss/vite` instalados en `client/`
- [x] `vite.config.js` configurado con alias `@/` → `src/`
- [x] `src/style.css` — Google Fonts (Inter + Material Symbols), Tailwind v4, `.no-scrollbar`
- [x] `src/main.js` — createApp + createPinia + vue-router
- [x] `src/App.vue` — inicializa restaurante + menú al montar
- [x] `src/services/api.js` — axios con `VITE_API_BASE_URL` y `VITE_RESTAURANT_TOKEN`
- [x] `src/utils/cookies.js` — get/set cookie `pideaqui_customer` (90 días) + generateCustomerToken()
- [x] `src/stores/restaurant.js` — fetchRestaurant(), fetchMenu(), paymentMethods
- [x] `src/stores/cart.js` — addItem, updateQuantity, removeItem, clear, subtotal, totalItems
- [x] `src/stores/order.js` — deliveryType, branch, delivery data, scheduledAt, customer data, confirmedOrderId
- [x] `src/router/index.js` — hash history, 5 rutas: /, /cart, /delivery, /payment, /confirmed
- [x] `src/views/MenuHome.vue` (c_01) — header sticky, chips de categorías, cards de productos, IntersectionObserver para chip activo, estados: loading/unavailable/empty
- [x] `src/components/ProductModal.vue` (c_02) — bottom sheet, grupos de modificadores (radio/checkbox), precio dinámico, notas, validación de grupos requeridos
- [x] `src/components/CartBar.vue` — barra flotante naranja con total + cantidad, animación slide-up
- [x] `src/views/CartSummary.vue` (c_03) — lista con editar cantidad, eliminar, agregar más, resumen
- [x] `src/views/DeliveryLocation.vue` (c_04) — selector A domicilio/Recoger/Comer aquí, GPS, cálculo de cobertura vía API, selección de sucursal, programar pedido
- [x] `src/views/PaymentConfirmation.vue` (c_05) — datos cliente pre-llenados de cookie, métodos de pago activos, datos bancarios transferencia, confirmación → POST /api/orders → abre WhatsApp → guarda cookie
- [x] `src/views/OrderConfirmed.vue` (c_06) — pantalla de éxito con número de pedido, instrucciones WhatsApp, botón volver al menú
- [x] `.env.example` con `VITE_API_BASE_URL` y `VITE_RESTAURANT_TOKEN`
- [x] Build de producción exitoso — 0 warnings, 97 módulos, ~179KB JS

**Pantallas:** `c_01` a `c_06`

---

### FASE 11 — Jobs y Tareas Programadas

- [ ] (Opcional) `ResetMonthlyOrderCountJob` — alternativa recomendada: contar con query directa

---

### FASE 12 — Integraciones Externas ✅ COMPLETA

| Integración | Fase | Costo | Estado |
|---|---|---|---|
| Google Maps JavaScript API | Fase 3b (mapa sucursales) + Fase 10 | Pago | ✅ |
| Google Distance Matrix API | Fase 5 | Pago | ✅ |
| Cloud Storage (S3 o compatible) | Fase 3a (imágenes) | Pago | ✅ |
| Geolocalización del navegador | Fase 10 | Gratis | ✅ |
| WhatsApp (wa.me link) | Fase 6 y 10 | Gratis | ✅ |

#### Cloud Storage (S3 / compatible)
- [x] `MEDIA_DISK` env var — controla el disco de almacenamiento (`public` local ó `s3`)
- [x] `config/filesystems.php` — `media_disk` config key + `visibility: public` en S3
- [x] Model accessors en `Product`, `Category`, `Restaurant` — `imageUrl()` / `logoUrl()` con `$appends`
- [x] Todos los controllers de upload usan `config('filesystems.media_disk', 'public')`
- [x] API Resources usan `Storage::disk(config(...))->url()` — URLs correctas en local y S3
- [x] Vistas Vue actualizadas: usan `image_url` / `logo_url` del servidor (no construyen `/storage/...`)
- [x] `admin/.env.example` — añadidos `MEDIA_DISK`, `AWS_URL`, `AWS_ENDPOINT`

#### Google Maps Interactive Map
- [x] `client/src/components/MapPicker.vue` — mapa interactivo con pin arrastrable (SPA cliente)
  - Carga dinámica del script de Google Maps API con `VITE_GOOGLE_MAPS_KEY`
  - Fallback elegante cuando no hay clave configurada
  - Emite `update:lat` y `update:lng` al arrastrar el pin
  - Se re-centra cuando cambian las props (GPS obtenido externamente)
- [x] `client/src/views/DeliveryLocation.vue` — integra `MapPicker` entre el botón GPS y los campos de dirección
- [x] `admin/resources/js/Components/MapPicker.vue` — variante para el panel admin
  - Admite clic en el mapa + pin arrastrable para reposicionar
  - Props como `String|Number` (valores del formulario)
- [x] `admin/resources/js/Pages/Branches/Create.vue` — integra `MapPicker` para selección de coordenadas
- [x] `admin/resources/js/Pages/Branches/Edit.vue` — integra `MapPicker` con coordenadas existentes
- [x] `client/.env.example` — añadido `VITE_GOOGLE_MAPS_KEY`

---

### FASE 13 — Tests

> Continua — escribir tests al implementar cada fase.

- [x] Tests de autenticación (AuthTest — 11 tests)
- [x] Tests de multitenancy (TenantContextTest — 4 tests)
- [x] Feature tests CRUD Menú (MenuTest — 8 tests)
- [x] Feature tests CRUD Sucursales (BranchTest — 5 tests)
- [x] Feature tests API (ApiTest — 12 tests, con token válido/inválido, sin production_cost)
- [x] Tests del DeliveryService (DeliveryServiceTest — 11 tests, GoogleMapsService mockeado)
- [x] Tests API Crear Pedido (OrderApiTest — 19 tests, anti-tampering, límites, multitenancy, cardinalidad)
- [x] Tests Panel Admin Dashboard (DashboardTest — 5 tests)
- [x] Tests Panel Admin Pedidos (OrderAdminTest — 22 tests, Kanban, show, avance status, newCount, cancelación, multitenancy)
- [x] Tests Configuración Admin (SettingsTest — 23 tests, todos los controllers, cross-tenant, validaciones, payment method guard)
- [x] Tests Panel SuperAdmin (SuperAdminTest — 20 tests, auth, dashboard KPIs, CRUD restaurantes, toggle, límites, estadísticas, cross-guard)
- [x] Tests Mapa de Pedidos (MapControllerTest — 13 tests, auth, render, KPIs, filtros fecha/sucursal/estatus, multitenancy)

---

## Orden de implementación recomendado (actualizado)

```
✅ Fase 1: DB + Modelos
    ↓
✅ Fase 2: Auth + Multitenancy
    ↓
✅ Fase 3: Panel Admin — Menú y Sucursales
    ↓
✅ Fase 4: API Menú + Sucursales
    ↓                   ↓
✅ Fase 5: DeliveryService  Fase 8: Config Admin
    ↓
   Fase 6: API Orders
    ↓            ↓
   Fase 7: Admin    Fase 10: SPA Cliente
   Pedidos + Dashboard
    ↓
   Fase 9: SuperAdmin (puede hacerse en paralelo con Fase 4+)
   Fase 11: Jobs (opcional)
   Fase 12: Integraciones (transversal)
   Fase 13: Tests (continua)
```

---

## Mejoras post-MVP — 2 de marzo de 2026

### Modificadores por producto (inline)
- [x] Migración: `modifier_groups` vuelve a `product_id` (HasMany), tabla pivote `modifier_group_product` eliminada
- [x] `modifier_options` tiene nueva columna `production_cost` decimal(10,2) default 0
- [x] Modelos actualizados: `ModifierGroup.product()` BelongsTo, `Product.modifierGroups()` HasMany
- [x] `ProductController` maneja CRUD inline de grupos y opciones (syncModifierGroups)
- [x] Form Requests: validación inline de `modifier_groups.*` con opciones anidadas
- [x] Eliminados: `ModifierGroupController`, `ModifierOptionController`, `Modifiers/Index.vue`, 4 Form Requests de modifiers, rutas y link de menú
- [x] UI: `Products/Create.vue` y `Products/Edit.vue` con formulario inline de grupos + opciones (name, selection_type, is_required, options con price_adjustment y production_cost)
- [x] `OrderService` validación PASO 5: opciones ahora se validan por `product_id` del item
- [x] `ModifierOptionResource` ya no expone `production_cost` (verificado)
- [x] Tests actualizados: MenuTest (inline create/edit/delete), OrderApiTest (product_id directo), ApiTest (sin attach)

### Sucursal activa obligatoria
- [x] `BranchController`: toggle, update y destroy validan que no se desactive/elimine la última sucursal activa
- [x] Método `isLastActiveBranch()` reutilizable en el controller
- [x] `Branches/Index.vue`: botones toggle/eliminar deshabilitados visualmente para la última sucursal activa
- [x] 4 tests nuevos en `BranchTest`: no toggle/delete última activa, sí toggle/delete con 2+ activas

### Barras de acción inferiores (admin)
- [x] `Products/Create.vue`, `Products/Edit.vue`, `Branches/Create.vue`, `Branches/Edit.vue`, `Branches/Schedules.vue`
- [x] Patrón: `fixed bottom-0 left-[260px]` con "Cancelar" y "Guardar", `pb-24` en contenido

### Fix payload órdenes (cliente SPA)
- [x] `PaymentConfirmation.vue`: customer como objeto anidado, `unit_price` en items, modifiers como array de objetos, `distance_km` incluido

### Simplificación flujo delivery (cliente SPA)
- [x] `DeliveryLocation.vue`: eliminado paso intermedio "Verificar cobertura", `proceed()` llama API directamente

### Método de pago — mínimo uno activo
- [x] `UpdatePaymentMethodRequest.php`: validación en `after()` que impide desactivar el último método activo
- [x] `PaymentMethods.vue`: muestra error de validación junto al toggle
- [x] Test nuevo: `test_cannot_deactivate_last_active_payment_method`

### Horarios a nivel restaurante (migración de branch → restaurant)
- [x] Migración: nueva tabla `restaurant_schedules` con constraint único `[restaurant_id, day_of_week]`
- [x] Modelo `RestaurantSchedule` con factory (estado `closed()`) y resource `RestaurantScheduleResource`
- [x] `Restaurant::schedules()` HasMany + `Restaurant::isCurrentlyOpen()` compara hora actual vs horario del día
- [x] `SettingsController@schedules` y `@updateSchedules` — aseguran 7 registros, truncan HH:MM:SS→HH:MM
- [x] `UpdateRestaurantScheduleRequest` — valida `date_format:H:i`
- [x] Vista `Settings/Schedules.vue` — 7 filas con toggle + timepickers, barra de acción fija
- [x] `SettingsLayout.vue` — nuevo nav item "Horarios" con icono `schedule`
- [x] API `GET /api/restaurant` retorna `schedules` (RestaurantScheduleResource) e `is_open` (boolean)
- [x] `DeliveryService` usa `RestaurantSchedule` en vez de `BranchSchedule` para validar horarios
- [x] Tests: `DeliveryServiceTest` actualizado a `RestaurantSchedule`

### QR y Token removidos del admin del restaurante
- [x] Eliminados: `QrCodeController.php`, `Settings/QrCode.vue`, ruta `/settings/qr-code`
- [x] `SettingsLayout.vue`: removido nav item "Código QR"
- [x] El token sigue visible solo en el panel SuperAdmin (`SuperAdmin/Restaurants/Show.vue`)

### Estado cerrado en el cliente (SPA)
- [x] `MenuHome.vue`: banner oscuro "Fuera de horario" con horario del día, menú completo en grayscale+opacity con pointer-events-none
- [x] `CartBar.vue`: botón gris y deshabilitado cuando `is_open === false`
- [x] `DeliveryLocation.vue`: time slots generan TODOS los slots de 30 min dentro del horario (sin cap de 12), flex-wrap grid
- [x] `openProduct()` retorna early si cerrado — doble seguridad

### Mejoras UI cliente (WhatsApp, GPS, textos)
- [x] Mensaje WhatsApp enriquecido con Google Maps links contextuales (cliente o sucursal según delivery type)
- [x] GPS: manejo de errores con mensajes descriptivos (permiso denegado, no disponible, timeout)
- [x] Tamaños de texto aumentados en tarjetas de producto (name `text-base`, description `text-sm`, price `text-base`)
- [x] ProductModal: imagen con `aspect-[4/3] max-h-[280px]` en vez de `h-52` fijo

### SuperAdmin tema claro + límites por periodo
- [x] `SuperAdminLayout.vue`: sidebar `bg-white border-r border-gray-100` (antes `bg-gray-900`), nav colores claros
- [x] `SuperAdmin/Login.vue`: fondo `bg-[#FAFAFA]`, card `bg-white`, inputs claros
- [x] `Restaurants/Index.vue`: filtros activos usan `bg-[#FF5722]` (antes `bg-gray-900`)
- [x] Migración: `max_monthly_orders` → `orders_limit`, nuevas columnas `orders_limit_start` (date), `orders_limit_end` (date)
- [x] `Restaurant` model: fillable/casts actualizados con periodo
- [x] `LimitService`: `isOrderLimitReached()` y `orderCountInPeriod()` usan `whereBetween` con fechas del periodo
- [x] `OrderService`: usa `isOrderLimitReached()` en vez de `isMonthlyLimitReached()`
- [x] `RestaurantResource`: retorna `orders_limit_reached` (antes `monthly_orders_reached`), usa `LimitService`
- [x] `StatisticsService`: recibe `Restaurant` completo, usa `LimitService::orderCountInPeriod()`, retorna `orders_limit`
- [x] `DashboardController`, `LimitsController`, `OrderController`: actualizados a nuevos campos
- [x] `SuperAdmin\RestaurantController`: usa `LimitService` para conteos por periodo
- [x] `CreateRestaurantRequest`, `UpdateRestaurantLimitsRequest`: validan `orders_limit`, `orders_limit_start`, `orders_limit_end`
- [x] Frontend admin: `Dashboard/Index.vue`, `Settings/Limits.vue`, `Orders/Index.vue` usan `orders_limit` y muestran "del periodo"
- [x] Frontend SuperAdmin: Create, Show, Index actualizados con campos de periodo
- [x] Cliente SPA: `MenuHome.vue` usa `orders_limit_reached`
- [x] Tests: 139 tests pasando — todos actualizados a nuevos campos

### Auditoría de seguridad — 7 fixes críticos
- [x] **Delivery cost validado server-side**: `OrderService` ahora calcula `delivery_cost` desde `DeliveryRange` según `distance_km`, ignora valor enviado por el cliente
- [x] **Race condition en límite de pedidos (TOCTOU)**: límite se re-verifica DENTRO de la transacción con `lockForUpdate()` en el row del restaurante
- [x] **Modifier groups requeridos validados**: si un producto tiene `is_required=true` modifier groups, el pedido falla si no se envía al menos una opción por grupo
- [x] **Sucursal inactiva rechaza pedidos**: `OrderService` valida `branch.is_active` antes de aceptar el pedido
- [x] **IDOR category_id corregido**: `StoreProductRequest` y `UpdateProductRequest` usan `Rule::exists()->where('restaurant_id', ...)` para scope al tenant
- [x] **Rate limiting en login**: endpoints POST de login (admin, superadmin, forgot-password, reset-password) tienen `throttle:5,1` (5 intentos/minuto)
- [x] **Password double-hash corregido**: `ProfileController` y `ResetPasswordController` ya no llaman `Hash::make()` — el cast `hashed` del modelo `User` se encarga
- [x] Tests: 143 tests pasando (4 nuevos: inactive branch, delivery range validation, delivery cost server-side, required modifiers)

### Auditoría de seguridad — 8 fixes de alta severidad
- [x] **Payment method validado**: `OrderService` verifica que el `payment_method` enviado sea un método activo del restaurante
- [x] **Delivery type validado**: `OrderService` verifica `delivery_type` contra los flags `allows_delivery`/`allows_pickup`/`allows_dine_in` del restaurante
- [x] **Google Maps element-level errors**: `GoogleMapsService` maneja elementos con status != 'OK' (destinos inalcanzables) retornando `PHP_FLOAT_MAX` en vez de crash
- [x] **`access_token` oculto de Inertia**: `Restaurant` model tiene `$hidden = ['access_token']`. SuperAdmin Show usa `makeVisible()` para mostrarlo explícitamente
- [x] **`restaurant_id` fuera de User $fillable**: previene mass assignment. Se asigna explícitamente en SuperAdmin store
- [x] **StoreOrderRequest endurecido**: `scheduled_at` valida `after:now`, items `max:50`, quantity `max:100`, unit_price `max:99999.99`, modifiers `max:20`
- [x] **`allows_delivery` verificado en API delivery**: `DeliveryController` retorna 422 si el restaurante no permite delivery
- [x] **Password double-hash en SuperAdmin store**: removido `Hash::make()` — UserFactory también simplificado
- [x] Tests: 145 tests pasando (2 nuevos: delivery type not allowed, inactive payment method)

### Auditoría de seguridad — 6 fixes de severidad media
- [x] **Duplicate modifier options rechazados**: `StoreOrderRequest` agrega regla `distinct` a `modifier_option_id` — impide enviar la misma opción dos veces en un item (doble cobro)
- [x] **SVG uploads bloqueados**: Todas las validaciones de imagen (productos, categorías, logo) ahora incluyen `mimes:jpeg,jpg,png,gif,webp` para prevenir XSS via SVG
- [x] **Overnight schedules**: `Restaurant::isCurrentlyOpen()` y `DeliveryService::checkSchedule()` ahora manejan horarios nocturnos (`closes_at < opens_at`, ej. 20:00–02:00)
- [x] **Null guard en checkSchedule**: `DeliveryService::checkSchedule()` ahora retorna cerrado si `opens_at` o `closes_at` son null
- [x] **Rate limiting en API de pedidos**: `POST /api/orders` limitado a 30 peticiones por minuto via `throttle:30,1`
- [x] **Cross-product modifier validation**: PASO 5 en `OrderService` ahora valida que los modificadores de cada item pertenezcan específicamente a los modifier groups de ese producto, no colectivamente a cualquier producto del restaurante
- [x] Tests: 147 tests pasando (2 nuevos: duplicate modifier option, cross-product modifier)

### Mejoras UX y consistencia — 3 de marzo de 2026

#### Admin Settings
- [x] **ShippingRates error display**: Errores de validación ahora usan posición absoluta con espacio reservado — no desplazan el layout del formulario. Bordes rojos en campos con error.
- [x] **Mensajes de validación en español**: Todos los Form Requests de settings (StoreDeliveryRange, UpdateDeliveryRange, UpdatePaymentMethod, UpdateDeliveryMethods) ahora tienen `messages()` completos en español.
- [x] **CLABE 16 o 18 dígitos**: Validación cambiada de `size:18` a `regex:/^\d{16}(\d{2})?$/`. Label y placeholder actualizados en frontend.
- [x] **Delivery requiere tarifas de envío**: No se puede activar "Entrega a domicilio" sin al menos una tarifa configurada. Backend valida en `UpdateDeliveryMethodsRequest`. Frontend muestra advertencia y deshabilita toggle.
- [x] **Cash activo por defecto**: Al crear restaurante, el método de pago "Efectivo" se crea con `is_active = true`.
- [x] **Kanban responsivo**: Columnas flex en vez de ancho fijo, scroll por columna, llena viewport sin overflow.

#### SuperAdmin
- [x] **Botones estandarizados**: Dashboard y Restaurants/Index unificados a `rounded-xl text-sm font-semibold` consistente con Show.
- [x] **"Invalid Date" corregido**: `formatDate()` en Show.vue ahora maneja ISO datetime strings de Eloquent date casts con `timeZone: 'UTC'`.
- [x] **Regenerar token**: Nuevo botón "Regenerar token" en Show.vue con modal de confirmación. Ruta `POST /super/restaurants/{id}/regenerate-token`. Genera nuevo SHA256 token.
- [x] Tests: 149 tests pasando (2 nuevos: regenerar token, delivery sin tarifas)

### Fix distancia de delivery — 12 de marzo de 2026

- [x] **DeliveryService: single-branch ahora usa Google Maps**: Antes, restaurantes con 1 sucursal calculaban distancia con Haversine (línea recta) y `durationMinutes=0`. Ahora llaman a Google Distance Matrix para obtener distancia real de conducción y duración estimada.
- [x] **OrderService: distancia de pedido ahora usa Google Maps**: Antes, `OrderService.store()` calculaba la distancia con Haversine para validar cobertura y determinar costo de envío. Esto causaba discrepancia entre el costo mostrado al cliente (DeliveryService con Google para 2+ sucursales) y el costo real cobrado (OrderService con Haversine). Ahora `OrderService` inyecta `GoogleMapsService` y calcula distancia real de conducción.
- [x] ~~**Fallback robusto**: Ambos servicios caen a Haversine si Google Maps falla~~ — **Removido en sesión del 13/03**: ahora sin fallback, lanza excepción si Google falla.
- [x] Tests: 155 tests pasando — mocks de GoogleMapsService actualizados en DeliveryServiceTest y OrderApiTest.

---

### UX: advertencias de configuración incompleta — 12 de marzo de 2026

- [x] **Admin Schedules.vue — advertencia "cerrado"**: Si todos los días están marcados como cerrados (o no hay horarios configurados), se muestra un banner amarillo: "Tu restaurante aparece como cerrado. Tus clientes verán que estás cerrado y no podrán realizar pedidos." El banner es reactivo — desaparece al activar cualquier día.
- [x] **Cliente DeliveryLocation.vue — sin sucursales**: Si el restaurante no tiene sucursales configuradas, se muestra un banner de advertencia y el botón "Continuar al pago" queda deshabilitado. Aplica a los 3 tipos de entrega (delivery, pickup, dine_in).
- [x] **Cliente restaurant store — error de menú visible**: `fetchMenu()` ya no silencia errores. Si la API del menú falla, se muestra el mensaje de error en la UI en vez de una pantalla vacía sin explicación.

### Fix: selección múltiple de modificadores — 12 de marzo de 2026

- [x] **Cliente ProductModal.vue — campo `is_multiple` inexistente**: La API retorna `selection_type` (string: "single"/"multiple") pero el componente leía `group.is_multiple` (siempre `undefined`). Resultado: todos los grupos se comportaban como selección única. Fix: helper `isMultiple(group)` que verifica `group.selection_type === 'multiple'`, usado en las 7 ubicaciones relevantes (inicialización, selectedModifiers, isValid, toggleOption, isSelected, template badge "Varios").
- [x] **Diferenciación visual radio vs checkbox**: El indicador de selección ahora usa `rounded-full` (círculo) para grupos single y `rounded-md` (cuadrado redondeado) para grupos multiple. Antes siempre era circular independientemente del tipo.
- [x] **Backend: validación de cardinalidad single-group**: Nuevo PASO 5c en `OrderService` — si un grupo tiene `selection_type === 'single'`, rechaza pedidos con más de una opción seleccionada para ese grupo. Antes, el backend no validaba la cardinalidad y aceptaba múltiples opciones en un grupo single.
- [x] Tests: 157 tests pasando (2 nuevos: `test_single_group_with_two_options_returns_422`, `test_multiple_group_with_two_options_succeeds`).

### UX: mejora de carga de imágenes — 12 de marzo de 2026

- [x] **Mensajes de validación en español (backend)**: Los 5 Form Requests con validación de imagen (`StoreProductRequest`, `UpdateProductRequest`, `StoreCategoryRequest`, `UpdateCategoryRequest`, `UpdateGeneralSettingsRequest`) ahora tienen `messages()` con errores en español para `image`/`mimes`/`max`. Antes usaban los defaults de Laravel en inglés.
- [x] **Hints visuales en frontend**: Todos los formularios de upload ahora muestran formato aceptado, peso máximo y proporción recomendada alineados con las reglas reales del backend (productos: 2 MB, 1:1; categorías: 5 MB; logo: 2 MB, 1:1).
- [x] **Validación client-side de tamaño**: `handleImageChange()` y `onLogoChange()` validan el peso del archivo antes de asignarlo al formulario. Si excede el límite, muestra error inmediato con el peso real del archivo. Evita upload innecesario al servidor.
- [x] **Accept restringido**: `accept="image/*"` reemplazado por `.jpg,.jpeg,.png,.gif,.webp` en todos los inputs, alineado con `mimes:jpeg,jpg,png,gif,webp` del backend. El diálogo de archivos del OS ya filtra los formatos aceptados.
- [x] **Preview de logo**: Settings/General.vue ahora muestra preview del nuevo logo seleccionado (antes solo mostraba el logo actual).

### Mejoras UI admin + cliente — 12 de marzo de 2026

#### Admin Kanban (Orders/Index.vue)
- [x] **Horario programado en card**: Si el pedido tiene `scheduled_at`, se muestra junto al precio total con icono de reloj y formato 12h (ej. "8:00 p.m."). Pedidos sin hora programada no muestran nada extra.
- [x] **Icono de método de pago en card**: Junto al nombre de la sucursal se muestra un icono según `payment_method`: `payments` (efectivo), `credit_card` (terminal), `account_balance` (transferencia). Permite identificar forma de pago de un vistazo.

#### Cliente SPA
- [x] **Footer sticky "Powered by PideAqui.com"**: Footer `fixed bottom-0 z-10` con fondo naranja sutil (`bg-[#FF5722]/5`), siempre visible en el viewport. Todos los botones de acción (CartBar, continuar, confirmar) subidos de `bottom-5` a `bottom-10` para no solaparse. Padding inferior de contenido ajustado en todas las vistas. En OrderConfirmed, su footer propio (z-20) cubre al "Powered by" naturalmente.
  > ⚠️ **Aclaración (15-abr-2026):** este footer fue implementado en la rama `test` del cliente (commit `e98c87a`) y **nunca se mergeó a `main`**. La rama `main` de `client/` y `pagos_stripe` históricamente NO lo tuvieron. El 15 de abril de 2026 fue **restaurado en la rama actual `pagos_stripe`** del cliente. Si despliegas desde otra rama, revisa que `client/src/App.vue` lo incluya.
- [x] **Favicon dinámico**: Reemplazado `vite.svg` por `favicon.svg` con icono naranja branded. Al cargar la app, si el restaurante tiene logo, el favicon se actualiza dinámicamente. También se actualiza el `<title>` de la pestaña con el nombre del restaurante.
- [x] **Logo sin recorte circular**: Contenedor del logo en header cambiado de `rounded-full` (circular) a `rounded-xl` (cuadrado redondeado). Imagen de `object-cover` a `object-contain` para mostrar el logo completo sin cortar esquinas. Tamaño aumentado de `w-10 h-10` a `w-12 h-12`, nombre de `text-sm` a `text-base`.

### Cancelación de pedidos — 12 de marzo de 2026

- [x] **Migración**: Expande constraint CHECK de PostgreSQL para incluir `'cancelled'`. Agrega columnas `cancellation_reason` (text, nullable) y `cancelled_at` (timestamp, nullable).
- [x] **Order model**: `cancellation_reason` y `cancelled_at` en `$fillable`, cast datetime, método `isCancellable()` (solo `received`/`preparing`).
- [x] **OrderPolicy::cancel()**: Valida tenant ownership + estado cancelable. Retorna 403 si el pedido está en `on_the_way`, `delivered` o ya `cancelled`.
- [x] **CancelOrderRequest**: Valida `cancellation_reason` required, string, max:500, mensajes en español.
- [x] **OrderController::cancel()**: Actualiza status a `cancelled` con razón y timestamp. Ruta `PUT /orders/{order}/cancel`.
- [x] **OrderController::advanceStatus()**: Guard adicional — pedidos cancelados no pueden avanzar.
- [x] **StatisticsService**: Revenue, baseProfit y modifierProfit excluyen pedidos cancelados (`where('status', '!=', 'cancelled')`).
- [x] **Dashboard/Index.vue**: STATUS_LABELS y STATUS_CLASSES incluyen `cancelled: 'Cancelado'` / `bg-red-100 text-red-800`.
- [x] **Orders/Index.vue (Kanban)**: Nueva columna "Cancelado" (rojo). Cards canceladas: no arrastrables, opacity reducida, nombre tachado, badge rojo.
- [x] **Orders/Show.vue**: Botón "Cancelar pedido" visible solo cuando `isCancellable`. Modal con 4 razones predefinidas + "Otro" con textarea obligatorio. Banner de cancelación reemplaza barra de progreso (muestra razón + fecha/hora).
- [x] **OrderFactory**: Estado `cancelled()` agregado.
- [x] Tests: 166 tests pasando (9 nuevos: cancel received, cancel preparing, cannot cancel on_the_way/delivered/already cancelled, requires reason, cannot cancel other restaurant, cancelled cannot advance, index includes cancelled column).

### Seccion Cancelaciones (reporte) — 12 de marzo de 2026

- [x] **CancellationService**: Servicio que calcula metricas de cancelacion por rango de fechas: cancelled_count, total_orders_count, cancellation_rate (%), reasons_breakdown (GROUP BY motivo), by_branch (cancelaciones por sucursal), by_day (serie temporal), cancelled_orders (lista con relaciones). Soporte para filtro por sucursal.
- [x] **CancellationController**: Renderiza `Cancellations/Index` con filtros de fecha (from/to) y sucursal (branch_id). Reutiliza `OrderPolicy::viewAny` para autorizacion.
- [x] **Ruta**: `GET /cancellations` → `cancellations.index` dentro del grupo auth+tenant.
- [x] **Cancellations/Index.vue**: Pagina completa con filtros (presets Hoy/Ayer/7dias/Mes + rango custom + selector sucursal), 3 KPI cards (cancelados, tasa %, motivo mas frecuente), barras de motivos de cancelacion, barras por sucursal, barras por dia, tabla de pedidos cancelados con link a detalle.
- [x] **Sidebar**: Nuevo item "Cancelaciones" con icono `cancel` despues de "Pedidos" en AppLayout.vue.
- [x] Tests: 178 tests pasando (12 nuevos en CancellationTest: auth, render, props, counts/rate, multitenancy, filtro fecha, filtro sucursal, reasons breakdown, top reason, by branch, relations, empty state).

### WebSockets en tiempo real (Reverb + Echo) — 12 de marzo de 2026

- [x] **Laravel Reverb v1.8**: Instalado via Composer. Servidor WebSocket self-hosted, integrado con Laravel Broadcasting.
- [x] **Laravel Echo + pusher-js**: Instalados via npm. Cliente JS para suscribirse a canales WebSocket.
- [x] **config/broadcasting.php**: Driver `reverb` configurado con variables `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_APP_ID`.
- [x] **config/reverb.php**: Servidor Reverb en puerto 8080, sin TLS (local).
- [x] **.env / .env.example**: `BROADCAST_CONNECTION=reverb`, variables `REVERB_*` y `VITE_REVERB_*` agregadas.
- [x] **3 eventos de broadcasting**:
  - `OrderCreated` — se dispara al crear pedido desde API del cliente. Payload: order completo con customer/branch.
  - `OrderStatusChanged` — se dispara al avanzar status (drag-and-drop o botón). Payload: order + previousStatus.
  - `OrderCancelled` — se dispara al cancelar pedido. Payload: order con razón/fecha + previousStatus.
  - Los 3 implementan `ShouldBroadcastNow` (sin cola, instantáneo). Canal privado `restaurant.{id}`.
- [x] **routes/channels.php**: Canal privado `restaurant.{restaurantId}` con autorización por `restaurant_id` del usuario (multitenancy).
- [x] **bootstrap.js**: Inicialización de Echo con broadcaster `reverb`, host/port/scheme desde `VITE_REVERB_*`.
- [x] **API OrderController**: `broadcast(new OrderCreated($order))` después de crear pedido exitosamente.
- [x] **Admin OrderController**: `broadcast(new OrderStatusChanged(...))->toOthers()` en advanceStatus, `broadcast(new OrderCancelled(...))->toOthers()` en cancel. Usa `toOthers()` para que el admin que hizo la acción no reciba el evento duplicado.
- [x] **Orders/Index.vue**: Suscripción a canal `restaurant.{restaurantId}` en `onMounted`. Escucha 3 eventos:
  - `OrderCreated` → agrega card a columna "Recibido" (si está en el rango de fecha/sucursal activo).
  - `OrderStatusChanged` → mueve card de columna anterior a nueva columna.
  - `OrderCancelled` → mueve card a columna "Cancelado".
  - Limpieza en `onUnmounted` para dejar el canal al navegar fuera.
- [x] Tests: 190 tests pasando (12 nuevos en BroadcastingTest: advance broadcasts event, channel is private, payload has previousStatus, cancel broadcasts event, cancelled payload has reason, created channel, created payload, API broadcasts created, no broadcast on failed advance, no broadcast on forbidden cancel, channel auth own restaurant, channel auth rejects other).

### WebSocket fix — 13 de marzo de 2026
- [x] **CSRF meta tag**: Agregado `<meta name="csrf-token">` en `app.blade.php` para que Echo pueda autenticar canales privados.
- [x] **Echo authorizer**: `bootstrap.js` ahora usa `authorizer` con axios para el POST a `/broadcasting/auth` (incluye CSRF automáticamente).
- [x] **Puerto 8080 expuesto**: `compose.yaml` ahora mapea `${REVERB_PORT:-8080}:${REVERB_PORT:-8080}` para que el browser alcance Reverb dentro del container.

### Lógica de periodo/vigencia — 13 de marzo de 2026
- [x] **Periodo no iniciado**: `LimitService::limitReason()` retorna `'period_not_started'` cuando `now() < orders_limit_start`. Antes no se validaba y el restaurante podía operar antes de su periodo.
- [x] **Periodo expirado**: `LimitService::limitReason()` retorna `'period_expired'` cuando `now() > orders_limit_end`.
- [x] **Límite alcanzado**: `LimitService::limitReason()` retorna `'limit_reached'` cuando el conteo >= límite.
- [x] **API `limit_reason`**: `RestaurantResource` ahora expone campo `limit_reason` (`null`, `'period_not_started'`, `'period_expired'`, `'limit_reached'`).
- [x] **CartBar.vue**: Ahora checa `orders_limit_reached` además de `is_open`. Botón se desactiva cuando el restaurante no está disponible.
- [x] **Visibilitychange refresh**: `App.vue` re-fetch `fetchRestaurant()` cuando el usuario regresa a la tab, actualizando el estado en tiempo real.
- [x] **Mensajes diferenciados**: `MenuHome.vue` muestra banners distintos para periodo expirado, periodo no iniciado y límite alcanzado.
- [x] Tests: 194 tests pasando (4 nuevos: period not started returns 422, expired period returns 422, API reports period_expired, API reports period_not_started).

### Notificaciones por correo — 13 de marzo de 2026
- [x] **Migración**: `notify_new_orders` boolean (default true) en tabla `restaurants`.
- [x] **Restaurant model**: Trait `Notifiable`, `routeNotificationForMail()` envía a todos los usuarios del restaurante.
- [x] **NewOrderNotification**: Notificación por email con folio, fecha, tipo de entrega, sucursal, cliente, productos con modificadores, dirección (si delivery), método de pago, subtotal/envío/total, botón "Ver pedido".
- [x] **Api\OrderController**: Envía notificación después de broadcast y creación exitosa del pedido.
- [x] **Settings/General.vue**: Switch "Recibir correo cuando entre un nuevo pedido" en sección "Notificaciones".
- [x] **SettingsController + UpdateGeneralSettingsRequest**: Expone y valida `notify_new_orders`.
- [x] Tests: 201 tests pasando (7 nuevos en OrderNotificationTest: sent on new order, not sent when disabled, contains order details, routes to all users, failed notification doesn't block order, toggle preference, general page shows toggle).

### Snapshot columns y delivery hardening — 13 de marzo de 2026
- [x] **Migración**: `product_name` (string) y `production_cost` (decimal) en `order_items`. `modifier_option_name` (string) y `production_cost` (decimal) en `order_item_modifiers`. Backfill desde tablas vivas.
- [x] **OrderService**: Al crear pedido, graba snapshots de `product_name`, `production_cost` en order_items y `modifier_option_name`, `production_cost` en order_item_modifiers. Mensaje WhatsApp usa campos snapshot.
- [x] **OrderService**: Removida dependencia de `HaversineService`. `getDrivingDistance()` lanza `ValidationException` si Google Maps falla (sin fallback).
- [x] **DeliveryService**: `MAX_CANDIDATES` reducido de 3 a 1. Removido fallback Haversine — lanza `DomainException` si Google falla o retorna `PHP_FLOAT_MAX`.
- [x] **StatisticsService**: `netProfit()` ahora usa columnas snapshot (`oi.production_cost`, `oim.production_cost`) en vez de joins a `products`/`modifier_options`.
- [x] **OrderController**: `show()` carga `items.modifiers` en lugar de `items.product` e `items.modifiers.modifierOption`.
- [x] **Orders/Show.vue**: Usa campos snapshot. Muestra `distance_km` en card de delivery. Muestra profit por item y ganancia neta total.
- [x] Tests: 200 tests pasando (2 nuevos: Google Maps failure tests. Actualizados tests multi-branch para 1 candidato).

### Mapa interactivo de pedidos — 13 de marzo de 2026
- [x] **MapController**: Nuevo controlador con ruta `GET /map` (middleware auth + tenant). Retorna pedidos geolocalizados, sucursales, KPIs y filtros via Inertia.
- [x] **Map/Index.vue**: Página con Google Maps JS API integrada. Markers interactivos para pedidos geolocalizados y sucursales. Sidebar con KPIs. Filtros por fecha, sucursal y estatus.
- [x] **Sidebar**: Nuevo item "Mapa" en AppLayout.vue.
- [x] Tests: 213 tests pasando (13 nuevos en MapControllerTest).

## Bugs corregidos — 12 de marzo de 2026

- **[Admin — Orders/Index.vue (Kanban)]** — Después de arrastrar una card entre columnas, ninguna card era clickeable. Causa: `didDrag` (flag anti-click accidental) se seteaba a `true` en `onDrop()` pero nunca se reseteaba en `onDragEnd()`. Fix: `setTimeout(() => { didDrag = false }, 0)` en `onDragEnd` — resetea después del event cycle para bloquear el click del drag pero permitir clicks futuros.

## Bugs corregidos — 1 de marzo de 2026

- **[Products/Edit, CategoryModal, Settings/General]** — Upload de imagen fallaba con 405 Method Not Allowed. Causa: `form.post()` con `method: 'put'` en opciones (Inertia lo ignora). Fix: `_method: 'put'` en los datos del formulario (method spoofing de Laravel).
- **[Client SPA — MenuHome, ProductModal, CartSummary, cart store]** — Imágenes de productos/categorías no se mostraban. Causa: usaban `product.image_path` (ruta interna) en vez de `product.image_url` (URL completa generada por el accessor).
- **[Client SPA — PaymentConfirmation]** — Métodos de pago no aparecían. Causa: filtro `.filter((pm) => pm.is_active)` en el cliente, pero `is_active` no se incluye en `PaymentMethodResource` (la API ya filtra los activos en backend). Fix: remover el filtro redundante.
- **[Client SPA — vite.config.js]** — Conflicto de puertos: cliente y Sail admin ambos en 5173. Fix: cliente movido a puerto 5174.

## Bugs corregidos — 2 de marzo de 2026

- **[API — RestaurantResource]** — No retornaba `branches` ni flags de delivery methods como campos top-level. Causa: faltaban en el resource. Fix: agregados `allows_delivery`, `allows_pickup`, `allows_dine_in`, `branches`, `schedules`, `is_open`.
- **[Client SPA — api.js]** — `VITE_API_BASE_URL` vacío fallaba en móvil. Causa: `"" || 'http://localhost'` evaluaba a `'http://localhost'`. Fix: `??` en vez de `||`.
- **[Client SPA — vite.config.js]** — ngrok bloqueado por Vite. Fix: `allowedHosts: true` + proxy `/api` a localhost.
- **[Client SPA — api.js]** — Interstitial de ngrok bloqueaba requests API. Fix: header `ngrok-skip-browser-warning: true`.

---

## Notas técnicas importantes

- **TenantScope** retorna 404 (no 403) para recursos de otro tenant — correcto desde seguridad.
- **`production_cost`** nunca se expone en la API pública del cliente. Se guarda como snapshot en `order_items.production_cost` y `order_item_modifiers.production_cost` al crear pedido.
- **Snapshot columns**: `order_items` guarda `product_name` y `production_cost`; `order_item_modifiers` guarda `modifier_option_name` y `production_cost`. Esto desacopla los reportes (StatisticsService, Orders/Show.vue) de cambios posteriores en el catálogo.
- **Dirección del cliente**: se ingresa manualmente. El pin del mapa solo obtiene coordenadas. Sin geocoding inverso.
- **Horarios a nivel restaurante**: los horarios (opens_at/closes_at por día) son por restaurante, no por sucursal. `Restaurant::isCurrentlyOpen()` determina `is_open` en la API. El SPA deshabilita visualmente el menú cuando está cerrado.
- **Menú global**: compartido entre todas las sucursales de un restaurante.
- **Billing SaaS (completo)**: Rama `pagos_stripe`. Laravel Cashier v16.5. Tablas: `plans`, `billing_settings`, `billing_audits`, `subscriptions`, `subscription_items`. Modelos: Plan, BillingSetting, BillingAudit. Restaurant: Billable trait, plan_id, status (7 estados), grace_period_ends_at, subscription_ends_at, stripe_id. LimitService dual (plan → legacy). StripeWebhookController (5 eventos). SubscriptionController (index, checkout, swap, cancel, resume, portal). SuperAdmin: PlanController CRUD, BillingSettingsController, updatePlan, extendGrace. Vue: Settings/Subscription.vue, billing banners en AppLayout, Plans/Index+Create+Edit, BillingSettings. 4 cron jobs (check-grace, check-canceled, send-reminders, reconcile). GraceExpiringNotification. billing:backfill-plans command. 439 tests pasando (49 nuevos). Spec: `docs/BILLING_SPEC.md`.
- **WhatsApp**: solo link `wa.me`. Sin WhatsApp Business API.
- **Seeder**: el `DatabaseSeeder` actual crea un usuario sin `restaurant_id` — no sirve para pruebas del panel. Se necesita un seeder de desarrollo con restaurante + admin completos.

---

_PideAqui — Documento de estado interno — Marzo 2026_
