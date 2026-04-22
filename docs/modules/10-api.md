# Módulo 10 — API Pública (Frontend del Cliente)

> Prefijo de rutas: `/api/public/{slug}`
> Archivo: `admin/routes/api.php`
> Autenticación: slug del restaurante en la URL (sin headers de auth)

---

## Descripción General

API REST consumida exclusivamente por el SPA universal del cliente (proyecto `client/`). El tenant se resuelve en runtime a partir del `slug` del restaurante en la URL. Un único bundle del SPA sirve a N restaurantes.

**El `production_cost` de los productos NUNCA se devuelve en ningún endpoint de esta API.**

---

## Autenticación / Resolución de Tenant

Middleware personalizado: `ResolveTenantFromSlug` (alias `tenant.slug`)

```php
// URL pattern
GET /api/public/{slug}/restaurant
POST /api/public/{slug}/orders
…

// El middleware:
// 1. Extrae el slug de la ruta.
// 2. Busca el Restaurant con ese slug.
// 3. Si no existe → 404 { code: "tenant_not_found" }.
// 4. Si no puede recibir pedidos (canReceiveOrders()==false) → 410 { code: "tenant_unavailable" }.
// 5. Inyecta el Restaurant en los attributes del request: $request->attributes->get('restaurant').
```

El SPA universal construye cada URL con el slug actual, obtenido del path `/r/:slug` del browser. Los rate-limits siguen aplicándose por IP + endpoint.

---

## Endpoints

### `GET /api/restaurant`

Información del restaurante para configurar el SPA.

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "name": "La Taquería del Centro",
    "logo_url": "https://storage.../logo.png",
    "slug": "la-taqueria",
    "is_active": true,
    "allows_delivery": true,
    "allows_pickup": true,
    "allows_dine_in": false,
    "is_open": true,
    "delivery_methods": {
      "delivery": true,
      "pickup": true,
      "dine_in": false
    },
    "payment_methods": [
      { "type": "cash", "label": "Efectivo" },
      { "type": "transfer", "label": "Transferencia", "bank_name": "BBVA", "account_holder": "Carlos Gómez", "clabe": "012345678901234567", "alias": "carlost" }
    ],
    "branches": [
      { "id": 1, "name": "Sucursal Centro", "address": "Av. Reforma 123", "whatsapp": "+5215512345678", "latitude": 20.6597, "longitude": -103.3496 }
    ],
    "schedules": [
      { "day_of_week": 0, "opens_at": null, "closes_at": null, "is_closed": true },
      { "day_of_week": 1, "opens_at": "10:30", "closes_at": "21:00", "is_closed": false }
    ],
    "orders_limit_reached": false,
    "limit_reason": null
  }
}
```

> `limit_reason` puede ser: `null` (sin bloqueo), `"period_not_started"`, `"period_expired"`, o `"limit_reached"`.
> Si `orders_limit_reached = true`, el SPA muestra pantalla de "Pedidos no disponibles" con mensaje diferenciado según `limit_reason`.
> Si `is_open = false`, el SPA muestra banner "Fuera de horario" y deshabilita el menú (grayscale + pointer-events-none).
> `branches` solo incluye sucursales activas. `schedules` incluye los 7 días configurados.
> `allows_delivery`, `allows_pickup`, `allows_dine_in` son top-level para fácil acceso.

---

### `GET /api/menu`

Categorías y productos activos del restaurante. Sin `production_cost`.

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Tacos",
      "description": "Nuestros tacos favoritos",
      "image_url": "https://storage.../cat1.png",
      "sort_order": 1,
      "products": [
        {
          "id": 10,
          "name": "Taco de Bistec",
          "description": "Con cebolla, cilantro y salsa verde",
          "price": 25.00,
          "image_url": "https://storage.../prod10.png",
          "modifier_groups": [
            {
              "id": 1,
              "name": "Tipo de tortilla",
              "selection_type": "single",
              "is_required": true,
              "options": [
                { "id": 1, "name": "Maíz", "price_adjustment": 0.00 },
                { "id": 2, "name": "Harina", "price_adjustment": 15.00 }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

Solo devuelve categorías con `is_active = true` y sus productos con `is_active = true`.
Ordenado por `category.sort_order`, luego por `product.sort_order` dentro de cada categoría.

---

### `GET /api/branches`

Sucursales activas con coordenadas y horarios.

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Sucursal Centro",
      "address": "Av. Juárez 100, Centro",
      "latitude": 19.432608,
      "longitude": -99.133209,
      "whatsapp": "+5215512345678",
      "schedules": [
        { "day_of_week": 1, "opens_at": "09:00", "closes_at": "22:00", "is_closed": false },
        { "day_of_week": 0, "opens_at": null, "closes_at": null, "is_closed": true }
      ]
    }
  ]
}
```

