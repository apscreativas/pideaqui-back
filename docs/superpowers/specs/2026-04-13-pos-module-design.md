# Spec — Módulo POS (Punto de Venta)

**Fecha**: 2026-04-13
**Estado**: aprobado por usuario, pendiente de implementación
**Autores**: Sebas + Claude (sesión brainstorming)

---

## 1. Contexto

GuisoGo ya cuenta con un sistema de pedidos (`orders`) que recibe órdenes desde la SPA del cliente (API) y desde el panel admin (pedidos manuales con `source='manual'`). Estos pedidos están diseñados para representar entregas o recogidas con flujo de cocina, dirección, cliente, etc.

Falta un módulo para **ventas de mostrador** ("caja rápida") donde el restaurante atiende al cliente físicamente: el cajero arma el ticket, lo manda a cocina, cobra cuando está listo y entrega. No hay cliente registrado, no hay envío, no hay cupones, no hay descuentos, no hay límite de plan que aplique.

## 2. Objetivos

- Modelar las ventas POS como entidad **separada** de `orders` (decisión arquitectónica explícita del cliente).
- Permitir flujo: cajero arma carrito → envía a cocina → cocina marca lista → cajero cobra → final.
- Soportar **pagos mixtos** (efectivo + tarjeta + transferencia en una sola venta).
- Incluir **notas, modificadores inline y modificadores de catálogo** por item (mismo set que `orders`).
- Los productos se venden **al precio configurado, sin descuentos, sin cupones, sin promociones automáticas**.
- POS NO consume el límite de pedidos del plan.
- POS aparece en dashboard y reportes pero **siempre distinguible** de orders.
- Reusar `Product`, `Category`, `ModifierGroup/Option` (inline + catálogo), `PaymentMethod`, `Branch`, `User`, `Restaurant` sin modificación.

## 3. Fuera de alcance (no MVP)

- Turnos de caja (apertura/cierre con saldo). Se descartó por simplicidad.
- Rol nuevo `cashier`. Se reusa `operator` con sucursal asignada.
- Edición de venta POS post-creación (no hay equivalente a `OrderEditService`).
- Refunds parciales o devoluciones (la cancelación es total).
- Auto-print silencioso (siempre se pregunta).
- Reportes nuevos como "Cancelaciones POS" — el reporte `/pos/sales` filtrable por status cubre el caso.
- Exposición vía API pública del POS (es interno del panel).
- Tickets impresos físicamente vía driver (se usa `window.print()` del navegador como en `OrderTicket`).

## 4. Arquitectura

### 4.1 Modelo de datos

Cuatro tablas nuevas, todas en el dominio del tenant (`BelongsToTenant` donde aplique). Cero modificación a tablas existentes.

```
pos_sales
├── id                       bigint PK
├── restaurant_id            bigint FK restaurants
├── branch_id                bigint FK branches
├── cashier_user_id          bigint FK users
├── ticket_number            string  (formato POS-NNNN secuencial por restaurante)
├── status                   enum    (preparing, ready, paid, cancelled)
├── subtotal                 decimal(10,2)
├── total                    decimal(10,2)  (= subtotal; sin descuento ni envío)
├── notes                    text NULL  (nota general del ticket)
├── cancellation_reason      text NULL
├── cancelled_at             timestamp NULL
├── prepared_at              timestamp NULL  (cuando cocina marcó ready)
├── paid_at                  timestamp NULL  (cuando se cerró el cobro)
└── created_at, updated_at

INDEX (restaurant_id, status, created_at)
INDEX (restaurant_id, ticket_number) UNIQUE

pos_sale_items
├── id                       bigint PK
├── pos_sale_id              bigint FK pos_sales (cascade delete)
├── product_id               bigint FK products  (siempre product, NUNCA promotion_id)
├── product_name             string  (snapshot)
├── quantity                 unsignedInteger
├── unit_price               decimal(10,2)  (snapshot)
├── production_cost          decimal(10,2)  (snapshot — para profit)
├── notes                    text NULL  (nota por item)
└── created_at, updated_at

pos_sale_item_modifiers
├── id                            bigint PK
├── pos_sale_item_id              bigint FK pos_sale_items (cascade)
├── modifier_option_id            bigint FK modifier_options NULL          (inline)
├── modifier_option_template_id   bigint FK modifier_option_templates NULL (catálogo)
├── modifier_option_name          string  (snapshot)
├── price_adjustment              decimal(10,2)  (snapshot)
├── production_cost               decimal(10,2)  (snapshot)
└── created_at, updated_at

CHECK (modifier_option_id IS NOT NULL OR modifier_option_template_id IS NOT NULL)
CHECK NOT (modifier_option_id IS NOT NULL AND modifier_option_template_id IS NOT NULL)

pos_payments
├── id                       bigint PK
├── pos_sale_id              bigint FK pos_sales (cascade)
├── payment_method_type      enum    (cash, terminal, transfer)
├── amount                   decimal(10,2)  (lo que cubre del total)
├── cash_received            decimal(10,2) NULL  (solo cash; lo entregado por cliente)
├── change_given             decimal(10,2) NULL  (cash_received − amount, solo cash)
├── registered_by_user_id    bigint FK users
└── created_at

INDEX (pos_sale_id)
```

