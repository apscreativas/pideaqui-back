# Módulo 03 — Gestión de Pedidos

> Pantallas de referencia: `ar_05_order_kanban_board`, `ar_06_order_details_view`

---

## Descripción General

Módulo central del panel del administrador. Permite visualizar, filtrar y gestionar todos los pedidos del restaurante en tiempo real. Los pedidos llegan desde el flujo del cliente (ver [08-customer-flow.md](./08-customer-flow.md)) y se muestran simultáneamente en WhatsApp (sucursal) y en este panel.

El flujo del estatus es **unidireccional** — no se puede revertir:

```
Recibido → En preparación → En camino → Entregado
```

Adicionalmente, cualquier pedido que **no** esté en estatus `delivered` puede ser **cancelado**:

```
received / preparing / on_the_way  →  cancelled
```

---

## Pantallas

### `ar_05` — Tablero Kanban de Pedidos (`/orders`)

**Columnas del Kanban:** Una por estatus.

| Columna | Estatus DB | Color de encabezado |
|---|---|---|
| Recibidos | `received` | Azul |
| En preparación | `preparing` | Naranja/primario |
| En camino | `on_the_way` | Morado |
| Entregados | `delivered` | Verde |
| Cancelados | `cancelled` | Rojo |

**Tarjeta de pedido (card):**
- ID del pedido (ej. `#0042`).
- Nombre del cliente.
- Tipo de entrega: 🛵 Domicilio / 🏃 Recoger / 🍽️ Comer aquí.
- Sucursal asignada (badge coloreado).
- Método de pago.
- Total del pedido.
- Hora del pedido (o hora programada si aplica).
- Botón de avanzar al siguiente estatus.
- Botón de cancelar (abre modal de cancelación).

**Drag-and-drop Kanban:**
- HTML5 DnD nativo (sin librería externa).
- Optimistic UI: el estado local se actualiza inmediatamente; se revierte en caso de error.
- Solo se permiten transiciones válidas (hacia adelante: received -> preparing -> on_the_way -> delivered).

**Filtros disponibles:**
- Por sucursal.
- Por estatus.
- Por fecha.

**Alertas:**
- Alerta visual (badge pulsante rojo) en el ícono de la nav cuando llegan pedidos nuevos.
- Alerta sonora al recibir pedido nuevo (polling o broadcasting).
- Indicador "X pedidos del mes / límite" en la parte superior del Kanban.

### `ar_07` — Historial de pedidos (`/orders/history`)

Reporte tabular con sumatorias para que el restaurante revise pedidos de un rango arbitrario sin perder el contexto del Kanban operativo (que sólo muestra pedidos vigentes).

**Acceso:** admin y operator. El operator ve sólo los pedidos de las sucursales asignadas (vía `User::allowedBranchIds()`).

**Filtros:**
- **Rango de fechas** — date pickers + presets rápidos (Hoy, Ayer, 7 días, Mes). Default: últimos 7 días.
- **Sucursal** — `branches` del restaurante.
- **Estatus** — `Todos` / `Entregados` / `Cancelados`. Default: Todos.
- **Por página** — 20 / 50 / 100.

**Columnas de la tabla:** Pedido (`#0001` + badge "CANCELADO" si aplica) · Fecha-Hora · Cliente (nombre) · Teléfono · Sucursal · Total · Costo · Utilidad · Detalle.

**Sumatorias:** se calculan sobre el **rango filtrado completo**, no sobre la página visible. Aparecen en 4 KPIs arriba (Pedidos / Total / Costo / Utilidad) y en `<tfoot>` de la tabla. Implementación: dos consultas a la misma `$base` query — una `get()` con `items.modifiers` precargados para sumar en PHP, otra `paginate()` para mostrar.

