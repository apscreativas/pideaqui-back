# Módulo 08 — Flujo del Cliente (SPA)

> Pantallas de referencia: `c_01` menu_home, `c_02` product_detail_and_modifiers, `c_03` cart_summary, `c_04` delivery_location_selection, `c_05` payment_and_order_confirmation, `c_06` order_confirmed
> Proyecto: `client/` (Vue 3 SPA independiente)

---

## Descripción General

Frontend independiente del cliente final. Es un SPA (Single Page Application) construido con Vite + Vue 3 que se comunica con el backend exclusivamente mediante la API REST. Se despliega una instancia por restaurante.

El cliente **no necesita registrarse ni hacer login**. Sus datos persisten en cookies del navegador por 90 días y se pre-rellenan en futuros pedidos.

El flujo completo es de **3 pasos**:
```
PASO 1: Seleccionar del menú
PASO 2: ¿A dónde te lo llevamos? (tipo de entrega + ubicación)
PASO 3: ¿Cómo pagas? + Confirmación
```

---

## Arquitectura del SPA

```
client/
├── src/
│   ├── main.js          ← createApp + router + pinia
│   ├── App.vue          ← Root component
│   ├── router/          ← vue-router (rutas del SPA)
│   ├── stores/          ← Pinia stores
│   │   ├── cart.js      ← Carrito de compras
│   │   ├── order.js     ← Estado del pedido en curso
│   │   └── restaurant.js ← Datos del restaurante (desde API)
│   ├── views/           ← Vistas principales (una por pantalla)
│   └── components/      ← Componentes reutilizables
├── .env                 ← VITE_API_BASE_URL, VITE_RESTAURANT_TOKEN
└── vite.config.js
```

**Variables de entorno por instancia:**
- `VITE_API_BASE_URL` — URL del backend.
- `VITE_RESTAURANT_TOKEN` — `access_token` único del restaurante (configurado por SuperAdmin).
- `VITE_GOOGLE_MAPS_KEY` — API key de Google Maps (para el mapa de ubicación).

Cada restaurante tiene su propia instancia con su propio `VITE_RESTAURANT_TOKEN`.

---

## Pantallas y Flujo

### `c_01` — Inicio del Menú (Paso 1) `/ `

**Comportamiento:**
- Al cargar, hace `GET /api/restaurant` para obtener nombre, logo y datos del restaurante.
- Hace `GET /api/menu` para obtener categorías y productos activos.
- Muestra categorías como chips horizontales scrolleables.
- Al seleccionar una categoría, hace scroll hasta esa sección del menú.
- Cada producto muestra: foto, nombre, descripción breve, precio.
- Al tocar un producto → abre `c_02` (modal o página del producto).

**Carrito flotante (siempre visible si hay ítems):**
- Barra inferior fija: "X productos · $XXX" + botón "Ver carrito".
- Al tocar → abre `c_03` (resumen del carrito).

**Sin geolocalización en este paso** — la sucursal no se determina hasta el Paso 2.

**Estados especiales:**
- Si `restaurant.is_active = false` → pantalla de "Restaurante no disponible".
- Si `restaurant.is_open === false` (fuera de horario) → banner oscuro "Fuera de horario" con el horario del día si aplica. Todo el menú (chips de categoría + tarjetas de productos) se muestra con `grayscale + opacity-50 + pointer-events-none`. El usuario puede ver el menú pero no puede interactuar. El CartBar se deshabilita (gris, sin click).
- Si `orders_limit_reached = true` → El CartBar se deshabilita. MenuHome muestra mensajes diferenciados según `limit_reason`:
  - `period_not_started` → "El periodo de pedidos aún no ha comenzado" (con fechas del periodo).
  - `period_expired` → "El periodo de pedidos ha expirado" (con fechas del periodo).
  - `limit_reached` → "Se alcanzó el límite de pedidos del periodo" (con fechas del periodo).

**Visibilitychange:** `App.vue` re-fetcha `fetchRestaurant()` cuando la tab vuelve a ser visible (`document.addEventListener('visibilitychange', ...)`). Esto detecta cambios de horario, límites de pedidos o estado del restaurante que ocurrieron mientras el usuario estaba en otra tab.

### `c_02` — Detalle del Producto / Modificadores

Modal o pantalla completa sobre el menú.

**Contenido:**
- Foto grande del producto.
- Nombre, descripción completa, precio base.
- **Grupos de modificadores** (en orden de `sort_order`):
  - Cada grupo con su nombre y tipo (radio = único, checkbox = múltiple).
  - Opciones con precio adicional (ej. "+$15").
  - Grupos `is_required = true` se marcan como obligatorios.
