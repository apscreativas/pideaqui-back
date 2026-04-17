# PideAqui — Fases de Implementación

> Orden de implementación recomendado para el MVP.
> Cada fase tiene dependencias sobre la anterior.

---

## Resumen de Fases

| Fase | Nombre | Prioridad | Dependencias |
|---|---|---|---|
| 1 | Base de Datos y Modelos | Crítica | Ninguna |
| 2 | Autenticación y Multitenancy | Crítica | Fase 1 |
| 3 | Panel Admin — Menú y Sucursales | Alta | Fase 2 |
| 4 | API Pública — Menú y Sucursales | Alta | Fase 3 |
| 5 | Servicio de Delivery | Alta | Fase 4 |
| 6 | API Pública — Crear Pedido | Alta | Fase 5 |
| 7 | Panel Admin — Pedidos y Dashboard | Media | Fase 6 |
| 8 | Panel Admin — Configuración | Media | Fase 2 |
| 9 | Panel SuperAdmin | Media | Fase 1, 2 |
| 10 | Frontend del Cliente (SPA) | Alta | Fase 4, 5, 6 |
| 11 | Jobs y Tareas Programadas | Baja | Fase 1 |
| 12 | Integraciones Externas | Transversal | Varias |
| 13 | Tests | Continua | Todo |

---

## Fase 1 — Base de Datos y Modelos del Dominio

> **Prioridad: Crítica.** Todo lo demás depende de esta fase.

### Migraciones a crear

```
restaurants
branches
branch_schedules
categories
products
modifier_groups
modifier_options
payment_methods
delivery_ranges
customers
orders
order_items
order_item_modifiers
```

También: agregar `restaurant_id` a la tabla `users` existente.

### Modelos Eloquent a crear

- `Restaurant` — relaciones: branches, categories, products, orders, paymentMethods, deliveryRanges
- `Branch` — relaciones: restaurant, schedules, orders
- `BranchSchedule` — relación: branch
- `Category` — relaciones: restaurant, products
- `Product` — relaciones: restaurant, category, modifierGroups
- `ModifierGroup` — relaciones: product, options
- `ModifierOption` — relación: modifierGroup
- `PaymentMethod` — relación: restaurant
- `DeliveryRange` — relación: restaurant
- `Customer` — relación: orders
- `Order` — relaciones: restaurant, branch, customer, items
- `OrderItem` — relaciones: order, product, modifiers
- `OrderItemModifier` — relaciones: orderItem, modifierOption
- Actualizar `User` — agregar relación `restaurant()`

**Documentación de referencia:** `docs/DATABASE.md`

---

## Fase 2 — Autenticación y Multitenancy

> Depende de: Fase 1

- Guard `web` para Admin Restaurante (`User` + `restaurant_id`).
- Guard `superadmin` para SuperAdmin (modelo `SuperAdmin` + tabla `super_admins`).
- Login Admin Restaurante (`/login`).
- Login SuperAdmin (`/super/login`).
- Recuperar y restablecer contraseña (Admin Restaurante).
- Middleware `EnsureTenantContext` — inyecta el contexto del restaurante en todas las rutas del panel admin.
- Policies para todos los modelos del dominio.

**Documentación de referencia:** `docs/modules/01-auth.md`, `docs/modules/07-superadmin.md`, `docs/ARCHITECTURE.md §4 y §5`

---

## Fase 3 — Panel Admin: Menú y Sucursales

> Depende de: Fase 2

### 3a — Gestión del Menú
- `CategoryController` — CRUD completo.
- `ProductController` — CRUD con upload de imagen.
- `ModifierGroupController` + `ModifierOptionController`.
- Vistas Inertia: lista acordeón, crear/editar producto, editor de categoría (modal), gestión de modificadores.
- `ImageUploadService` — subida de imágenes a cloud storage.

**Documentación de referencia:** `docs/modules/04-menu.md`
**Pantallas:** `ar_07`, `ar_08`, `ar_09`, `ar_10`

### 3b — Gestión de Sucursales
- `BranchController` — CRUD con validación de `max_branches`.
- `BranchScheduleController` — horarios por día de la semana.
- Vistas Inertia: lista de sucursales, crear/editar (con Google Maps), horarios.

**Documentación de referencia:** `docs/modules/05-branches.md`
**Pantallas:** `ar_11`, `ar_12`, `ar_13`

---

## Fase 4 — API Pública: Menú y Sucursales

> Depende de: Fase 3

- Crear `routes/api.php`.
- Middleware de autenticación por `access_token` (`AuthenticateRestaurantToken`).
- Endpoints:
  - `GET /api/restaurant`
  - `GET /api/menu`
  - `GET /api/branches`
