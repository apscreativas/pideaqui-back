# SuperAdmin Dashboard — Alertas accionables (Paquete A)

**Goal:** Enriquecer la tab "Alertas" del Dashboard SuperAdmin con 4 cards que surface signals operacionales críticos, y permitir click-through a la lista filtrada correspondiente.

**Decisiones confirmadas:**
- Threshold orders limit: **80%**
- Umbral grace: **≤ 3 días**
- Ubicación: **tab "Alertas" existente** en `Dashboard.vue:440+` (no agregar al sidebar)
- Scope: 4 cards nuevas + filtro `billing_mode=manual` en Index + arreglo N+1

---

## File structure

**Nuevos (0):** — ninguno; todo se agrega a archivos existentes.

**Modificados:**
| Path | Cambio |
|---|---|
| `app/Http/Controllers/SuperAdmin/DashboardController.php` | 4 nuevas métricas en `index()`: `grace_expiring_soon`, `orders_near_limit`, `billing_manual`, `new_this_week` |
| `app/Http/Controllers/SuperAdmin/RestaurantController.php` | `index()` acepta filtros `alert`/`billing_mode`; fix N+1 |
| `resources/js/Pages/SuperAdmin/Dashboard.vue` | 4 cards nuevas en tab `alerts`; `totalAlerts` incluye nuevos campos |
| `resources/js/Pages/SuperAdmin/Restaurants/Index.vue` | Filtros por alerta + billing_mode; columna extra con indicador |
| `tests/Feature/SuperAdminTest.php` | Tests de las nuevas métricas + filtros |

---

## Métricas (cálculos)

### 1. Grace expiring soon (≤ 3 días)
```php
Restaurant::query()
    ->where('status', 'grace_period')
    ->whereBetween('grace_period_ends_at', [now(), now()->addDays(3)])
    ->count();
```

### 2. Orders near limit (≥ 80%)
**Problema N+1 actual:** `RestaurantController@index` ya calcula esto en loop. Unifico en método reutilizable.

```php
// En LimitService o dentro del controller
Restaurant::query()
    ->withCount(['orders as period_orders_count' => fn($q) => $q
        ->whereHas('branch', fn($b) => $b->where('restaurant_id', $restaurant->id))
        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
        ->whereNotIn('status', ['cancelled']),
    ])
    ->get()
    ->filter(fn($r) => $r->orders_limit > 0 && $r->period_orders_count / $r->orders_limit >= 0.8)
    ->count();
```

⚠️ Aquí hay matiz: `orders` se scope por `branch_id` (orders no tiene `restaurant_id` directo). Necesito confirmar el shape exacto mirando el modelo antes de implementar. Queda en el plan marcado como **verificar**.

### 3. Billing manual count
```php
Restaurant::query()
    ->where('billing_mode', 'manual')
    ->where('is_active', true)
    ->count();
```

### 4. New this week (split por source)
```php
[
    'total' => Restaurant::where('created_at', '>=', now()->subDays(7))->count(),
    'self_signup' => Restaurant::where('created_at', '>=', now()->subDays(7))
        ->where('signup_source', 'self_signup')->count(),
    'super_admin' => Restaurant::where('created_at', '>=', now()->subDays(7))
        ->where('signup_source', 'super_admin')->count(),
]
```

---

## UX — Dashboard tab "Alertas"

Layout: grid 2×2 de cards nuevas, **arriba** de la tabla at-risk existente.

```
┌─────────────────────────┬─────────────────────────┐
│ 🎁 Gracia expira pronto │ ⚠️ Cerca del límite      │
│    3 restaurantes       │    5 restaurantes       │
│    [Ver →]              │    [Ver →]              │
├─────────────────────────┼─────────────────────────┤
│ 💵 Modo manual          │ 🆕 Nuevos esta semana    │
│    12 activos           │    4 (3 self, 1 admin)  │
│    [Ver →]              │    [Ver →]              │
└─────────────────────────┴─────────────────────────┘

[Tabla "Restaurantes en riesgo" existente debajo]
```

Estilos por urgencia:
- Rojo: `grace_expiring_soon > 0` o `orders_near_limit > 0`
- Naranja: `billing_manual > 0`
- Azul (info): `new_this_week > 0`

Click `[Ver →]` navega a `Restaurants/Index?alert={grace_expiring|orders_near_limit|billing_manual|new_this_week}`.

### Restaurants/Index — filtros nuevos
- Dropdown "Alertas" con las 4 opciones + "Todas"
- Dropdown "Modo de billing" con `Todos | Subscription | Manual`
- Contador en pillar encima de la tabla

### Indicador en row (Index)
- Badge naranja "Gracia 2d" cuando `grace_period_ends_at` <= 3 días
- Badge rojo "80%+" cuando orders near limit (aprovecha el progress bar existente, solo realza)
- Badge gris "Manual" cuando `billing_mode = 'manual'`

---

## Red flag bonus: N+1 en Restaurants Index

Actual (`RestaurantController.php:44-46`):
```php
$restaurants->each(function (Restaurant $restaurant): void {
    $restaurant->period_orders_count = $this->limitService->orderCountInPeriod($restaurant);
});
```

Con 200 restaurants → 200 queries extra. Se reemplaza por un `withCount` o por una query agregada en batch.

**Estrategia:** un subquery en `Restaurant::query()` que suma orders del período por restaurant (via `branches.orders`), evitando el loop.

---

## Tests

**Feature (`SuperAdminTest.php`) — 6 nuevos:**
1. `dashboard_exposes_grace_expiring_soon_count`
2. `dashboard_exposes_orders_near_limit_count`
3. `dashboard_exposes_billing_manual_count`
4. `dashboard_exposes_new_this_week_split_by_source`
5. `restaurants_index_filter_by_alert_billing_manual`
6. `restaurants_index_filter_by_alert_grace_expiring`

---

## Plan de fases

### Fase A — Backend (DashboardController + RestaurantController)
- A.1 Tests failing: 4 métricas del Dashboard
- A.2 Implementar métricas en `DashboardController@index`
- A.3 Tests failing: 2 filtros del Index
- A.4 Implementar filtros en `RestaurantController@index`
- A.5 Arreglar N+1 con subquery batch

### Fase B — Frontend (Dashboard.vue + Index.vue)
- B.1 Agregar cards en tab `alerts` de Dashboard.vue
- B.2 Click-through → Index con query param `alert=`
- B.3 Agregar dropdowns de filtro en Index.vue
- B.4 Badges inline en las rows

### Fase C — Verificación
- C.1 Suite completa verde
- C.2 Rebuild + smoke manual en browser

---

## Riesgos / edge cases

- **Período de órdenes:** usar `now()->startOfMonth()` como límite inferior (como hace `LimitService`). Revisar antes de codear porque `orders_limit_start/end` columns existen y pueden diferir. **Verificar** en Fase A.2.
- **Grace period con status != 'grace_period':** hay casos donde `grace_period_ends_at` está seteado pero el status es `canceled` con acceso aún. Mi query sólo cuenta `status='grace_period'` — consistente con `RestaurantOperationalGateTest`.
- **"New this week" incluye restaurantes inactivos:** decisión de diseño — sí los contamos (útil saber el volumen total). Si prefieres solo `is_active=true`, se filtra.
- **Click-through con query param no soportado todavía:** Index.vue necesita leer `alert` de la URL y aplicar el filtro. Sin esto, el `[Ver →]` llevaría a la lista completa.