- **Campo de nota libre**: texto libre, no afecta precio (ej. "sin aguacate").
- Selector de cantidad (mínimo 1).
- **Precio dinámico**: se actualiza en tiempo real al seleccionar modificadores.
- Botón "Agregar al carrito" — deshabilitado si hay grupos requeridos sin seleccionar.

Al agregar → el ítem se añade al store del carrito (Pinia) y se cierra el modal.

### `c_03` — Carrito de Compras

Vista del resumen del carrito antes de continuar.

**Contenido:**
- Lista de productos con: nombre, modificadores seleccionados, nota, cantidad, subtotal.
- Botones para editar cantidad o eliminar ítem.
- Subtotal total.
- Botón "Continuar" → navega al Paso 2 (`c_04`).

Si el carrito está vacío → mensaje "Tu carrito está vacío" con botón de regreso.

### `c_04` — Tipo de Entrega y Ubicación (Paso 2)

La pantalla más compleja del flujo.

**Selección de tipo de entrega** (solo se muestran los métodos activos del restaurante):

#### Si elige 🛵 A domicilio:

1. Se verifica si hay datos de dirección en cookies → pre-rellena formulario.
2. Si no hay cookies → solicita permiso de GPS al navegador.
3. Muestra **mapa interactivo** (Google Maps JavaScript API) con pin en la ubicación del cliente.
4. El cliente puede mover el pin para ajustar su ubicación exacta.
5. **Formulario de dirección** (se llena manualmente — no geocoding inverso, campos estructurados):
   - `address_street` — Calle.
   - `address_number` — Número.
   - `address_colony` — Colonia.
   - `address_references` — Referencias: entre calles, color de casa, número de depto, etc.
   - Se pre-rellenan desde cookie si hay datos previos.
6. Al confirmar dirección → llama a `POST /api/delivery/calculate` con las coordenadas del pin.
7. El backend responde con:
   - Sucursal asignada (nombre, dirección).
   - Distancia real por calles (km).
   - Tiempo estimado de entrega.
   - Costo de envío (según rangos configurados).
8. Se muestra resumen: "Tu pedido llegará desde Sucursal Centro · 3.2 km · ~30 min · Envío $30".
9. **Validación de cobertura**: si excede la distancia máxima → mensaje de fuera de cobertura con opciones alternativas.
10. **Validación de horario**: si la sucursal asignada está cerrada → mensaje con horarios del día.

#### Si elige 🏃 Recoger en local:

- Si hay múltiples sucursales activas → se detecta la más cercana o el cliente elige manualmente.
- Se muestra dirección y teléfono de la sucursal.
- No se solicita dirección del cliente.

#### Si elige 🍽️ Comer aquí:

- Si hay múltiples sucursales → el cliente elige en cuál se encuentra.
- Sin geolocalización ni cálculo de distancia.

**Programación del pedido** (se muestra para todos los tipos de entrega):
- "¿Para cuándo tu pedido?"
  - 🕐 **Lo antes posible** (default).
  - 📅 **Programar para más tarde** → chips de hora en intervalos de 30 min dentro del horario del **restaurante** (no de la sucursal). Se generan todos los slots posibles desde el próximo slot disponible (mínimo 30 min en el futuro) hasta la hora de cierre. Los chips se muestran en un grid con flex-wrap (no scroll horizontal) para mostrar todas las opciones de una vez.

### `c_05` — Pago y Confirmación (Paso 3)

**Datos del cliente** (pre-rellenados de cookies si existen):
- Nombre completo (requerido).
- Teléfono (requerido).

**Método de pago** (solo los activos del restaurante):
- Si solo hay uno activo → preseleccionado automáticamente.
- Si selecciona transferencia → se muestran los datos bancarios del restaurante.

**Resumen final:**
- Lista de productos con modificadores, notas, cantidades.
- Subtotal.
- Costo de envío.
- Hora programada (si aplica).
- **Total**.

**Botón "Confirmar y enviar por WhatsApp":**
1. Se abre una **ventana en blanco** (`window.open('')`) ANTES de la llamada API para evitar el popup blocker del navegador.
2. Se registra el pedido en DB via `POST /api/orders`.
3. El backend responde con el ID del pedido, el número de WhatsApp de la sucursal y el `whatsapp_message` pre-generado.
4. **En caso de éxito:** se redirige la ventana abierta a `wa.me/{whatsapp_sucursal}?text={mensaje_encoded}`.
5. **En caso de error:** se cierra la ventana popup.
6. Se guardan/actualizan los datos del cliente en cookies (90 días).
7. Se navega a `c_06`.

### `c_06` — Confirmación del Pedido

Pantalla de éxito post-pedido.

