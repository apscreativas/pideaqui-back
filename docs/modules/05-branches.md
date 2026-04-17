# Módulo 05 — Gestión de Sucursales

> Pantallas de referencia: `ar_11_branch_list_management`, `ar_12_branch_creation_map_positioning`

---

## Descripción General

Módulo para crear y administrar las sucursales del restaurante. Cada sucursal es una ubicación física independiente con su propio WhatsApp y coordenadas geográficas.

El menú y los **horarios de operación** son **compartidos** a nivel restaurante — no se configuran por sucursal (ver [06-settings.md](./06-settings.md)). Lo que sí es propio de cada sucursal: nombre, dirección, coordenadas, WhatsApp y estado activo/inactivo.

La cantidad de sucursales que puede crear el admin está **limitada** por el SuperAdmin (`max_branches` en la tabla `restaurants`).

---

## Pantallas

### `ar_11` — Lista de Sucursales (`/branches`)

- Tabla o lista de cards con todas las sucursales del restaurante.
- Por cada sucursal: nombre, dirección, teléfono WhatsApp, estado (activa/inactiva), número de pedidos del mes.
- Toggle para activar/desactivar sucursal (sin eliminar).
- Botones de editar y eliminar.
- Indicador en la parte superior: **"X sucursales de Y disponibles"** (X = creadas, Y = `max_branches`).
- Botón "+ Crear sucursal" deshabilitado con tooltip si se alcanzó el límite.

### `ar_12` — Crear / Editar Sucursal (`/branches/create` o `/branches/{id}/edit`)

Formulario en pantalla completa con mapa integrado.

**Campos:**
- Nombre descriptivo (ej. "Sucursal Centro", "Sucursal Norte"). Requerido.
- Dirección completa (texto). Requerido.
- **Mapa interactivo (Google Maps)** con pin arrastrable:
  - El admin arrastra el pin para ubicar la sucursal exacta.
  - Las coordenadas (`latitude`, `longitude`) se guardan automáticamente al soltar el pin.
  - El mapa sirve solo para obtener coordenadas — la dirección se ingresa manualmente.
- Número de WhatsApp (con código de país +52). Requerido.
- Estado activo/inactivo.

**Nota:** El mapa usa **Google Maps JavaScript API** (no estático). El pin es arrastrable.

**Nota:** Los horarios de operación se configuran a nivel restaurante en `/settings/schedules`, no por sucursal. Ver [06-settings.md](./06-settings.md).

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `Branch` | `branches` | Sucursal del restaurante |
| `Restaurant` | `restaurants` | `max_branches` — límite de sucursales |
| `Order` | `orders` | Pedidos asociados a cada sucursal |

### Campos clave de `Branch`

| Campo | Tipo | Descripción |
|---|---|---|
| `restaurant_id` | bigint | Tenant — restaurante propietario |
| `name` | varchar | Nombre descriptivo |
| `address` | text | Dirección completa |
| `latitude` | decimal(10,8) | Coordenada del pin del mapa |
| `longitude` | decimal(11,8) | Coordenada del pin del mapa |
| `whatsapp` | varchar | Número con código de país (ej. `+5215512345678`) |
| `is_active` | boolean | Activa/inactiva |

---

## Reglas de Negocio

- Un restaurante debe tener **al menos 1 sucursal activa** para poder recibir pedidos. El backend impide desactivar (toggle/update) o eliminar la última sucursal activa.
- No se pueden crear más sucursales que `restaurant.max_branches`. El sistema debe validarlo en el backend (no solo en el frontend).
- Desactivar una sucursal no elimina sus pedidos históricos.
- Una sucursal inactiva **no aparece** en la API del cliente ni participa en el cálculo de sucursal más cercana.
- El frontend deshabilita visualmente los botones de toggle y eliminar cuando solo queda 1 sucursal activa.
- Las **coordenadas de la sucursal** son críticas para el algoritmo de detección de sucursal más cercana (ver [09-delivery-service.md](./09-delivery-service.md)).
- El número de WhatsApp de la sucursal es el destino del mensaje de pedido. Si no está bien configurado, el cliente no podrá enviar el pedido.
- Los **horarios** se configuran a nivel restaurante (no por sucursal). Ver [06-settings.md](./06-settings.md).

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[09-delivery-service.md](./09-delivery-service.md)** | Las coordenadas de cada sucursal son la entrada principal del algoritmo Haversine + Distance Matrix. Sin coordenadas válidas, el cálculo falla. |
| **[08-customer-flow.md](./08-customer-flow.md)** | En el Paso 2, el cliente selecciona tipo de entrega. El servicio de delivery usa las sucursales activas para determinar la más cercana. |
| **[06-settings.md](./06-settings.md)** | Los horarios de operación se configuran a nivel restaurante, no por sucursal. |
| **[10-api.md](./10-api.md)** | El endpoint `GET /api/restaurant` devuelve las sucursales activas con sus coordenadas. |
| **[03-orders.md](./03-orders.md)** | Cada pedido tiene un `branch_id`. El Kanban puede filtrarse por sucursal. |
| **[07-superadmin.md](./07-superadmin.md)** | El SuperAdmin configura `max_branches`. Este módulo valida contra ese límite al crear una nueva sucursal. |
| **[02-dashboard.md](./02-dashboard.md)** | El dashboard muestra pedidos agrupados por sucursal. |

---

## Implementación Backend

```
Routes:
  GET    /branches                → BranchController@index
  GET    /branches/create         → BranchController@create
  POST   /branches                → BranchController@store (valida max_branches)
  GET    /branches/{id}/edit      → BranchController@edit
  PUT    /branches/{id}           → BranchController@update
  DELETE /branches/{id}           → BranchController@destroy
  PATCH  /branches/{id}/toggle    → BranchController@toggle

Form Requests:
  StoreBranchRequest
    - Valida: name, address, latitude, longitude, whatsapp (required)
    - Valida: que el restaurante no exceda max_branches
  UpdateBranchRequest

Policy:
  BranchPolicy → branch.restaurant_id === auth()->user()->restaurant_id
```

**Integración Google Maps (ar_12):**
- Se carga Google Maps JavaScript API en el componente Vue del formulario.
- El pin inicial se ubica en las coordenadas de la sucursal (edición) o en la ciudad del restaurante (creación).
- Al soltar el pin (`dragend`), se actualizan los campos ocultos `latitude` y `longitude` en el formulario.

---

## Notas de Diseño

### Lista de Sucursales (ar_11)
- Cards en grid de 2 columnas en desktop. Cada card muestra: nombre, dirección truncada, chip de estado (verde = activa, gris = inactiva), WhatsApp y botones de acción.
- Indicador de límite en la cabecera: "2 / 3 sucursales" con barra de progreso.
- Si se alcanza el límite, el botón "+ Crear sucursal" aparece deshabilitado con tooltip explicativo.

### Creación/Edición (ar_12)
- Formulario a la izquierda + mapa a la derecha en layout de dos columnas.
- El mapa ocupa al menos 350px de alto.
- Pin naranja (`#FF5722`) como marcador del mapa.
- Los campos `latitude` y `longitude` son campos ocultos actualizados por el evento `dragend` del mapa.

---

_PideAqui — Módulo Sucursales v1.2 — Marzo 2026_
