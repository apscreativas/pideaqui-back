# 12. Mapa Operativo

Mapa interactivo con la ubicacion geografica de pedidos y sucursales usando Google Maps JavaScript API.

---

## Pantalla

No existe mockup dedicado (feature post-MVP). Se accede desde el sidebar del admin: **"Mapa"** (icon: `map`).

---

## Ruta

| Metodo | URI | Controlador | Nombre |
|--------|-----|------------|--------|
| `GET` | `/map` | `MapController@index` | `map.index` |

---

## Componentes

### Map/Index.vue

**Props recibidas:** `orders`, `branches`, `kpis`, `filters`, `allBranches`, `mapsKey`

**Funcionalidades:**
- **Mapa Google Maps** con markers interactivos
- **Markers de pedidos** coloreados por status:
  - Recibido: rojo
  - Preparacion: naranja
  - En camino: azul
  - Entregado: verde
  - Cancelado: gris
- **Markers de sucursales:** Pin naranja con label
- **InfoWindow:** Click en marker muestra # pedido, status, link al detalle
- **Filtros:**
  - Presets de fecha (hoy, ayer, 7 dias, mes) + rango personalizado
  - Filtro por sucursal (dropdown)
  - Toggle pills por status (mostrar/ocultar por tipo)
- **KPIs sidebar:**
  - Total pedidos, pedidos activos, entregados, cancelados
  - Revenue
  - Pedidos geolocalizados

---

## Dependencias

- `GOOGLE_MAPS_API_KEY` (variable de entorno backend) — se pasa como `mapsKey` via `config('services.google_maps.key')`
- Solo muestra pedidos que tienen `latitude` y `longitude` (pedidos de delivery)

---

## Reglas de Negocio

- Los pedidos sin coordenadas (pickup/dine_in) no aparecen en el mapa pero si en los KPIs
- Las sucursales siempre se muestran como referencia geografica
- El mapa se centra automaticamente para incluir todos los markers visibles

---

## Modulos Relacionados

- [03 — Pedidos](./03-orders.md) — InfoWindow enlaza al detalle del pedido
- [05 — Sucursales](./05-branches.md) — Markers de sucursales
- [09 — DeliveryService](./09-delivery-service.md) — Coordenadas calculadas al crear pedido