**Cálculo de costo y utilidad:** vive en métodos del modelo `Order`:
- `Order::productionCost()` — suma de `production_cost` snapshot de cada `OrderItem` × qty + suma de `production_cost` snapshot de cada `OrderItemModifier` × qty del item padre.
- `Order::profit()` — `subtotal − productionCost() − discount_amount`. **No** descuenta `delivery_cost` (es passthrough al repartidor) ni `cash_amount`.

**Pedidos cancelados:** se incluyen por default (filas en rojo + badge). Si el usuario quiere ver únicamente venta real, usa el filtro "Entregados".

**Detalle:** el botón "open_in_new" abre `/orders/{id}` en **nueva pestaña** (`<a target="_blank" rel="noopener">`, no Inertia `<Link>`) — diferencia consciente con el resto de la app, para no perder el contexto del filtro al revisar pedidos uno por uno.

**No incluye columna "Correo":** la tabla `customers` no captura `email` (el SPA cliente nunca lo pide). Pendiente como scope futuro si se decide capturar.

---

### `ar_06` — Detalle del Pedido (`/orders/{id}`)

**Secciones de la comanda completa:**

1. **Cabecera:** ID, estatus actual, hora y fecha, sucursal asignada.
2. **Datos del cliente:** Nombre, teléfono.
3. **Entrega:** Tipo (domicilio/recoger/comer aquí), dirección completa, referencias, link de Google Maps con coordenadas del cliente, distancia en km (`distance_km`), tiempo estimado.
4. **Productos:** Lista con modificadores seleccionados, nota libre por ítem y subtotal por línea. Los nombres y costos se muestran desde **snapshots** (`product_name`, `production_cost` en `order_items`; `modifier_option_name`, `production_cost` en `order_item_modifiers`), no desde los datos actuales del producto.
5. **Ganancia:** Ganancia por item (verde) y ganancia neta total, calculada usando `production_cost` del snapshot.
6. **Resumen económico:** Subtotal, costo de envío, **total**.
7. **Pago:** Método seleccionado. Si es transferencia: datos bancarios del restaurante.
8. **Programación:** Si aplica, hora programada de entrega/recogida.
9. **Acciones:** Botón para avanzar al siguiente estatus + botón de imprimir comanda.
10. **Banner de cancelación:** Si el pedido está cancelado, se muestra un banner con la razón de cancelación.

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `Order` | `orders` | Pedido principal |
| `OrderItem` | `order_items` | Cada producto del pedido |
| `OrderItemModifier` | `order_item_modifiers` | Modificadores seleccionados por ítem |
| `Customer` | `customers` | Nombre y teléfono del cliente |
| `Branch` | `branches` | Sucursal asignada al pedido |
| `Product` | `products` | Nombre del producto (referencia) |
| `ModifierOption` | `modifier_options` | Nombre del modificador seleccionado |
| `Restaurant` | `restaurants` | Para `orders_limit` (indicador del periodo) |

### Campos clave del modelo `Order`

| Campo | Tipo | Descripción |
|---|---|---|
| `status` | enum | `received`, `preparing`, `on_the_way`, `delivered`, `cancelled` |
| `delivery_type` | enum | `delivery`, `pickup`, `dine_in` |
| `scheduled_at` | timestamp\|null | Hora programada (null = lo antes posible) |
| `subtotal` | decimal | Productos + modificadores |
| `delivery_cost` | decimal | Costo de envío según rango |
| `total` | decimal | subtotal + delivery_cost |
| `distance_km` | decimal\|null | Distancia real por calles |
| `address` | text\|null | Dirección manual del cliente |
| `address_references` | text\|null | Referencias del cliente |
| `latitude` / `longitude` | decimal\|null | Coordenadas del pin del mapa |
| `payment_method` | enum | `cash`, `terminal`, `transfer` |

---

## Reglas de Negocio