Solo devuelve sucursales con `is_active = true`. Las coordenadas son usadas por el `DeliveryService` en el siguiente endpoint.

---

### `POST /api/delivery/calculate`

Calcula la sucursal más cercana, distancia, tiempo y costo de envío. Invoca el `DeliveryService`.

**Request:**
```json
{
  "latitude": 19.420000,
  "longitude": -99.110000
}
```

**Respuesta (dentro de cobertura, sucursal abierta):**
```json
{
  "data": {
    "branch": {
      "id": 1,
      "name": "Sucursal Centro",
      "address": "Av. Juárez 100, Centro",
      "whatsapp": "+5215512345678"
    },
    "distance_km": 3.2,
    "duration_minutes": 18,
    "delivery_cost": 30.00,
    "is_in_coverage": true,
    "is_open": true,
    "schedule": null
  }
}
```

**Respuesta (fuera de cobertura):**
```json
{
  "data": {
    "branch": { "id": 1, "name": "Sucursal Centro", ... },
    "distance_km": 18.5,
    "duration_minutes": null,
    "delivery_cost": null,
    "is_in_coverage": false,
    "is_open": true,
    "schedule": null
  }
}
```

**Respuesta (fuera de horario):**
```json
{
  "data": {
    "branch": { ... },
    "distance_km": 3.2,
    "duration_minutes": 18,
    "delivery_cost": 30.00,
    "is_in_coverage": true,
    "is_open": false,
    "schedule": {
      "day_of_week": 1,
      "opens_at": "09:00",
      "closes_at": "22:00",
      "is_closed": false
    }
  }
}
```

---

### `POST /api/orders`

Crea el pedido. Se llama desde el SPA en el Paso 3 antes de abrir WhatsApp.

**Request:**
```json
{
  "customer": {
    "token": "uuid-del-cliente-en-cookie",
    "name": "María García",
    "phone": "+5215598765432"
  },
  "delivery_type": "delivery",
  "branch_id": 1,
  "address_street": "Calle Morelos",
  "address_number": "45",
  "address_colony": "Col. Roma Norte",
  "address_references": "Entre Orizaba y Tonalá, edificio azul piso 3",
  "latitude": 19.420000,
  "longitude": -99.110000,
  "distance_km": 3.2,
  "delivery_cost": 30.00,
  "scheduled_at": null,
  "payment_method": "cash",
  "cash_amount": 200.00,
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "unit_price": 25.00,
      "notes": "sin cebolla",
      "modifiers": [
        { "modifier_option_id": 1, "price_adjustment": 0.00 }
      ]
    }
  ]
}
```