### 4.2 Lo que NO incluye (por diseño)

- Sin `customer_id`, `customer_name`, `customer_phone`.
- Sin `coupon_id`, `coupon_code`, `discount_amount`.
- Sin `promotion_id` en items.
- Sin `delivery_*`, `address_*`, `latitude/longitude`, `scheduled_at`, `distance_km`.
- Sin `requires_invoice` (si más adelante se necesita facturación POS, se agrega como flag aparte).
- Sin `edit_count` / `edited_at` — POS no es editable post-creación.

### 4.3 Reuso intacto

- `Product` + `Product::getAllModifierGroups()` (mergea inline + catálogo) — reuso total para cargar el catálogo en `/pos`.
- `Category` — sidebar de categorías.
- `ModifierGroup`, `ModifierOption`, `ModifierGroupTemplate`, `ModifierOptionTemplate` — sin cambios.
- `PaymentMethod` — se valida que el `type` esté activo en el restaurante al registrar cada split.
- `Branch`, `User`, `Restaurant` — sin cambios.
- `print-ticket.css` — reutilizado por `PosTicket.vue`.

## 5. State machine

```
   POST /pos/sales
         │
         ▼
   ┌──────────┐     cancela     ┌───────────┐
   │preparing │ ───────────────▶│ cancelled │ (terminal)
   └─────┬────┘                  └───────────┘
         │ cocina marca lista          ▲
         ▼                             │
   ┌──────────┐     cancela            │
   │  ready   │ ──────────────────────-┘
   └─────┬────┘
         │ cajero cobra (suma de splits >= total)
         ▼
   ┌──────────┐
   │   paid   │ (terminal)
   └──────────┘
```

### 5.1 Transiciones y endpoints

| De → A | Disparador | Endpoint |
|---|---|---|
| (nada) → `preparing` | Cajero envía a cocina | `POST /pos/sales` |
| `preparing` → `ready` | Cocina marca lista | `PUT /pos/sales/{sale}/ready` |
| `ready` → `paid` | Cajero cierra cobro | `PUT /pos/sales/{sale}/pay` |
| `preparing` → `cancelled` | Cualquier user con permiso | `PUT /pos/sales/{sale}/cancel` |
| `ready` → `cancelled` | Cualquier user con permiso | `PUT /pos/sales/{sale}/cancel` |

### 5.2 Reglas duras

- `paid` y `cancelled` son terminales. No hay revert ni desbloqueo.
- No se puede cobrar (`pay`) si `SUM(pos_payments.amount) < pos_sale.total`.
- Cancelar no libera ningún recurso (no hay límite ni cupón).
- Pagos son inmutables tras `paid` (no se pueden borrar splits).

## 6. Reglas de pricing y aislamiento

### 6.1 Pricing

- `unit_price` por item se carga en backend desde `Product::price`. El cliente no puede pasar un precio distinto.
- `price_adjustment` por modifier idem (cargado de `ModifierOption::price_adjustment` o `ModifierOptionTemplate::price_adjustment`).
- Anti-tampering: si el cliente envía un `unit_price` que no coincide con DB ±0.01, se rechaza con `ValidationException`.
- `total = subtotal = SUM(item: (unit_price + SUM(modifiers.price_adjustment)) × quantity)`.
- Sin descuentos. Sin cupones. Sin promociones automáticas. Sin envío.

