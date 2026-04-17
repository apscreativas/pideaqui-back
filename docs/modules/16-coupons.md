# Módulo 16 — Cupones de Descuento

> **Tipo:** Feature transversal (afecta admin, API pública y cliente SPA)
> **Estado:** Implementado (Mar 2026 — 40 tests)
> **Pantallas de referencia:** sin mockup (post-MVP)

Sistema de cupones de descuento **por restaurante**. Cada restaurante gestiona su propio catálogo de códigos. Los cupones aplican siempre al **subtotal** del pedido, nunca al costo de envío. El descuento queda calculado en backend (anti-tampering) y se guarda como snapshot en la orden.

---

## Reglas de negocio

- Un cupón tiene un código alfanumérico **único por restaurante** (case-insensitive). Dos restaurantes pueden tener el mismo código sin conflicto.
- El descuento puede ser **fijo** (monto en MXN) o **porcentaje** (con `max_discount` opcional para capar porcentajes grandes).
- **Fórmula:** `total = subtotal - discount_amount + delivery_cost`. El delivery nunca se descuenta.
- **Un cupón por pedido máximo.**
- **Tracking por `customer_phone`** — no hay cuenta de usuario en GuisoGo.
- Las **órdenes canceladas no liberan el uso** — el `coupon_use` queda asociado.
- `min_purchase`, `max_uses_per_customer`, `max_total_uses`, `starts_at` y `ends_at` son opcionales; si no se setean, no aplican.

---

## Schema

### Tabla `coupons`

Migración: `database/migrations/2026_03_24_144533_create_coupons_table.php`

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | |
| `restaurant_id` | FK | `cascadeOnDelete` |
| `code` | string | unique por restaurante (índice compuesto) |
| `discount_type` | enum | `fixed` \| `percentage` |
| `discount_value` | decimal(8,2) | monto MXN si fixed; % si percentage |
| `max_discount` | decimal(8,2) | nullable — cap para porcentajes |
| `min_purchase` | decimal(8,2) | nullable |
| `starts_at` | timestamp | nullable |
| `ends_at` | timestamp | nullable |
| `max_uses_per_customer` | unsignedInt | nullable |
| `max_total_uses` | unsignedInt | nullable |
| `is_active` | boolean | default `true` |
| `timestamps` | | |

**Índice único:** `(restaurant_id, code)`.

### Tabla `coupon_uses`

Migración: `2026_03_24_144534_create_coupon_uses_table.php`

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | |
| `coupon_id` | FK | `cascadeOnDelete` |
| `order_id` | FK | `cascadeOnDelete` |
| `customer_phone` | string | |
| `created_at` | timestamp | **sin `updated_at`** |

**Índice:** `(coupon_id, customer_phone)` para queries por cliente.

### ALTER `orders`

Migración: `2026_03_24_144534_add_coupon_fields_to_orders_table.php`

- `coupon_id` — FK nullable, `nullOnDelete` (si se borra el cupón, la orden conserva el snapshot)
- `coupon_code` — string nullable (snapshot del código aplicado)
- `discount_amount` — decimal(8,2) default `0`

---

## Modelos

### `app/Models/Coupon.php`

**Casts:**

```php
'discount_value' => 'decimal:2',
'max_discount' => 'decimal:2',
'min_purchase' => 'decimal:2',
'is_active' => 'boolean',
'starts_at' => 'datetime',
'ends_at' => 'datetime',
```

**Relaciones:**

- `restaurant()` — `BelongsTo`
- `uses()` — `HasMany(CouponUse::class)`

**Scopes:**

- `active()` — `where('is_active', true)`
- `valid()` — `active()` + dentro de `starts_at` / `ends_at`

**Métodos:**

- `isValidForOrder(float $subtotal, string $customerPhone, bool $lockUses = false): array`
  Retorna `['valid' => bool, 'reason' => ?string]`.
  Valida en orden: `is_active` → vigencia temporal → `min_purchase` → `max_uses_per_customer` → `max_total_uses`.
  Si `$lockUses === true`, las queries a `coupon_uses` usan `lockForUpdate()` (para atomicidad dentro de la transacción del pedido).