- API Resources correspondientes (excluyendo `production_cost`).

**Documentación de referencia:** `docs/modules/10-api.md`

---

## Fase 5 — Servicio de Delivery

> Depende de: Fase 4

- `HaversineService` — distancia en línea recta.
- `GoogleMapsService` — wrapper de Google Distance Matrix API.
- `DeliveryService` — orquesta pre-filtro Haversine + Distance Matrix + cálculo de rangos + validación de horario.
- Endpoint: `POST /api/delivery/calculate`.
- `DeliveryCalculationResource`.

**Documentación de referencia:** `docs/modules/09-delivery-service.md`, `docs/modules/10-api.md`

---

## Fase 6 — API Pública: Crear Pedido

> Depende de: Fase 5

- Endpoint: `POST /api/orders`.
- `OrderService` — creación de pedido, validación de límites, cálculo de totales en backend.
- `LimitService` — verificar y controlar el límite mensual de pedidos.
- `StoreOrderRequest` — validación completa del pedido.
- `OrderConfirmationResource` — devuelve `order_id`, `branch_whatsapp`, `whatsapp_message`.
- Lógica de construcción del mensaje de WhatsApp.

**Documentación de referencia:** `docs/modules/10-api.md §POST /api/orders`, `docs/modules/08-customer-flow.md`

---

## Fase 7 — Panel Admin: Pedidos y Dashboard

> Depende de: Fase 6

### 7a — Pedidos
- `OrderController` — lista Kanban, detalle, cambio de estatus.
- Vistas Inertia: Kanban con filtros, detalle de pedido con comanda completa.
- Polling de pedidos nuevos (endpoint: `GET /api/admin/orders/new-count`).
- Alerta visual (badge pulsante en sidebar).
- Impresión de comanda (CSS print).
- Indicador de pedidos del mes vs. límite.

**Pantallas:** `ar_05`, `ar_06`

### 7b — Dashboard
- `DashboardController` — KPIs del día y del mes.
- `StatisticsService` — pedidos por sucursal, ganancia neta, contadores.
- Vista Inertia: 4 cards KPI + gráficas + pedidos recientes.

**Pantallas:** `ar_04`

**Documentación de referencia:** `docs/modules/03-orders.md`, `docs/modules/02-dashboard.md`

---

## Fase 8 — Panel Admin: Configuración

> Depende de: Fase 2

- `SettingsController` — configuración general del restaurante.
- `PaymentMethodController` — toggles de métodos de pago y datos bancarios.
- `DeliveryRangeController` — CRUD de rangos de distancia.
- `ProfileController` — datos del admin autenticado.
- Vistas Inertia: general, métodos de entrega, tarifas de envío, métodos de pago, QR/link, mi cuenta, mis límites.

**Documentación de referencia:** `docs/modules/06-settings.md`
**Pantallas:** `ar_14` a `ar_20`

---

## Fase 9 — Panel SuperAdmin

> Depende de: Fase 1, Fase 2

- Guard `superadmin` + modelo `SuperAdmin`.
- `SuperAdmin\AuthController` — login/logout.
- `SuperAdmin\RestaurantController` — CRUD de restaurantes + toggle activo.
- `SuperAdmin\DashboardController` — KPIs globales.
- `SuperAdmin\StatisticsController` — gráficas globales.
- Vistas Inertia: login, dashboard, lista de restaurantes, detalle, estadísticas.

**Documentación de referencia:** `docs/modules/07-superadmin.md`

---

## Fase 10 — Frontend del Cliente (SPA)

> Depende de: Fase 4, Fase 5, Fase 6

- Configurar vue-router y Pinia en `client/src/main.js`.
- Crear stores: `cart`, `order`, `restaurant`.
- Configurar `axios` con interceptor para `Authorization: Bearer {token}` desde `.env`.
- Vistas:
  - `c_01` MenuHome — header, categorías, grid de productos, carrito flotante.
  - `c_02` ProductDetail — modal con modificadores y nota libre.
  - `c_03` CartSummary — resumen editable.
  - `c_04` DeliveryLocation — selector de entrega, mapa Google Maps, formulario, llamada a `/api/delivery/calculate`.
  - `c_05` PaymentConfirmation — datos del cliente, método de pago, resumen, `POST /api/orders`.
  - `c_06` OrderConfirmed — pantalla de éxito.
- Lógica de cookies (lectura, escritura, expiración 90 días).
- Generación del link de WhatsApp.
- Manejo de estados especiales: restaurante inactivo, límite mensual alcanzado, fuera de cobertura, fuera de horario.

**Documentación de referencia:** `docs/modules/08-customer-flow.md`
**Pantallas:** `c_01` a `c_06`