### 6.2 Aislamiento de servicios existentes

POS NO ejecuta:

- `LimitService::isOrderLimitReached()` — POS no consume cupo del plan por construcción (tabla distinta).
- `DeliveryService::calculate()` — POS no tiene dirección.
- `OrderEditService::update()` — POS no es editable.
- `OrderService::store()` — POS tiene su propio `PosSaleService`.
- `Coupon::isValidForOrder()` — POS no acepta cupones.

POS NO dispara eventos:

- `OrderCreated`, `OrderUpdated`, `OrderCancelled`, `OrderStatusChanged` — usa los suyos (`PosSaleCreated`, `PosSaleStatusChanged`, `PosSaleCancelled`).

POS NO toca tablas:

- `orders`, `order_items`, `order_item_modifiers`, `customers`, `coupons`, `coupon_uses`, `order_events`, `order_audits`.

## 7. Pagos mixtos

### 7.1 Endpoints

- `POST /pos/sales/{sale}/payments` — registra un split. Body: `{ payment_method_type, amount, cash_received? }`.
- `DELETE /pos/sales/{sale}/payments/{payment}` — elimina un split (solo si el ticket está `ready`, no `paid`).
- `PUT /pos/sales/{sale}/pay` — cierra el cobro (transiciona `ready → paid`). Valida que la suma cubra el total.

### 7.2 Reglas

- Sin límite arbitrario de splits. En la práctica 1–3, técnicamente unlimited.
- Cada split tiene `registered_by_user_id` para trazabilidad.
- Cash con cambio: cajero ingresa `cash_received`. Sistema calcula `change_given = cash_received − amount`.
- El `amount` registrado en un split nunca excede lo que falta por cubrir del total. Si el cliente da $200 cash sobre un saldo pendiente de $150, `amount = 150`, `cash_received = 200`, `change_given = 50`. **Validación server-side**: `POST /pos/sales/{sale}/payments` rechaza con 422 si `amount > (pos_sale.total − SUM(splits_existentes.amount))`. La UI también lo previene como UX, pero el backend es la verdad.
- Solo cash puede tener `cash_received` y `change_given`. Para terminal/transfer, ambos son NULL.
- Tras `paid`, los pagos son inmutables.

### 7.3 Validación al cobrar

```
SUM(pos_payments WHERE pos_sale_id = X).amount  >=  pos_sale.total
```

Si la suma es menor → 422 con mensaje "Pago incompleto". Si es mayor (no debería pasar por la regla anterior pero defensiva) → registrar warning y aceptar (cualquier sobrante = error operativo del cajero, no del sistema).

## 8. Impresión de tickets

### 8.1 Componente

`PosTicket.vue` — nuevo, en `resources/js/Components/Pos/`. Reusa `print-ticket.css` existente. Mismo DOM hidden + media print pattern que `OrderTicket.vue`.

### 8.2 Flujo

- Tras `POST /pos/sales` exitoso → modal pregunta "¿Imprimir ticket de cocina?" con [Sí, imprimir] [No, gracias]. Si Sí → `window.print()` con `PosTicket` montado. Default focus en "Sí" para Enter rápido.
- Tras `PUT /pos/sales/{sale}/pay` exitoso → mismo modal "¿Imprimir ticket de venta?".
- Botón **Reimprimir** disponible siempre en `/pos/sales/{sale}` para reimpresiones manuales.
- No hay auto-print silencioso. No hay configuración por sucursal (MVP).

### 8.3 Contenido del ticket

Mismo template para "comanda cocina" y "recibo cliente". Estructura:

```
═════════════════════════
   {RESTAURANT_NAME}
   {BRANCH_NAME}
═════════════════════════
Ticket {ticket_number}
{created_at formato es-MX}
Cajero: {cashier.name}
─────────────────────────
PRODUCTOS
─────────────────────────
{quantity}x {product_name}   {item_total}
   ↳ {modifier_name} (+price?)
   📝 {item_notes}
─────────────────────────
TOTAL                    ${total}
─────────────────────────
PAGOS  (solo si paid)
{payment.type}: ${amount}{ (rec ${cash_received})?}
Cambio: ${change_given total}
═════════════════════════
{notes general si hay}
```

