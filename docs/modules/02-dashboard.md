# Módulo 02 — Dashboard del Administrador

> Pantalla de referencia: `ar_04_admin_dashboard`

---

## Descripción General

Pantalla de inicio del Panel del Administrador del Restaurante. Es lo primero que ve el admin tras hacer login. Muestra KPIs en tiempo real del día y del mes, incluyendo pedidos por sucursal, estatus de pedidos activos, progreso hacia el límite mensual y ganancia neta del período.

Es una pantalla de **solo lectura** — no permite modificar datos, solo visualizarlos. Funciona como punto de navegación central hacia los demás módulos.

---

## Pantalla

### `ar_04` — Dashboard Principal (`/dashboard`)

**Sección KPIs (4 tarjetas):**

| KPI | Descripción | Ícono |
|---|---|---|
| Pedidos de hoy | Total del día actual, con % de cambio vs. ayer | `receipt_long` |
| En preparación | Pedidos con estatus `preparing` en este momento | `skillet` |
| Pedidos mensuales | Contador del mes vs. límite configurado por SuperAdmin | `calendar_month` |
| Ganancia neta | Suma de (precio_venta - costo_producción) del período | `payments` |

**Sección de gráficas:**
- Barra de pedidos por sucursal (semana actual).
- Lista de pedidos recientes con estatus y hora.
- Accesos rápidos a módulos frecuentes.

**Header:**
- Nombre del restaurante y sucursal activa en el sidebar.
- Ícono de notificaciones con badge rojo cuando hay pedidos nuevos.
- Fecha y hora actual.

---

## Modelos Involucrados

| Modelo | Tabla | Qué se consulta |
|---|---|---|
| `Order` | `orders` | Pedidos del día, del mes, por sucursal, por estatus |
| `Product` | `products` | Precio de venta |
| `OrderItem` | `order_items` | Cantidad y precio unitario para calcular subtotales |
| `OrderItemModifier` | `order_item_modifiers` | Ajustes de precio de modificadores |
| `Branch` | `branches` | Agrupar pedidos por sucursal |
| `Restaurant` | `restaurants` | `max_monthly_orders` para la barra de progreso |

---

## Cálculos Clave

### Ganancia Neta

```
Por pedido:
  ganancia_neta = Σ (order_item.unit_price × order_item.quantity)
                + Σ order_item_modifier.price_adjustment
                - Σ (product.production_cost × order_item.quantity)

Total del período:
  ganancia_total = Σ ganancia_neta de todos los pedidos del período
```

> `production_cost` nunca se expone a la API pública del cliente. Es un campo solo del panel admin.

### Progreso de Pedidos Mensuales

```
porcentaje = (pedidos_del_mes / restaurant.max_monthly_orders) × 100
```

La barra de progreso cambia de color según el porcentaje:
- < 70% → morado/neutral
- 70–90% → amarillo (advertencia)
- > 90% → rojo (alerta)

### Pedidos por Sucursal

Query: pedidos de los últimos 7 días, agrupados por `branch_id`, solo del restaurante del tenant autenticado.

---

## Reglas de Negocio

- Solo muestra datos del **restaurante del admin autenticado** (multitenancy).
- El campo `production_cost` y la ganancia neta son **confidenciales**: nunca se exponen en la API pública.
- Si un restaurante tiene **una sola sucursal**, no se muestra el desglose por sucursal.
- Si el restaurante está cerca de su límite mensual (>90%), se muestra un badge de advertencia prominente.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[01-auth.md](./01-auth.md)** | El dashboard es el destino post-login. Requiere autenticación y contexto de tenant. |
| **[03-orders.md](./03-orders.md)** | El dashboard muestra pedidos recientes y permite navegar al Kanban de pedidos. |
| **[04-menu.md](./04-menu.md)** | El `production_cost` de los productos se usa para calcular la ganancia neta aquí. |
| **[05-branches.md](./05-branches.md)** | El desglose de pedidos por sucursal depende de las sucursales configuradas. |
| **[07-superadmin.md](./07-superadmin.md)** | El límite mensual (`max_monthly_orders`) es configurado por el SuperAdmin y se muestra aquí. |

---

## Implementación Backend

```
Routes:
  GET /dashboard → DashboardController@index

Controller retorna (Inertia):
  - today_orders_count
  - preparing_orders_count
  - monthly_orders_count
  - max_monthly_orders (de restaurants)
  - net_profit (del mes actual)
  - orders_by_branch (últimos 7 días)
  - recent_orders (últimos 10, con estatus)
```

**Servicio recomendado:** `StatisticsService` — encapsula todos los cálculos de KPIs y ganancia neta.

---

## Notas de Diseño (ar_04)

- Layout: Sidebar izquierdo fijo (260px) + contenido principal con padding `p-8`.
- Cuatro tarjetas KPI en grid `grid-cols-4` (desktop), colapsando a 2 y luego 1 en móvil.
- Cada tarjeta tiene: ícono coloreado, badge de tendencia, valor grande en negrita y etiqueta.
- Barra de progreso mensual con color dinámico según porcentaje.
- Fondo: `#FAFAFA`, tarjetas en blanco con `shadow-sm border border-gray-100`.
- Todas las cifras monetarias en pesos mexicanos (MXN), formato `$X,XXX`.

---

_PideAqui — Módulo Dashboard v1.0 — Febrero 2026_