- `calculateDiscount(float $subtotal): float`
  Calcula el descuento aplicado. Para `percentage`, aplica `min(subtotal * value / 100, max_discount ?? ∞)`. El resultado se capa a `min(discount, subtotal)` — nunca descuenta más que el subtotal.

**Tenant:** usa `BelongsToTenant`.

### `app/Models/CouponUse.php`

- `$timestamps = false` (solo `created_at` seteado por la DB)
- Cast `created_at` → datetime
- Relaciones: `coupon()`, `order()` — `BelongsTo`

---

## Admin — Panel del Restaurante

### Controller

`app/Http/Controllers/CouponController.php`

| Ruta | Método | Endpoint | Acción |
| --- | --- | --- | --- |
| `coupons.index` | GET | `/coupons` | Lista + KPIs (total, activos, expirados) + paginación |
| `coupons.create` | GET | `/coupons/create` | |
| `coupons.store` | POST | `/coupons` | |
| `coupons.edit` | GET | `/coupons/{coupon}/edit` | Precarga `uses_count` |
| `coupons.update` | PUT | `/coupons/{coupon}` | |
| `coupons.destroy` | DELETE | `/coupons/{coupon}` | |
| `coupons.toggle-active` | PATCH | `/coupons/{coupon}/toggle-active` | Cambia `is_active` sin validar otros campos |

Todas las acciones autorizan con `CouponPolicy` (tenant-scoped).

### Form Requests

`StoreCouponRequest` y `UpdateCouponRequest`:

```php
// prepareForValidation() aplica trim + UPPERCASE al código antes de validar
```

| Campo | Reglas |
| --- | --- |
| `code` | required, string, max:20, `alpha_dash`, unique por restaurante (en Update `ignore($couponId)`) |
| `discount_type` | required, in:`fixed`,`percentage` |
| `discount_value` | required, numeric, min:0.01, max:99999.99 |
| `max_discount` | nullable, numeric, min:0 |
| `min_purchase` | nullable, numeric, min:0 |
| `starts_at` | nullable, date |
| `ends_at` | nullable, date, after:starts_at |
| `max_uses_per_customer` | nullable, integer, min:1 |
| `max_total_uses` | nullable, integer, min:1 |
| `is_active` | nullable, boolean |

Mensajes en español.

### Policy

`app/Policies/CouponPolicy.php` — tenant-scoped.

### Vistas

`resources/js/Pages/Coupons/`:

- `Index.vue` — tabla con stats header, toggle de activación, modal de eliminación, filtros `per_page`
- `Create.vue` — form completo
- `Edit.vue` — pre-poblado + contador de usos totales

### Entrada en sidebar

Icono `confirmation_number`, ubicado entre **Promociones** y **Pedidos**.

---

## API Pública

### `POST /api/coupons/validate`

**Propósito:** preview antes de crear el pedido (el cliente SPA la usa al escribir el código en `PaymentConfirmation.vue`).

Controller: `app/Http/Controllers/Api/CouponController.php` — `validate()`.

**Throttle:** 10 requests/min.

**Request:**

```json
{ "code": "BIENVENIDA", "subtotal": 250, "customer_phone": "5555555555" }
```

**Response válido:**

```json
{
  "valid": true,
  "discount_type": "percentage",
  "discount_value": 10,
  "max_discount": 50,
  "calculated_discount": 25
}
```

**Response inválido:**

```json
{ "valid": false, "reason": "min_purchase" }
```

El endpoint **enmascara razones** que revelan la existencia de un código (`not_found`, `expired`, `inactive`, `exhausted`) retornando un mensaje genérico. Solo expone razones accionables para el cliente: `min_purchase`, `max_uses_per_customer`.

**Nota:** esta validación es **no autoritativa**. La validación final ocurre dentro de la transacción de creación del pedido.

### `POST /api/orders` — integración

El payload de creación de pedido acepta opcionalmente:

```json
{ "coupon_code": "BIENVENIDA" }
```

El `OrderService` re-valida y calcula el descuento en el servidor, sin confiar en lo que envíe el cliente.

---

