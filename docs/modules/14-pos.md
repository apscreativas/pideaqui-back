# Módulo 14 — POS · Caja

> Spec de origen: [`docs/superpowers/specs/2026-04-13-pos-module-design.md`](../superpowers/specs/2026-04-13-pos-module-design.md)
> Sin mockup oficial. Layout en producción: 3 columnas estilo Square.

---

## Descripción general

Sistema de punto de venta para **mostrador físico**. Vive en `/pos`, `/pos/board` y `/pos/sales`. Está completamente desacoplado del módulo de pedidos (`orders`):

- Tablas separadas (`pos_sales`, `pos_sale_items`, `pos_sale_item_modifiers`, `pos_payments`).
- No usa `Customer`, `Coupon`, `Promotion`, `DeliveryService`, `LimitService`.
- No consume el cupo de pedidos del plan.
- Eventos broadcast en canal aislado `restaurant.{id}.pos` (no contamina canal `.{id}` de orders).

---

## State machine

```
preparing → ready → paid (terminal)
    ↘         ↘
       cancelled (terminal, en cualquier momento antes de paid)
```

| Transición | Endpoint | Quién |
|---|---|---|
| (creación) → `preparing` | `POST /pos/sales` | Cajero |
| `preparing` → `ready` | `PUT /pos/sales/{sale}/ready` | Cocina |
| `ready` → `paid` | `PUT /pos/sales/{sale}/pay` (requiere `SUM(payments.amount) >= total`) | Cajero |
| `preparing\|ready` → `cancelled` | `PUT /pos/sales/{sale}/cancel` | Cualquier user con permiso |

---

## Pricing y aislamiento

- Productos: precio fijo del catálogo. **Cero** descuentos, cupones, promociones automáticas o ediciones libres.
- Modifiers inline + catálogo, mismo set que orders (reuso `Product::getAllModifierGroups()`).
- Anti-tampering: backend revalida `unit_price` y `price_adjustment` ±0.01.
- `total = subtotal` (sin envío, sin descuento).
- POS NO ejecuta `LimitService::isOrderLimitReached`. Las ventas POS NO suman a `orderCountInPeriod`.

---

## Pagos mixtos

- Tabla `pos_payments` permite N splits por venta.
- Tipos: `cash`, `terminal`, `transfer` (validado contra `payment_methods` activos del restaurante).
- Cash con cambio: `cash_received` + `change_given = cash_received − amount`.
- Validación server-side: `amount` no excede saldo pendiente.
- Splits eliminables en `ready`, inmutables en `paid`.
- `closePay()` rechaza si `SUM(amount) < total`.

---

## Impresión

- Mismo template (`PosTicket.vue`) para comanda de cocina y recibo del cliente.
- Modal de confirmación tras `POST /pos/sales` y tras `PUT /pos/sales/{sale}/pay`. Default focus en "Sí, imprimir".
- Botón "Reimprimir" disponible siempre en `/pos/sales/{sale}`.
- CSS reutilizado: `resources/css/print-ticket.css`.

---

## UI / Rutas

| Verbo | Ruta | Vue Page | Quién |
|---|---|---|---|
| GET | `/pos` | `Pos/Index.vue` (3 columnas Square) | admin + operator |
| GET | `/pos/board` | `Pos/Board.vue` (kanban POS) | admin + operator |
| GET | `/pos/sales` | `Pos/Sales/Index.vue` (reporte + CSV) | admin + operator |
| GET | `/pos/sales/{sale}` | `Pos/Sales/Show.vue` (detalle + cobro + reimprimir) | admin + operator |
| POST | `/pos/sales` | `pos.sales.store` (`throttle:60,1`) | admin + operator |
| PUT | `/pos/sales/{sale}/ready` | `pos.sales.ready` | admin + operator |
| PUT | `/pos/sales/{sale}/cancel` | `pos.sales.cancel` | admin + operator |
| PUT | `/pos/sales/{sale}/pay` | `pos.sales.pay` | admin + operator |
| POST | `/pos/sales/{sale}/payments` | `pos.sales.payments.store` | admin + operator |
| DELETE | `/pos/sales/{sale}/payments/{payment}` | `pos.sales.payments.destroy` | admin + operator |

