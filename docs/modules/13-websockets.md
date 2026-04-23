# 13. WebSockets (Tiempo Real)

Comunicacion en tiempo real entre el backend y el panel admin usando Laravel Reverb + Laravel Echo. Permite que el tablero Kanban de pedidos se actualice automaticamente sin recargar la pagina.

---

## Stack Tecnico

| Tecnologia | Version | Rol |
|-----------|---------|-----|
| Laravel Reverb | v1.8 | Servidor WebSocket (Pusher-compatible) |
| Laravel Echo | v2.3 | Cliente JavaScript para suscribirse a canales |
| pusher-js | v8.4 | Transporte subyacente (compatible con Reverb) |

---

## Canal

| Canal | Tipo | Autenticacion |
|-------|------|---------------|
| `restaurant.{restaurantId}` | Privado | `routes/channels.php` — verifica que `user.restaurant_id === restaurantId` |

Cada admin de restaurante solo escucha eventos de **su propio restaurante**.

---

## Eventos

Los 3 eventos implementan `ShouldBroadcastNow` (sincronos, sin cola).

### OrderCreated

- **Disparado por:** `Api/OrderController@store` (cuando un cliente crea un pedido)
- **Canal:** `restaurant.{order.restaurant_id}`
- **Payload:** order (id, status, delivery_type, payment_method, subtotal, delivery_cost, total, scheduled_at, created_at), customer (id, name, phone), branch (id, name)

### OrderStatusChanged

- **Disparado por:** `OrderController@advanceStatus` (cuando el admin avanza el status)
- **Canal:** `restaurant.{order.restaurant_id}`
- **Payload:** Mismo que OrderCreated + `previous_status`
- **Nota:** Usa `broadcast()->toOthers()` para no notificar al admin que hizo el cambio

### OrderCancelled

- **Disparado por:** `OrderController@cancel` (cuando el admin cancela el pedido)
- **Canal:** `restaurant.{order.restaurant_id}`
- **Payload:** Mismo que OrderStatusChanged + `cancellation_reason`, `cancelled_at`
- **Nota:** Usa `broadcast()->toOthers()`

---

## Frontend (Orders/Index.vue)

```js
// Suscripcion (onMounted)
const echo = window.getEcho()
echo.private(`restaurant.${restaurantId}`)
  .listen('OrderCreated', handler)
  .listen('OrderStatusChanged', handler)
  .listen('OrderCancelled', handler)

// Cleanup (onUnmounted)
window.getEcho()?.leave(`restaurant.${restaurantId}`)
```

**Comportamiento:**
- `OrderCreated` → agrega el pedido a la columna "Recibido" (si pasa filtros de fecha/sucursal)
- `OrderStatusChanged` → mueve el pedido a la columna del nuevo status
- `OrderCancelled` → remueve el pedido del tablero

---

## Inicializacion (bootstrap.js)

Echo se inicializa **lazy** via `window.getEcho()`:
- Solo se crea la instancia si `VITE_REVERB_APP_KEY` esta configurada
- Evita errores de Pusher en paginas que no usan WebSockets
- Authorizer personalizado: POST a `/broadcasting/auth` con `socket_id` y `channel_name` via axios

---

## Resiliencia (Broadcast Decoupling)

Los controllers envuelven `broadcast()` en `try/catch`:
- Si Reverb **no esta corriendo**, el cambio de status se guarda igual en la base de datos
- Se logea un warning pero la operacion no falla
- El admin puede recargar la pagina para ver el estado actualizado

---

## Variables de Entorno

### Backend
```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=localhost      # Dominio en produccion
REVERB_PORT=8080
REVERB_SCHEME=http         # 'https' en produccion
```

### Frontend (Vite)
```dotenv
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${REVERB_HOST}
VITE_REVERB_PORT=${REVERB_PORT}
VITE_REVERB_SCHEME=${REVERB_SCHEME}
```

### Testing
```xml
<!-- phpunit.xml -->
<env name="BROADCAST_CONNECTION" value="null"/>
```

---

## Desarrollo Local

```bash
# Iniciar el servidor WebSocket
php artisan reverb:start
```

Reverb escucha en `127.0.0.1:8080` (configurable via `REVERB_HOST` / `REVERB_PORT`). Si quieres exponerlo detras del TLS de Herd, usa `herd proxy reverb http://127.0.0.1:8080`. Sin Reverb corriendo, la app funciona normalmente — los cambios requieren recargar la pagina.

---

## Produccion

### Laravel Cloud
WebSockets gestionados automaticamente. Ver README del backend.

### Servidor Tradicional
Correr Reverb como servicio systemd. Proxy Nginx para WSS. Ver README del backend.

---

## Modulos Relacionados

- [03 — Pedidos](./03-orders.md) — Kanban es el consumidor principal de WebSockets
- [10 — API Publica](./10-api.md) — `Api/OrderController@store` dispara `OrderCreated`