- El estatus **solo avanza hacia adelante**. No se puede revertir ni saltar pasos.
- `delivered` es el estatus final. Una vez entregado, el pedido no puede cambiar de estatus.
- El admin **puede crear pedidos manualmente** desde el botón **Nuevo pedido** del Tablero (`/orders/create`). El pedido manual reutiliza `OrderService::store` con `source='manual'` y queda registrado con `OrderEvent.user_id` apuntando al admin que lo creó. Aplica el mismo `LimitService`, anti-tampering, validación de modificadores, `DeliveryService` (cuando es delivery), método de pago activo y todas las demás validaciones que un pedido API. El cliente se busca por `phone` dentro del restaurante (si existe se reutiliza, si no se crea con un token sintético `manual_<sha1>`). Aparece directamente en la columna **Recibido** y dispara `OrderCreated` por broadcast.
- El conteo de pedidos del periodo se incrementa al crearse cada `Order` (status `received`). El límite lo controla el `LimitService` usando `orders_limit`, `orders_limit_start` y `orders_limit_end`.
- Si el restaurante ha alcanzado su `orders_limit` (dentro del periodo definido), el endpoint de creación de pedidos en la API retorna error y bloquea nuevos pedidos.
- El link de mapa en el detalle de pedido se construye con las coordenadas almacenadas: `https://maps.google.com/?q={latitude},{longitude}`.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[08-customer-flow.md](./08-customer-flow.md)** | El cliente crea pedidos desde el SPA (Paso 3). Los pedidos aparecen aquí al instante. |
| **[10-api.md](./10-api.md)** | El endpoint `POST /api/orders` crea el pedido en DB. Este módulo lo visualiza. |
| **[05-branches.md](./05-branches.md)** | Cada pedido está asignado a una sucursal. El filtro por sucursal depende de las sucursales activas. |
| **[06-settings.md](./06-settings.md)** | Los métodos de pago habilitados y las tarifas de envío afectan los datos que se muestran en el detalle del pedido. |
| **[07-superadmin.md](./07-superadmin.md)** | El `orders_limit` (configurado por SuperAdmin) determina si se bloquean nuevos pedidos dentro del periodo. |
| **[02-dashboard.md](./02-dashboard.md)** | El dashboard usa datos de `orders` para KPIs. Cambios de estatus acá afectan los contadores del dashboard. |

---

## Implementación Backend

```
Routes:
  GET  /orders          → OrderController@index   (Kanban)
  GET  /orders/{id}     → OrderController@show    (Detalle)
  PUT  /orders/{id}/status → OrderController@advanceStatus
  PUT  /orders/{id}/cancel → OrderController@cancel

Form Requests:
  AdvanceOrderStatusRequest
    - rules: status must be next in sequence

  CancelOrderRequest
    - cancellation_reason: required, string, max:500

Policy:
  OrderPolicy → solo el admin del restaurante dueño del pedido
```

### Modal de Cancelacion

Al presionar "Cancelar pedido" en el Kanban o en el detalle, se abre un modal con:

- **5 razones preestablecidas** (radio buttons): el admin selecciona una razon comun.
- **Textarea personalizado:** para escribir una razon libre si ninguna preestablecida aplica.
- El campo `cancellation_reason` es requerido (max 500 caracteres).
- Al confirmar, se envia `PUT /orders/{id}/cancel` con la razon.
- En el detalle del pedido (`Orders/Show.vue`), si el estatus es `cancelled`, se muestra un **banner de cancelacion** con la razon.

### WebSocket — Actualizaciones en Tiempo Real

Se usa **Laravel Reverb** + **Laravel Echo** para notificaciones push al panel admin.

**Canal privado:** `restaurant.{restaurantId}` (autenticado via `routes/channels.php`).

**Eventos broadcast:**

| Evento | Disparado cuando | Tipo |
|---|---|---|
| `OrderCreated` | Un cliente crea un pedido desde la API | `ShouldBroadcastNow` |
| `OrderStatusChanged` | El admin avanza el estatus de un pedido | `ShouldBroadcastNow` |
| `OrderCancelled` | El admin cancela un pedido | `ShouldBroadcastNow` |