---

## Servicios

- **`PosSaleService`** — `store`, `markReady`, `cancel`, `registerPayment`, `removePayment`, `closePay`. Toda la lógica de validación, anti-tampering y transiciones de estado.
- **`PosTicketNumberService`** — Genera `POS-NNNN` secuencial por restaurante con `lockForUpdate()`.
- **`StatisticsService` (extendido)** — Métricas nuevas: `pos_sales_count`, `pos_revenue`, `revenue_by_channel: {orders, pos}`. Firma extendida con parámetro opcional `?array $channels = null` sin romper llamadas existentes.

---

## Eventos broadcast

| Evento | Canal | Trigger |
|---|---|---|
| `PosSaleCreated` | `restaurant.{id}.pos` | Crear venta |
| `PosSaleStatusChanged` | `restaurant.{id}.pos` | `markReady`, `closePay` |
| `PosSaleCancelled` | `restaurant.{id}.pos` | `cancel` |

Los eventos de orders (`OrderCreated`, etc.) siguen en `restaurant.{id}` — NO se mezclan.

---

## Authorization

`PosSalePolicy` (mismo patrón que `OrderPolicy`, sin `edit`):

- `viewAny` — autoriza index, board, sales index.
- `view` — autoriza sales show, scoped a restaurant + branch.
- `update` — autoriza markReady, closePay, payment store/destroy.
- `cancel` — autoriza cancel.

Operadores solo pueden vender en sus sucursales asignadas (validado en `PosSaleService::store`).

---

## Tests

Cobertura: `tests/Feature/PosSaleTest.php`:

- Auth, render del form
- Crear venta válida + ticket_number incremental
- Anti-tampering, modifier inline snapshot
- Operator branch authorization (assigned + unassigned)
- State machine: markReady, pay rejected before ready, pay insufficient, pay full
- Mixed payments cash + terminal con cambio
- Payment exceeding pending rejected
- Remove payment on ready/paid
- Cancel preparing/ready/paid
- POS no consume límite del plan
- Statistics revenue_by_channel separa orders y pos
- Tenant isolation
- **Pagination & scalability:** cursor paginator devuelve 50 por página, orden estable con timestamps idénticos (tiebreaker id DESC), KPIs cubren todo el rango (no afectados por filtro `status`), rango de fechas aplica a KPIs, filtro `payment_method` usa `whereExists`, aislamiento por tenant con timestamps iguales, `production_cost` no se expone en la lista.

---

## Historial POS — Escalabilidad

- **Cursor pagination** (`cursorPaginate(50)`) sobre `created_at DESC, id DESC`. Estable ante inserciones concurrentes.
- **KPIs**: 1 query agregada con `CASE WHEN` (`tickets`, `revenue`, `open_count`, `cancelled_count`). **No** se filtra por `status` para que las 4 tarjetas muestren siempre el breakdown completo; sí respeta `date range`, `branch_id`, `payment_method` y `allowedBranches`.
- **Rango de fechas**: `created_at >= from` y `created_at < to+1day` (no `whereDate`), aprovechando el índice `(restaurant_id, status, created_at)`.
- **Filtro `payment_method`**: `whereExists` (más rápido que `whereHas`), con índice compuesto `pos_payments(pos_sale_id, payment_method_type)`.
- **Eager loading selectivo**: `items:id,pos_sale_id,quantity` y `payments:id,pos_sale_id,amount,payment_method_type`. `production_cost` nunca se envía.
- **Frontend**: broadcast (`PosSaleCreated`, `PosSaleStatusChanged`, `PosSaleCancelled`) muta el array local en lugar de disparar `router.reload` — evita ráfagas cuando varios cajeros trabajan en paralelo. Botón "Cargar más" concatena la siguiente página vía Inertia partial reload. Debounce 300ms en filtros.
