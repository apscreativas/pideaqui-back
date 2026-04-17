# 11. Cancelaciones

Modulo de analisis de pedidos cancelados con KPIs, desglose por razon/sucursal/dia, y tabla de pedidos cancelados.

---

## Pantalla

No existe mockup dedicado (feature post-MVP). Se accede desde el sidebar del admin: **"Cancelaciones"** (icon: `cancel`).

---

## Ruta

| Metodo | URI | Controlador | Nombre |
|--------|-----|------------|--------|
| `GET` | `/cancellations` | `CancellationController@index` | `cancellations.index` |

---

## Componentes

### Cancellations/Index.vue

**Props recibidas:** `cancelled_count`, `total_orders_count`, `cancellation_rate`, `top_reason`, `reasons_breakdown`, `by_branch`, `by_day`, `cancelled_orders` (paginator), `branches`, `filters`, `by_channel`

**Funcionalidades:**
- **Filtros:** Presets de fecha (hoy, ayer, 7 dias, mes) + rango personalizado + filtro por sucursal. Cambiar filtros resetea a página 1.
- **KPIs:** Pedidos cancelados, tasa de cancelacion (%), motivo principal
- **Graficos:**
  - Desglose por razon (barras horizontales)
  - Desglose por sucursal (barras horizontales) — ahora respeta el filtro `branch_id`
  - Cancelaciones por dia (serie temporal)
- **Tabla paginada:** 25 cancelaciones por página con paginador Anterior/Siguiente. Columnas: referencia, canal (POS/Online), fecha cancelación, cliente/cajero, sucursal, total, motivo.

---

## Servicio

### CancellationService

- `getData(Restaurant, Carbon $from, Carbon $to, ?int $branchId)` → array (solo agregados)
- `list(Restaurant, Carbon $from, Carbon $to, ?int $branchId, int $page = 1, int $perPage = 25)` → `LengthAwarePaginator`
- KPIs por tabla en 1 query agregada con `CASE WHEN`/`COUNT FILTER` (portable pgsql/sqlite)
- `byBranch` consolidado en 2 queries (orders + pos) + merge, sin N+1
- Lista unificada: ranked manifest (id + channel + cancelled_at) con tiebreaker estable `cancelled_at DESC, channel, id DESC`, hidratación de relaciones solo para la página actual

---

## Reglas de Negocio

- Solo se muestran pedidos con status `cancelled`
- Los filtros de fecha y sucursal se aplican a todas las metricas (incluido `by_branch`)
- La tasa de cancelacion = `cancelled_count / total_orders_count * 100`
- El dataset combina orders + pos_sales canceladas. Orden estable: `cancelled_at DESC, channel, id DESC`.
- Índices: `orders(restaurant_id,cancelled_at)`, `pos_sales(restaurant_id,cancelled_at)` usados para el rango y orden.

---

## Modulos Relacionados

- [03 — Pedidos](./03-orders.md) — El status `cancelled` se asigna desde el detalle del pedido
- [02 — Dashboard](./02-dashboard.md) — KPIs complementarios