## 9. Kanban POS

### 9.1 Ruta `/pos/board`

3 columnas activas + tab para cancelados:

| Columna | Status DB |
|---|---|
| ⏳ En preparación | `preparing` |
| ✓ Listas | `ready` |
| 💵 Cobradas hoy | `paid` (filtro fecha = hoy) |

Tab/filtro **Cancelados** muestra `cancelled` separado.

### 9.2 Interacción

- Drag & drop solo entre columnas válidas (preparing→ready directo; ready→paid abre PaymentModal). Si el cajero cierra/cancela el PaymentModal sin completar el cobro, la card permanece en `ready` (no se mueve a paid). El estado del backend es la fuente de verdad — el drop es solo UI hasta que el `PUT /pay` retorne 2xx.
- Click en card → `/pos/sales/{sale}` (detalle).
- Filtros: sucursal (limitada por `allowedBranchIds`), cajero, rango de fechas (default hoy).
- Real-time vía Echo en canal `restaurant.{id}.pos` con eventos `PosSaleCreated`, `PosSaleStatusChanged`, `PosSaleCancelled`. Mismo patrón que `Orders/Index.vue` con `restaurant.{id}`.

### 9.3 Card

Muestra: `ticket_number`, hora creación, items count, total, cajero, badge de pagos pendientes vs cobrados (en `ready`).

## 10. Dashboard y reportes

### 10.1 StatisticsService extendido

Firma actual:

```php
public function getDashboardData(
    Restaurant $r, Carbon $from, Carbon $to,
    ?array $branchIds = null,
    ?array $statuses = null,
    ?float $minAmount = null,
    ?float $maxAmount = null,
): array
```

Se añade `?array $channels = null` al final (default `null` = ambos canales — sin romper llamadas existentes):

```php
public function getDashboardData(
    Restaurant $r, Carbon $from, Carbon $to,
    ?array $branchIds = null,
    ?array $statuses = null,
    ?float $minAmount = null,
    ?float $maxAmount = null,
    ?array $channels = null,  // ['orders','pos'] o uno solo
): array
```

### 10.2 Métricas

| Métrica | Cálculo |
|---|---|
| `orders_count` | Conteo `orders` (POS no cuenta) |
| `pos_sales_count` (NUEVO) | Conteo `pos_sales WHERE status='paid'` |
| `revenue` | `SUM(orders.total WHERE status='delivered') + SUM(pos_sales.total WHERE status='paid')` |
| `revenue_by_channel` (NUEVO) | `{orders: float, pos: float}` |
| `revenue_by_payment` | UNION orders.payment_method + cada `pos_payments.payment_method_type` (POS suma cada split por separado). **Asimetría documentada**: una venta `paid` con pagos mixtos contribuye a 2+ buckets de método de pago aunque cuente como 1 sola venta. La gráfica de "ingresos por método" es por monto, no por conteo de tickets. |
| `net_profit` | UNION cálculo en orders + pos_sales (ambos tienen `production_cost` snapshot) |
| `monthly_orders_count` (límite plan) | Solo orders. POS NUNCA cuenta. |
| `recent_activity` | UNION últimos N orders + pos_sales con flag `channel` |

### 10.3 Dashboard UI

- KPI nuevo: **Ventas POS hoy** (counter + total $).
- Gráfica de ingresos: barras apiladas con segmento "Online" (orders) + "Mostrador" (pos).
- Tabla "actividad reciente" con badge `Online / POS`.
- Indicador de límite del plan sigue diciendo "X / Y pedidos" + leyenda pequeña: "(las ventas POS no cuentan)".

### 10.4 Reporte dedicado `/pos/sales`

- Tabla filtrable: cajero, sucursal, fecha, status (preparing/ready/paid/cancelled), método de pago.
- Card sticky lateral con totales del filtro: # tickets, revenue, profit, breakdown por método de pago.
- Export CSV.
- Click en fila → `/pos/sales/{sale}` (detalle + reimprimir + auditoría básica de pagos).