## OrderService — Integración con pedidos

`app/Services/OrderService.php`

**PASO 7c (líneas ~437–457):** si el request trae `coupon_code`, busca el cupón con `UPPER(code)` (case-insensitive), llama a `isValidForOrder($subtotal, $customerPhone)` **sin locks** para validación preliminar, y calcula el descuento.

**PASO 8 (líneas ~469–523) — dentro de la transacción:**

```php
DB::transaction(function () use (...) {
    // ... lockForUpdate() en el coupon y sus uses
    $result = $coupon->isValidForOrder($subtotal, $phone, lockUses: true);
    if (!$result['valid']) {
        throw ValidationException::withMessages(['coupon_code' => ...]);
    }
    $discount = $coupon->calculateDiscount($subtotal);

    // ... create order con coupon_id, coupon_code (snapshot), discount_amount
    // ... create CouponUse(coupon_id, order_id, customer_phone)
});
```

**Fórmula final:**

```
total = subtotal - discount_amount + delivery_cost
```

El `discount_amount` queda grabado en la orden como snapshot — si el cupón se edita/elimina después, la orden histórica no cambia.

---

## OrderEditService — Recálculo al editar

`app/Services/OrderEditService.php` (líneas ~192–223, 281–289)

Al editar los items de un pedido que tiene cupón aplicado:

- Si el nuevo subtotal **< `min_purchase`**: el cupón se **remueve**. Se nullifican `coupon_id`, `coupon_code`, `discount_amount=0`, se borra el `coupon_use` (libera el conteo), y se registra en `order_audits` con razón `coupon_removed`.
- Si el nuevo subtotal **≥ `min_purchase`**: se recalcula `discount_amount = coupon->calculateDiscount(subtotalNuevo)` y se actualiza.

El `delivery_cost` no se recalcula en edición de items (ver `03-orders.md` → edición post-creación).

---

## Cliente SPA

`client/src/stores/order.js`:

- Estado: `couponCode`, `couponDiscount` (persiste en `localStorage`)

`client/src/views/PaymentConfirmation.vue`:

- Sección "¿Tienes un cupón?" — input + botón "Aplicar"
- Al aplicar, llama a `POST /api/coupons/validate` con el subtotal actual
- Si válido, muestra `calculated_discount` y actualiza `order.couponDiscount`
- Al confirmar el pedido, envía `coupon_code` en el payload de `POST /api/orders`

`client/src/views/OrderConfirmed.vue`:

- Muestra en el resumen: `Descuento (BIENVENIDA): -$25.00`
- Incluye la línea de cupón en el mensaje de WhatsApp generado

---

## Factory

`database/factories/CouponFactory.php`

Estados:

- `fixed($value)` — cupón fijo
- `percentage($value, $maxDiscount)` — cupón porcentual con cap opcional
- `expired()` — `ends_at` en el pasado
- `inactive()` — `is_active = false`
- `future()` — `starts_at` en el futuro

---

## Tests

`tests/Feature/CouponTest.php` — **40 tests, 672 líneas**:

- **Admin CRUD:** crear fijo/porcentaje, uppercase automático, unicidad por restaurante, validación de fechas, toggle, delete, aislamiento por tenant
- **API `/validate`:** min_purchase, max_uses_per_customer, max_total_uses, lifecycle (expired/future/inactive/not_found), enmascaramiento de razones
- **Integración con `POST /api/orders`:** descuento aplicado correctamente, `CouponUse` registrado, re-validación atómica dentro de transacción, rechazo si se vuelve inválido en la carrera
- **Edición de órdenes:** recálculo al cambiar items, remoción automática si baja de `min_purchase`, no libera uso en cancelación

---

## Relación con otros módulos

- **`03-orders.md`** — `OrderService` y `OrderEditService` integran el cupón; contrato del payload de `POST /api/orders`.
- **`10-api.md`** — endpoint `POST /api/coupons/validate`.
- **`08-customer-flow.md`** — UI del cliente en `PaymentConfirmation` y `OrderConfirmed`.

---

_PideAqui / GuisoGo — Módulo 16: Cupones de Descuento — Marzo 2026_