**Validaciones en backend (`OrderService`):**
1. El restaurante está activo y no alcanzó su límite de pedidos del periodo (re-checked con `lockForUpdate()` dentro de transacción).
2. El `branch_id` pertenece al restaurante del token **y la sucursal está activa**.
3. Todos los `product_id` pertenecen al restaurante y están activos.
4. Todos los `modifier_option_id` pertenecen **al producto específico del item** (no colectivamente al restaurante). Previene cross-product modifier injection.
5. **Modifier groups requeridos** (`is_required = true`) deben tener al menos una opción seleccionada por item.
6. **No se permiten `modifier_option_id` duplicados** dentro del mismo item (regla `distinct`).
7. Los precios del request coinciden con los precios en DB (validación anti-tampering ±$0.01).
8. `subtotal` y `total` se **recalculan en backend** (nunca se confía en el total del request).
9. `delivery_cost` se **calcula server-side** a partir de los `DeliveryRange` del restaurante según `distance_km`. El valor enviado por el cliente se ignora. `distance_km` en el request también se **ignora** — el backend recalcula la distancia via Google Maps.
13. `cash_amount` (nullable): monto con el que el cliente paga en efectivo. Solo relevante si `payment_method = "cash"` y el cliente desea indicar con cuánto paga.
10. `delivery_type` debe estar permitido por el restaurante (flags `allows_*`).
11. `payment_method` debe ser un método activo del restaurante.
12. Endpoint tiene **rate limiting** (`throttle:30,1` — 30 requests/minuto).

**Respuesta:**
```json
{
  "data": {
    "order_id": 42,
    "order_number": "#0042",
    "branch_whatsapp": "+5215512345678",
    "whatsapp_message": "Hola! Quiero hacer un pedido:\n\n🛵 *A domicilio*\n\n🍽️ *Mi pedido:*\n- 2x Taco de Bistec (Maíz) - sin cebolla · $50.00\n\n📍 *Dirección:* Calle Morelos 45, Col. Roma Norte\n*Referencias:* Entre Orizaba y Tonalá, edificio azul piso 3\n\n💰 *Subtotal:* $50.00\n🚚 *Envío:* $30.00\n✅ *Total:* $80.00\n\n💳 *Pago:* Efectivo\n\n¡Gracias! 🙌"
  }
}
```

El SPA usa el `whatsapp_message` para construir el link de WhatsApp:
```
https://wa.me/{branch_whatsapp}?text={encodeURIComponent(whatsapp_message)}
```

**Si el restaurante alcanzó su límite de pedidos del periodo:**
```json
{
  "message": "Lo sentimos, no podemos recibir más pedidos en este periodo.",
  "error": "limit_reached"
}
// HTTP 422
```

Los posibles valores de `error` relacionados con límites son: `"period_not_started"`, `"period_expired"`, `"limit_reached"` — correspondientes al `limit_reason` de `GET /api/restaurant`.

---

## Recursos Eloquent (API Resources)

```
app/Http/Resources/
├── RestaurantResource.php
├── MenuCategoryResource.php
├── MenuProductResource.php       ← Excluye production_cost
├── ModifierGroupResource.php
├── ModifierOptionResource.php
├── BranchResource.php
├── RestaurantScheduleResource.php
├── DeliveryCalculationResource.php
└── OrderConfirmationResource.php
```

---

## Manejo de Errores

| HTTP Status | Descripción |
|---|---|
| `401` | Token inválido o restaurante inactivo |
| `422` | Validación fallida (campos faltantes, límite alcanzado) |
| `500` | Error interno (ej. falla de Google Distance Matrix) |

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[08-customer-flow.md](./08-customer-flow.md)** | El SPA consume todos estos endpoints. |
| **[09-delivery-service.md](./09-delivery-service.md)** | `POST /api/public/{slug}/delivery/calculate` invoca el `DeliveryService`. |
| **[07-superadmin.md](./07-superadmin.md)** | El `slug` del restaurante se define al crearlo; renombrar solo desde SuperAdmin. |
| **[04-menu.md](./04-menu.md)** | `GET /api/public/{slug}/menu` devuelve solo productos activos; `production_cost` nunca se incluye. |
| **[05-branches.md](./05-branches.md)** | `GET /api/public/{slug}/branches` y `POST /api/public/{slug}/delivery/calculate` dependen de sucursales activas con coordenadas. |
| **[06-settings.md](./06-settings.md)** | `GET /api/public/{slug}/restaurant` devuelve métodos de entrega y pago activos. |

---

_PideAqui — Módulo API v1.2 — Marzo 2026_