**Suscripcion en frontend:**
- `Orders/Index.vue` se suscribe al canal en `onMounted` y se desuscribe en `onUnmounted`.
- Al recibir un evento, se actualiza la lista del Kanban en tiempo real sin necesidad de recargar.

**Broadcast decoupling:**
- Los controllers (`advanceStatus`, `cancel`) envuelven `broadcast()` en `try/catch`.
- Si Laravel Reverb no esta corriendo, el cambio de estatus se guarda igual en la base de datos y se logea un warning. La funcionalidad core no depende de WebSockets.

---

## Notas de Diseño

### Kanban (ar_05)
- Columnas en scroll horizontal si el contenido desborda en pantallas pequeñas.
- Cards con sombra `shadow-sm`, borde superior coloreado según estatus.
- Badge de sucursal con color distinto por sucursal (para diferenciar visualmente en multi-sucursal).
- El badge de alerta de pedidos nuevos es un círculo rojo pulsante sobre el ícono de pedidos en el sidebar.

### Detalle (ar_06)
- Layout de dos columnas en desktop: datos del cliente/entrega a la izquierda, productos y total a la derecha.
- Botón de avance de estatus en color primario (`#FF5722`), prominente.
- Botón de imprimir comanda en gris secundario.
- Link de mapa como texto con ícono de `open_in_new` (abre en nueva pestaña).

---

## Edición Post-Creación + Audit Trail

> **Agregado Mar 2026** — 33 tests en `tests/Feature/OrderEditTest.php`.

Los admins pueden **editar un pedido después de crearlo**, pero sólo mientras esté en status `received` o `preparing`. Una vez que pasa a `on_the_way`, `delivered` o `cancelled`, el botón "Editar" se oculta.

### Qué se puede editar

Tres tipos de cambios, cada uno opcional e independiente en la misma request:

1. **Items del pedido** — agregar/quitar productos o promociones, cambiar cantidades, cambiar modificadores, cambiar notas. Se **re-snapshotean** los precios actuales (si el producto cambió de precio, se usa el precio actual). Recalcula `subtotal` y `total`. **`delivery_cost` no se recalcula** (queda inmutable — la decisión fue no re-geocodificar al editar).
2. **Dirección de entrega** — sólo el texto (`address_street`, `number`, `colony`, `references`) y opcionalmente el pin (`latitude` / `longitude`). **No recalcula delivery_cost** ni la sucursal asignada.
3. **Método de pago** — cambiar entre `cash`, `terminal`, `transfer`. Para `cash`, permite modificar `cash_amount`.

### Schema — Audit Trail

**Tabla `order_audits`** (migración `2026_03_24_124642_create_order_audits_table.php`):

| Columna | Tipo | Notas |
| --- | --- | --- |
| `order_id` | FK | |
| `user_id` | FK nullable | admin que hizo el cambio |
| `action` | string(30) | `items_modified`, `address_modified`, `location_modified`, `payment_method_changed`, `coupon_removed` |
| `changes` | JSON | diff estructurado (ver abajo) |
| `reason` | text nullable | razón escrita por el admin (opcional) |
| `old_total` | decimal | total antes del cambio |
| `new_total` | decimal | total después |
| `ip_address` | string(45) nullable | |
| `created_at` | timestamp | sin `updated_at` |

Índice: `(order_id, created_at)`.

**ALTER `orders`** (misma migración):

- `edited_at` — timestamp nullable, última edición
- `edit_count` — unsignedInteger default `0`

**Estructura del campo `changes`:**

```json
// action = items_modified
{ "added": [...], "removed": [...], "modified": [...], "coupon_removed": true? }

// action = address_modified
{ "before": { "address_street": "...", ... }, "after": { ... } }

// action = location_modified
{ "before": { "lat": 19.4, "lng": -99.1 }, "after": { ... } }

// action = payment_method_changed
{ "before": "cash", "after": "terminal" }
```