**Contenido:**
- Ícono de éxito.
- "¡Tu pedido #XXXX está en camino!"
- Mensaje: "Continúa la conversación por WhatsApp con la sucursal."
- Número de pedido y resumen breve.
- La comunicación de seguimiento es **directamente por WhatsApp** — no hay tracking en el SPA.
- Botón "Hacer otro pedido" → regresa al menú limpiando el carrito.

---

## Estado del Pedido (Pinia Stores)

### `cart` store
```js
{
  items: [
    {
      product_id,
      product_name,
      unit_price,        // precio base del producto
      quantity,
      notes,             // nota libre
      modifiers: [
        { modifier_option_id, name, price_adjustment }
      ],
      item_total         // unit_price + Σ price_adjustment × quantity
    }
  ],
  subtotal               // Σ item_total
}
```

### `order` store
```js
{
  delivery_type,         // 'delivery' | 'pickup' | 'dine_in'
  branch_id,             // sucursal asignada
  branch_name,
  branch_whatsapp,
  distance_km,
  delivery_cost,
  address_street,        // calle
  address_number,        // número
  address_colony,        // colonia
  address_references,    // referencias
  latitude,
  longitude,
  scheduled_at,          // null = lo antes posible
  payment_method,        // 'cash' | 'terminal' | 'transfer'
  cash_amount,           // monto con el que paga (nullable, solo si payment_method=cash)
  customer_name,
  customer_phone
}
```

---

## Cookies del Cliente

| Clave | Tipo | Datos guardados | Expiración |
|---|---|---|---|
| `pideaqui_customer` | Cookie | `{ token, name, phone, address_street, address_number, address_colony, address_references, latitude, longitude }` | 90 días |
| `pideaqui_cart` | localStorage | Items del carrito (Pinia persist) | Persistente |
| `pideaqui_order` | localStorage | Estado del pedido en curso (Pinia persist) | Persistente |

El `token` es un UUID generado la primera vez. Se envía en `POST /api/orders` para identificar al cliente en DB.

---

## Reglas de Negocio

- El cliente nunca ve el `production_cost` de los productos.
- La geolocalización **no se solicita** en el Paso 1 (menú). Solo en el Paso 2 si elige domicilio.
- Si el restaurante tiene **una sola sucursal activa**, se asigna automáticamente sin pedir GPS.
- Si el restaurante tiene **múltiples sucursales activas**: se usa GPS + Haversine + Distance Matrix (ver [09-delivery-service.md](./09-delivery-service.md)).
- El mensaje de WhatsApp lo **envía el cliente** manualmente — la app solo abre WhatsApp con el mensaje pre-llenado.
- El pedido se registra en DB **antes** de abrir WhatsApp. Si el cliente cierra WhatsApp sin enviar, el pedido ya quedó registrado con status `received`.
- Si el cliente está fuera de cobertura de domicilio, aún puede elegir "Recoger en local" o "Comer aquí".

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[10-api.md](./10-api.md)** | Este SPA consume todos los endpoints de la API pública. |
| **[09-delivery-service.md](./09-delivery-service.md)** | El Paso 2 del flujo llama al `DeliveryService` via API para calcular sucursal, distancia y costo. |
| **[04-menu.md](./04-menu.md)** | El menú que ve el cliente (c_01, c_02) es el configurado por el admin. |
| **[06-settings.md](./06-settings.md)** | Métodos de entrega (ar_15) y pago (ar_17) determinan qué opciones ve el cliente en c_04 y c_05. |
| **[05-branches.md](./05-branches.md)** | Las sucursales activas con coordenadas y horarios son la fuente del Paso 2. |
| **[03-orders.md](./03-orders.md)** | El pedido confirmado en c_05 aparece en el Kanban del admin. |

---

## Notas de Diseño

**Sistema de diseño (cliente):**
- Mobile-first. Ancho máximo ~390px (viewport móvil).
- Fondo: `#f6f8f7`.
- Color primario: `#FF5722`.
- Bordes muy redondeados (`border-radius: 1rem`, `2rem`).
- Fuente: Inter.
- Dark mode soportado (fondo oscuro: `#131f18`).

**Header (c_01):**
- Sticky top. Logo circular del restaurante + nombre del restaurante + ícono de búsqueda.

**Categorías:**
- Chips horizontales con scroll sin scrollbar visible (clase `no-scrollbar`).
- Chip activo: fondo naranja `#FF5722` + texto blanco + sombra naranja.

**Carrito flotante:**
- Barra fija en la parte inferior, por encima del contenido.
- Fondo naranja, botón pill, con contador de ítems.

**Mapa (c_04):**
- Mapa a pantalla completa con panel de formulario encima o debajo.
- Pin naranja arrastrable.

---

_PideAqui — Módulo Flujo del Cliente v1.2 — Marzo 2026_