## 11. UI / rutas

### 11.1 Rutas (todas dentro de `auth + tenant`)

| Verbo | Ruta | Nombre | Quién |
|---|---|---|---|
| GET | `/pos` | `pos.index` | admin + operator (su sucursal) |
| GET | `/pos/board` | `pos.board` | admin + operator |
| GET | `/pos/sales` | `pos.sales.index` | admin + operator |
| GET | `/pos/sales/{sale}` | `pos.sales.show` | admin + operator (su sucursal) |
| POST | `/pos/sales` | `pos.sales.store` | admin + operator (`throttle:60,1`) |
| PUT | `/pos/sales/{sale}/ready` | `pos.sales.ready` | admin + operator |
| PUT | `/pos/sales/{sale}/cancel` | `pos.sales.cancel` | admin + operator |
| PUT | `/pos/sales/{sale}/pay` | `pos.sales.pay` | admin + operator |
| POST | `/pos/sales/{sale}/payments` | `pos.sales.payments.store` | admin + operator |
| DELETE | `/pos/sales/{sale}/payments/{payment}` | `pos.sales.payments.destroy` | admin + operator |

`PosSalePolicy` espeja `OrderPolicy` (sin método `edit`):

- `viewAny`, `view`, `update`, `cancel` filtran por `restaurant_id` y `allowedBranchIds()`.

### 11.2 Estructura Vue

```
resources/js/Pages/Pos/
├── Index.vue          ← /pos — caja rápida (3 columnas Square)
├── Board.vue          ← /pos/board — kanban POS
├── Sales/
│   ├── Index.vue      ← /pos/sales — reporte filtrable + CSV
│   └── Show.vue       ← /pos/sales/{id} — detalle + reimprimir

resources/js/Components/Pos/
├── ProductCard.vue    ← card grande clickeable
├── CategorySidebar.vue← sidebar izquierdo
├── CartPanel.vue      ← carrito derecho
├── ProductModal.vue   ← modal modifiers/notas/cantidad
├── PaymentModal.vue   ← modal de cobro con splits
├── PrintConfirmModal.vue ← "¿Imprimir ticket?"
├── PosTicket.vue      ← ticket imprimible
└── SaleStatusBadge.vue← badge coloreado por estado
```

### 11.3 Layout `/pos` (3 columnas Square)

```
┌──────────┬────────────────────────┬────────────────┐
│Categorías│  Productos (grid)      │ Ticket actual  │
│ ▸ Burgers│  ┌────┐ ┌────┐ ┌────┐ │ Cantidad: 3    │
│   Pizzas │  │Img │ │Img │ │Img │ │                │
│   Bebidas│  │Name│ │Name│ │Name│ │ 2x Clásica $190│
│   Postres│  │$95 │ │$120│ │$110│ │ 1x BBQ    $120 │
│   ...    │  └────┘ └────┘ └────┘ │ 1x Coca    $25 │
│  ─────   │  [search bar]         │ ────────────── │
│  Sucursal│                        │ Total   $335   │
│  Cajero  │                        │ [Limpiar]      │
│          │                        │ [Enviar coc.]  │
└──────────┴────────────────────────┴────────────────┘
```

- Click producto sin modifiers → suma 1 al carrito directo.
- Click producto con modifiers → abre `ProductModal`.
- Click item del carrito → cambiar cantidad / quitar.
- "Enviar a cocina" deshabilitado si carrito vacío.
- Tras crear → `PrintConfirmModal` → carrito se vacía.

### 11.4 Sidebar nav

```
Operación
  Tablero (orders.index)
  Mapa (map.index)
  Cancelaciones
─────────────
POS                          ← NUEVO
  Caja (pos.index)
  Tablero POS (pos.board)
  Ventas POS (pos.sales.index)
─────────────
Gestión
  Menú, Promociones, ...
```

## 12. Migraciones e inventario de archivos nuevos

### 12.1 Migraciones (5 archivos en orden)