---

## Fase 11 — Jobs y Tareas Programadas

> Depende de: Fase 1

- (Opcional) `ResetMonthlyOrderCountJob` — si se usa un campo `monthly_order_count` en `restaurants`.
  - Alternativa sin job: contar pedidos del mes con query directa (`WHERE created_at >= inicio_del_mes`). **Esta es la opción recomendada** — evita un job y es siempre exacta.
- Scheduler configurado en `bootstrap/app.php` o `routes/console.php`.

---

## Fase 12 — Integraciones Externas

> Transversal — algunos elementos se integran en fases anteriores.

| Integración | Fase en que se integra | Costo |
|---|---|---|
| **Google Maps JavaScript API** | Fase 3b (mapa en sucursales) + Fase 10 (mapa cliente) | Pago |
| **Google Distance Matrix API** | Fase 5 (DeliveryService) | Pago |
| **Cloud Storage (S3 o compatible)** | Fase 3a (imágenes de menú) | Pago |
| **Geolocalización del navegador** | Fase 10 (Paso 2 del cliente) | Gratis |
| **WhatsApp (wa.me link)** | Fase 6 y 10 | Gratis |

---

## Fase 13 — Tests

> Continua — escribir tests al implementar cada fase.

- Tests de autenticación (login, guards, middleware de tenant).
- Tests de multitenancy (un restaurante no puede acceder a datos de otro).
- Feature tests CRUD: Restaurant, Branch, Category, Product, Order.
- Tests de validación de límites (mensual, sucursales).
- Tests de cálculo de tarifas de envío por rangos.
- Tests de validación de horarios de sucursal.
- Tests de endpoints de API (con token válido e inválido).
- Tests de autorización (policies).
- Tests del `DeliveryService` (mock de Google Distance Matrix).

**Stack:** PHPUnit, Feature tests principalmente. Sin Pest.

---

## Diagrama de Dependencias

```
Fase 1: DB + Modelos
    ↓
Fase 2: Auth + Multitenancy
    ↓                ↓
Fase 3: Admin     Fase 9: SuperAdmin
Menú + Sucursales
    ↓
Fase 4: API Menú + Sucursales
    ↓                   ↓
Fase 5: DeliveryService  Fase 8: Config Admin
    ↓
Fase 6: API Orders
    ↓            ↓
Fase 7: Admin    Fase 10: SPA Cliente
Pedidos + Dashboard
    ↓
Fase 11: Jobs (opcional)
Fase 12: Integraciones (transversal)
Fase 13: Tests (continua)
```

---

### Mejoras Post-MVP (implementadas)

Las siguientes mejoras fueron implementadas después de completar las 13 fases del MVP:

- **Cancelaciones** — Módulo de análisis de pedidos cancelados con KPIs, desglose por razón/sucursal/día, y tabla de pedidos. Ver [11-cancellations.md](./modules/11-cancellations.md).
- **Mapa operativo** — Mapa interactivo con Google Maps JS API para visualizar pedidos y sucursales geográficamente. Ver [12-map.md](./modules/12-map.md).
- **WebSockets** — Comunicación en tiempo real con Laravel Reverb + Laravel Echo para actualizar el Kanban automáticamente. Ver [13-websockets.md](./modules/13-websockets.md).
- **Notificaciones email** — `NewOrderNotification` vía mail cuando llega un pedido nuevo (toggle `notify_new_orders` en settings).
- **Snapshot columns** — `order_items` y `order_item_modifiers` guardan `product_name`, `modifier_option_name` y `production_cost` al momento de crear el pedido, desacoplando el historial de cambios futuros al menú.
- **Login unificado** — SuperAdmin con tema claro (bg-white), consistente con el admin de restaurante.
- **Drag-and-drop menú** — Reorden de categorías y productos por arrastre (HTML5 DnD nativo) con `sort_order` automático.
- **Dirección estructurada** — Dirección del cliente con campos separados en el flujo de checkout.
- **Límites por periodo** — `orders_limit` con fechas `orders_limit_start`/`orders_limit_end` (reemplaza `max_monthly_orders`). LimitService con `limitReason()`.
- **Horarios a nivel restaurante** — `restaurant_schedules` (no por sucursal). `Restaurant::isCurrentlyOpen()` con soporte overnight.
- **Cash amount** — Campo para monto con el que paga el cliente en efectivo.
- **Social media fields** — Campos de redes sociales en configuración del restaurante.
- **Regenerar token + reset password desde SuperAdmin** — Acciones administrativas en el detalle del restaurante.

---

_PideAqui — Fases de Implementación v1.0 — Febrero 2026_