### `OrderEditService`

`app/Services/OrderEditService.php` — punto único de entrada:

```php
public function update(
    Order $order,
    array $validated,
    User $user,
    ?string $ipAddress = null,
): Order
```

**Flujo:**

1. Valida que `$order->isEditable()` — si no, `ValidationException`.
2. **Optimistic lock:** compara `$order->updated_at` con `$validated['expected_updated_at']`. Si difieren → `HttpException(409)`. Esto detecta ediciones concurrentes (dos admins editando el mismo pedido).
3. `DB::transaction()` + `lockForUpdate()` sobre la orden + re-valida el estado.
4. Procesa cada tipo de cambio si está presente:
   - `processItems($order, $validated)` — re-snapshot de precios, recalcula subtotal, valida modifiers (inline + catálogo), recalcula descuento del cupón si aplica
   - `processAddress($order, $validated)`
   - `processPaymentMethod($order, $validated)`
5. Cada processor retorna `['action' => ..., 'changes' => ...]` o `null`.
6. Actualiza `edited_at = now()`, incrementa `edit_count`.
7. Crea entries en `order_audits` con `old_total` / `new_total` / `reason` / `ip_address`.
8. Dispara `OrderUpdated` (broadcast) afuera de la transacción.

### `UpdateOrderRequest`

`app/Http/Requests/UpdateOrderRequest.php`

| Campo | Reglas |
| --- | --- |
| `expected_updated_at` | required, date |
| `items` | sometimes, array, min:1, max:50 |
| `items.*.product_id` \| `items.*.promotion_id` | uno u otro |
| `items.*.quantity` | integer, min:1, max:100 |
| `items.*.unit_price` | numeric, max:99999.99 |
| `items.*.modifiers.*.modifier_option_id` \| `modifier_option_template_id` | uno u otro; unicidad **por item** vía trait `ValidatesItemModifiers` (no `distinct` global — varios items pueden compartir la misma opción del catálogo) |
| `address_street`, `number`, `colony`, `references` | sometimes, nullable |
| `latitude`, `longitude` | sometimes, nullable |
| `payment_method` | sometimes, in:cash,terminal,transfer |
| `cash_amount` | nullable, numeric, min:0.01, max:100000, prohibited_unless:payment_method,cash |
| `reason` | nullable, string, max:500 |

### Policy

`OrderPolicy::edit($user, $order)`:

1. `$user->restaurant_id === $order->restaurant_id`
2. `$order->isEditable()` (status ∈ received/preparing)
3. `canAccessBranch($user, $order->branch_id)` — operators ven sólo sus sucursales asignadas

### Rutas

```
GET  /orders/{id}/edit   → OrderController@edit
PUT  /orders/{id}        → OrderController@update
```

### Event `OrderUpdated`

`app/Events/OrderUpdated.php` — implementa `ShouldBroadcastNow`, canal `restaurant.{restaurant_id}`, payload incluye `edit_count` y `edited_at`.

### UI

- **`Pages/Orders/Show.vue`** — botón "Editar" visible sólo si `canEdit === true`. Muestra un **historial de cambios collapsible** (`order.audits`) con timestamp, usuario, razón y el diff formateado por tipo de acción. Badge `old_total → new_total` en ámbar si difieren.
- **`Pages/Orders/Edit.vue`** — form con selector de productos, modal de modificadores inline, `MapPicker`, radio de payment methods, summary con diff contra el total original. Envía `expected_updated_at` en cada request.
- **`Pages/Orders/Index.vue`** — badge "editado" en las cards que tienen `edit_count > 0`. Escucha `OrderUpdated` en tiempo real.
- **OrderTicket** (comanda impresa) — muestra "(editado)" junto al ID del pedido.

### Comportamiento con cupones