1. `create_pos_sales_table`
2. `create_pos_sale_items_table`
3. `create_pos_sale_item_modifiers_table`
4. `create_pos_payments_table`
5. (Index) Asegurar índices declarados (`(restaurant_id, status, created_at)`, unique `(restaurant_id, ticket_number)`, FK `pos_sale_id`).

Sin tocar tablas existentes.

### 12.2 Modelos (4)

- `PosSale` (BelongsToTenant, casts decimal/datetime, `status` enum)
- `PosSaleItem`
- `PosSaleItemModifier`
- `PosPayment`

### 12.3 Factories (4)

Una por modelo. `PosSaleFactory` con estados `preparing()`, `ready()`, `paid()`, `cancelled()`.

### 12.4 Servicios (1 nuevo + 1 extendido)

- `PosSaleService` — `store($data, $restaurant, $cashierId)`, `markReady($sale, $user)`, `cancel($sale, $reason, $user)`, `registerPayment($sale, $data, $user)`, `removePayment($payment, $user)`, `closePay($sale, $user)`.
- `StatisticsService` — extendida con parámetro opcional `?array $channels = null` y métodos privados nuevos: `posSalesCount()`, `posRevenue()`, `revenueByChannel()`. Llamadas existentes sin cambios.

### 12.5 Controllers (1)

- `PosController` — métodos `index`, `board`, `salesIndex`, `salesShow`, `store`, `markReady`, `cancel`, `closePay`, `storePayment`, `destroyPayment`.

### 12.6 Form Requests (3)

- `StorePosSaleRequest` — items obligatorios (`min:1, max:50`); por item: `quantity` (`min:1, max:100`), `unit_price` (`min:0, max:99999.99`); modifiers (`min:1`), `modifier_option_id` y `modifier_option_template_id` con `distinct` cada uno; sin customer/coupon/delivery. Espeja la hardening de `StoreOrderRequest` para no regresar protecciones.
- `RegisterPosPaymentRequest` — `payment_method_type` (in: cash, terminal, transfer), `amount` (numeric, min:0.01, max:99999.99), `cash_received` (numeric, `prohibited_unless:payment_method_type,cash`, `gte:amount` cuando aplica).
- `CancelPosSaleRequest` — `cancellation_reason` (string max:255).

### 12.7 Policy

- `PosSalePolicy` — métodos: `viewAny` (autoriza `index`, `board`, `salesIndex`), `view` (autoriza `salesShow`), `update` (autoriza `markReady`, `closePay`, `storePayment`, `destroyPayment`), `cancel` (autoriza `cancel`). Patrón análogo a `OrderPolicy`, sin `edit` (POS no es editable post-creación). Cada método valida `restaurant_id` y `allowedBranchIds()`.

### 12.8 Eventos (3)

- `PosSaleCreated` (`ShouldBroadcastNow`) — canal `restaurant.{id}.pos`.
- `PosSaleStatusChanged` (`ShouldBroadcastNow`) — mismo canal.
- `PosSaleCancelled` (`ShouldBroadcastNow`) — mismo canal.

`routes/channels.php` — autorización para `restaurant.{id}.pos` (mismo restaurant_id que el user).

### 12.9 Helpers/utilidades

- `PosTicketNumberService` — genera `POS-NNNN` secuencial por restaurante con `lockForUpdate()` para evitar colisiones.

## 13. Checklist de no-regresión

- ✅ `orders` table intacta.
- ✅ `LimitService` sin cambios. `orderCountInPeriod` sigue contando solo `orders`.
- ✅ `OrderService::store` sin cambios.
- ✅ `OrderEditService` sin cambios.
- ✅ `DeliveryService` sin cambios.
- ✅ `Statistics::getDashboardData` agrega parámetro opcional con default `null` → llamadas existentes (DashboardController) siguen funcionando.
- ✅ `OrderTicket.vue` sin cambios. `PosTicket.vue` es nuevo, comparte `print-ticket.css`.
- ✅ `Cancellations` module sin tocar (sigue siendo solo orders).
- ✅ `Map` module sin tocar (POS no tiene ubicación).
- ✅ Stripe/Cashier sin tocar.
- ✅ Canales de broadcast: `restaurant.{id}` (orders) intacto; `restaurant.{id}.pos` es nuevo.
- ✅ `Restaurant`, `Branch`, `User`, `PaymentMethod`, `Product`, `Category`, `ModifierGroup`, `ModifierOption`, `ModifierGroupTemplate`, `ModifierOptionTemplate` sin cambios estructurales.