Si el pedido tiene cupón aplicado y al editar items el `subtotal` queda por debajo de `min_purchase`:

- Se **remueve el cupón** (nullifica `coupon_id`, `coupon_code`, `discount_amount = 0`)
- Se **libera el `coupon_use`** (elimina el registro — el cliente puede volver a usarlo)
- Se registra en `audits` con `changes.coupon_removed = true`

Si `subtotal >= min_purchase`, se recalcula el `discount_amount` con el nuevo subtotal.

Ver [16-coupons.md](./16-coupons.md) → sección "Recálculo al editar".

---

## Pedidos Manuales (`orders.source`)

> **Agregado Abr 2026** — 40 tests en `tests/Feature/OrderManualTest.php`.

El admin puede crear pedidos desde el Tablero mediante el botón "Nuevo pedido". El flujo es similar al de la API pública pero con algunos matices.

### Campo `orders.source`

Migración: `database/migrations/2026_04_13_083032_add_source_to_orders_table.php`

```php
$table->string('source', 16)->default('api')->after('status');
```

Valores observados:

- `'api'` — pedido creado por el cliente desde el SPA (canal público)
- `'manual'` — pedido creado por el admin desde `/orders/create`
- `'pos'` — reservado para el módulo POS (ver [14-pos.md](./14-pos.md), que usa tablas separadas `pos_sales`; por ahora `orders.source` sólo distingue `api` vs `manual`)

### Form Request

`app/Http/Requests/StoreManualOrderRequest.php`:

| Campo | Reglas |
| --- | --- |
| `customer.name` | required, string |
| `customer.phone` | required, regex:/^\d{10}$/ |
| `delivery_type` | required, in:delivery,pickup,dine_in |
| `branch_id` | required, exists en sucursales del restaurante |
| `address_street`, `number`, `colony` | required_if:delivery_type,delivery |
| `items` | required, array, min:1, max:50 |
| `items.*.unit_price` \| `items.*.price_adjustment` | permitidos (override manual del admin) |
| `payment_method` | required, in:cash,terminal,transfer |

### Controller

```
POST /orders/create → OrderController@storeManual
```

En `app/Http/Controllers/OrderController.php` (método `storeManual`, línea ~198):

1. Autoriza (admin con acceso a la sucursal)
2. Valida con `StoreManualOrderRequest`
3. Invoca `OrderService::store($validated, $restaurant, source: 'manual', createdByUserId: $user->id)`
4. Captura excepciones operacionales (`orders_limit_reached`, `cannot_operate`, etc.) y las mapea a errores de validación
5. Dispara `OrderCreated` por broadcast

### Lookup de cliente

Dentro de `OrderService::store()`, cuando `source === 'manual'`:

- Busca un `Customer` por teléfono dentro del restaurante (se une por órdenes previas).
- Si existe, lo reutiliza; si no, crea uno nuevo con un token sintético `manual_<sha1(phone)>`.
- Esto evita crear customers duplicados cuando el mismo cliente regresa y el admin vuelve a tomar su pedido manualmente.

### Gate operacional

Los pedidos manuales **respetan el gate operacional** del módulo de billing. Si `Restaurant::canOperate() === false`, el botón "Nuevo pedido" se deshabilita y muestra el `block_message` en banner rojo (ver [17-billing.md](./17-billing.md)).

El gate **no bloquea por `orders_limit_reached`** — un admin puede seguir creando pedidos manuales aunque el cliente final esté bloqueado en la API pública. (Este comportamiento es distinto al POS, que tampoco se bloquea por limit.)

### Visualización

Los pedidos manuales aparecen en la columna "Recibidos" del Kanban igual que los del cliente. Hoy no hay un badge visual que los distinga — el campo `orders.source` está disponible en el payload para un indicador futuro.

---

_PideAqui — Módulo Pedidos v1.1 — Actualizado Marzo–Abril 2026 (edición post-creación + pedidos manuales)_