## 14. Riesgos y mitigaciones

| # | Riesgo | Mitigación |
|---|---|---|
| 1 | Concurrencia en `ticket_number` | `PosTicketNumberService` con `lockForUpdate()`. |
| 2 | Drag & drop kanban POS — cambio de status simultáneo | Revalidar estado dentro de transacción con `lockForUpdate`; idempotente si ya cambió. |
| 3 | `window.print()` requiere acción del usuario en algunos browsers | Modal de confirmación es la acción del usuario que dispara `print()`. |
| 4 | Cajero olvida cobrar tickets en `ready` que se acumulan | El reporte `/pos/sales?status=ready` los expone. Posible alerta en dashboard si hay ready > N horas. |
| 5 | Producto cambia de precio entre que se carga el catálogo y se envía a cocina | Re-validación de `unit_price` en `PosSaleService::store` con `lockForUpdate` del producto. |

## 15. Tests (cobertura mínima MVP)

`tests/Feature/PosSaleTest.php`:

| Test | Asserts |
|---|---|
| Cajero crea venta válida | status=preparing, ticket_number único, items snapshot, `LimitService::orderCountInPeriod` SIN cambios |
| Crear con producto inactivo | rechaza con `ValidationException` |
| Crear con producto de otro restaurante | rechaza |
| Crear con precio manipulado | rechaza con anti-tampering |
| Crear con modifier inline + catálogo en mismo item | OK, ambos snapshots persistidos |
| Crear con required modifier missing | rechaza |
| Operator crea en sucursal asignada | OK |
| Operator crea en sucursal NO asignada | rechaza con `ValidationException` |
| Cobrar antes de `ready` | rechaza |
| Cobrar con pagos insuficientes | rechaza |
| Cobrar con pagos completos | status=paid, `paid_at` set |
| Pago mixto cash + terminal | suma cubre total, `change_given` calculado |
| Cancelar `preparing` | OK, `cancelled_at` set |
| Cancelar `ready` | OK |
| Cancelar `paid` | rechaza (estado terminal) |
| `PosSaleCreated` broadcast en canal `.pos` | NO contamina canal de orders |
| POS no consume límite del plan | crear N ventas POS, `LimitService::summary` invariante |
| Statistics con `channels=['pos']` | revenue solo de POS |
| Statistics con `channels=['orders']` | revenue solo de orders, sin POS |
| Statistics con `channels=null` (default) | revenue combinado |
| Reimprimir ticket | accesible vía GET, no muta datos |
| Tenant isolation: ver venta de otro restaurante | 404 |
| Eliminar split en venta `ready` | OK, sale recalcula pendiente |
| Eliminar split en venta `paid` | rechaza (estado terminal) |
| Registrar split que excede saldo pendiente | rechaza con 422 |

## 16. Estimación de iteración 2 (post-MVP)

Decisiones explícitamente diferidas:

- Refunds parciales / devoluciones.
- Apertura/cierre de turno con saldo (`pos_shifts`).
- Configuración "auto-print" por sucursal o por usuario.
- Facturación POS (CFDI / requires_invoice).
- Reporte combinado de cancelaciones (orders + pos).
- Edición de venta POS post-creación (similar a `OrderEditService`).
- Rol `cashier` separado de `operator`.

## 17. Aprobaciones

- **Diseño aprobado por el usuario** en sesión brainstorming del 2026-04-13.
- **Decisiones clave confirmadas**:
  - Tabla separada (no reuso de `orders`).
  - State machine 4 estados: `preparing → ready → paid` + `cancelled`.
  - Kanban POS separado (`/pos/board`).
  - Sin turnos.
  - Reuso de rol `operator`.
  - Cobrar al final (no antes de `ready`).
  - Impresión opcional (modal de confirmación, no auto).
  - Modifiers de catálogo soportados desde MVP.
  - Layout 3 columnas estilo Square en `/pos`.
