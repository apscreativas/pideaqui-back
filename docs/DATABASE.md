# PideAqui — Esquema de Base de Datos

> PostgreSQL — Multitenancy por `restaurant_id` (row-level)
> 18 tablas del dominio + tablas de billing/Cashier + tablas de sistema Laravel

---

## Tablas del Dominio

### 1. `restaurants`

Entidad principal (tenant). Creada por el SuperAdmin.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `name` | varchar(255) | NOT NULL | Nombre del restaurante |
| `slug` | varchar(255) | UNIQUE, NOT NULL | Identificador URL amigable |
| `logo_path` | varchar(255) | NULLABLE | Ruta de imagen en cloud storage |
| `access_token` | varchar(255) | UNIQUE, NOT NULL | Token para autenticación de la API del cliente |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `orders_limit` | integer | NULLABLE | Límite de pedidos del periodo (config SuperAdmin) |
| `orders_limit_start` | date | NULLABLE | Inicio del periodo de límite |
| `orders_limit_end` | date | NULLABLE | Fin del periodo de límite |
| `max_branches` | integer | NOT NULL, DEFAULT 1 | Límite de sucursales (config SuperAdmin) |
| `allows_delivery` | boolean | NOT NULL, DEFAULT false | Permite entrega a domicilio |
| `allows_pickup` | boolean | NOT NULL, DEFAULT false | Permite recoger en sucursal |
| `allows_dine_in` | boolean | NOT NULL, DEFAULT false | Permite comer en sucursal |
| `notify_new_orders` | boolean | NOT NULL, DEFAULT false | Notificar nuevos pedidos por email |
| `instagram` | varchar(255) | NULLABLE | URL de Instagram del restaurante |
| `facebook` | varchar(255) | NULLABLE | URL de Facebook del restaurante |
| `tiktok` | varchar(255) | NULLABLE | URL de TikTok del restaurante |
| `plan_id` | bigint | NULLABLE, FK → plans | Plan de suscripción actual |
| `status` | varchar(255) | NOT NULL, DEFAULT 'active' | Estado billing: active, past_due, grace_period, suspended, canceled, incomplete, disabled |
| `grace_period_ends_at` | timestamp | NULLABLE | Fecha fin de periodo de gracia |
| `subscription_ends_at` | timestamp | NULLABLE | Fecha fin de suscripción (para cancelados) |
| `stripe_id` | varchar(255) | NULLABLE | Stripe Customer ID |
| `pm_type` | varchar(255) | NULLABLE | Tipo de método de pago Stripe |
| `pm_last_four` | varchar(4) | NULLABLE | Últimos 4 dígitos de tarjeta |
| `trial_ends_at` | timestamp | NULLABLE | Fin de trial (Cashier, no usado) |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `slug` (unique), `access_token` (unique), `stripe_id` (index)

**Relaciones:**
- Belongs to: `plans`
- Has many: `branches`, `categories`, `products`, `payment_methods`, `delivery_ranges`, `orders`, `restaurant_schedules`, `billing_audits`
- Has one: `subscription` (via Cashier Billable trait)
- Has many through: `orders` (via branches)

---

### 2. `branches`

Sucursales de un restaurante. Cada una tiene su propio WhatsApp y horarios.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `name` | varchar(255) | NOT NULL | Nombre descriptivo (ej. "Sucursal Centro") |
| `address` | text | NOT NULL | Dirección completa |
| `latitude` | decimal(10,8) | NOT NULL | Coordenada GPS |
| `longitude` | decimal(11,8) | NOT NULL | Coordenada GPS |
| `whatsapp` | varchar(20) | NOT NULL | Número WhatsApp (con código de país) |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `restaurant_id`, `(restaurant_id, is_active)`

**Relaciones:**
- Belongs to: `restaurant`
- Has many: `orders`

---

### 3. `restaurant_schedules`

Horarios de operación por día de la semana para cada restaurante (no por sucursal).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `day_of_week` | smallint | NOT NULL | 0=Domingo, 1=Lunes, ..., 6=Sábado |
| `opens_at` | time | NULLABLE | Hora de apertura (null si `is_closed`) |
| `closes_at` | time | NULLABLE | Hora de cierre (null si `is_closed`) |
| `is_closed` | boolean | NOT NULL, DEFAULT false | Si el día está cerrado |

**Índices:** `restaurant_id`, `(restaurant_id, day_of_week)` (unique)

**Sin timestamps.**

**Relaciones:**
- Belongs to: `restaurant`

---

### 3b. `restaurant_special_dates`

Fechas especiales (holidays o custom hours) que sobreescriben el horario regular del restaurante.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL, CASCADE | Tenant |
| `date` | date | NOT NULL | Fecha específica (ej. 2026-12-25) |
| `type` | enum('closed','special') | NOT NULL, DEFAULT 'closed' | closed = cerrado todo el día, special = horario diferente |
| `opens_at` | time | NULLABLE | Hora de apertura (solo para type=special) |
| `closes_at` | time | NULLABLE | Hora de cierre (solo para type=special) |
| `label` | varchar(255) | NULLABLE | Etiqueta descriptiva (ej. "Navidad") |
| `is_recurring` | boolean | NOT NULL, DEFAULT false | Si se repite cada año (match mes+día) |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Unique constraint:** `(restaurant_id, date)`

**Prioridad:** special_date > regular schedule. Si hay entry para hoy, SIEMPRE sobreescribe el horario del día de la semana.

**Relaciones:**
- Belongs to: `restaurant`

---

### 4. `categories`

Categorías del menú. El menú es global por restaurante (no por sucursal).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `name` | varchar(255) | NOT NULL | Nombre de la categoría |
| `description` | text | NULLABLE | Descripción opcional |
| `image_path` | varchar(255) | NULLABLE | Imagen de la categoría |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden de visualización |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `restaurant_id`, `(restaurant_id, sort_order)`

**Relaciones:**
- Belongs to: `restaurant`
- Has many: `products`

---

### 5. `products`

Productos del menú. Visibles en todas las sucursales del restaurante.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `category_id` | bigint | FK(categories), NOT NULL | |
| `name` | varchar(255) | NOT NULL | Nombre del producto |
| `description` | text | NULLABLE | Descripción del producto |
| `price` | decimal(10,2) | NOT NULL | Precio de venta |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción (solo visible para admin) |
| `image_path` | varchar(255) | NULLABLE | Imagen del producto |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `restaurant_id`, `category_id`, `(restaurant_id, is_active)`

**Relaciones:**
- Belongs to: `restaurant`, `category`
- Has many: `modifier_groups`

---

### 6. `modifier_groups`

Grupos de modificadores por producto (ej. "Tipo de tortilla", "Tamaño"). Cada grupo pertenece a un producto específico y se gestiona inline al crear/editar producto.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant (para BelongsToTenant) |
| `product_id` | bigint | FK(products), NOT NULL, CASCADE DELETE | Producto al que pertenece |
| `name` | varchar(255) | NOT NULL | Nombre del grupo (ej. "Tipo de tortilla") |
| `selection_type` | enum('single','multiple') | NOT NULL, DEFAULT 'single' | Selección única o múltiple |
| `is_required` | boolean | NOT NULL, DEFAULT false | Si el grupo es obligatorio |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden de visualización |

**Índices:** `restaurant_id`, `product_id`

**Relaciones:**
- Belongs to: `restaurant`, `product`
- Has many: `modifier_options`

---

### 7. `modifier_options`

Opciones dentro de un grupo de modificadores (ej. "Maíz $0", "Harina +$15").

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `modifier_group_id` | bigint | FK(modifier_groups), NOT NULL | |
| `name` | varchar(255) | NOT NULL | Nombre de la opción |
| `price_adjustment` | decimal(10,2) | NOT NULL, DEFAULT 0 | Ajuste de precio (+/-) |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción (solo visible para admin) |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden de visualización |

**Índices:** `modifier_group_id`

**Relaciones:**
- Belongs to: `modifier_group`

---

### 8. `payment_methods`

Métodos de pago configurados por restaurante.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `type` | enum('cash','terminal','transfer') | NOT NULL | Tipo de método |
| `is_active` | boolean | NOT NULL, DEFAULT false | Habilitado/deshabilitado |
| `bank_name` | varchar(255) | NULLABLE | Nombre del banco (solo para transfer) |
| `account_holder` | varchar(255) | NULLABLE | Titular de la cuenta (solo para transfer) |
| `clabe` | varchar(18) | NULLABLE | CLABE interbancaria (solo para transfer) |
| `alias` | varchar(255) | NULLABLE | Alias o alias (solo para transfer) |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `restaurant_id`, `(restaurant_id, type)` (unique)

**Reglas de negocio:**
- No se puede activar `transfer` sin tener `bank_name`, `account_holder` y `clabe` configurados.
- No se puede desactivar el último método de pago activo de un restaurante (siempre debe haber al menos uno).

**Relaciones:**
- Belongs to: `restaurant`

---

### 9. `delivery_ranges`

Rangos de distancia con precio fijo de envío, configurados a nivel de restaurante.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `min_km` | decimal(5,2) | NOT NULL | Distancia mínima del rango (km) |
| `max_km` | decimal(5,2) | NOT NULL | Distancia máxima del rango (km) |
| `price` | decimal(10,2) | NOT NULL | Precio fijo de envío para este rango |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden (coincide con min_km habitualmente) |

**Índices:** `restaurant_id`, `(restaurant_id, sort_order)`

**Regla de negocio:** Los rangos deben ser contiguos y sin huecos. El `max_km` del último rango define la cobertura máxima del restaurante.

**Relaciones:**
- Belongs to: `restaurant`

---

### 10. `customers`

Clientes que han realizado pedidos. Identificados por token de cookie (sin registro).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `token` | varchar(255) | UNIQUE, NOT NULL | Token identificador (guardado en cookie 90 días) |
| `name` | varchar(255) | NOT NULL | Nombre del cliente |
| `phone` | varchar(20) | NOT NULL | Teléfono del cliente |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `token` (unique)

**Relaciones:**
- Has many: `orders`

---

### 11. `orders`

Pedidos realizados por clientes.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `branch_id` | bigint | FK(branches), NOT NULL | Sucursal asignada |
| `customer_id` | bigint | FK(customers), NOT NULL | |
| `delivery_type` | enum('delivery','pickup','dine_in') | NOT NULL | Tipo de entrega |
| `status` | enum('received','preparing','on_the_way','delivered','cancelled') | NOT NULL, DEFAULT 'received' | Estatus del pedido |
| `source` | varchar(16) | NOT NULL, DEFAULT 'api' | Origen del pedido: `api` (cliente SPA) o `manual` (admin desde Tablero) |
| `scheduled_at` | timestamp | NULLABLE | Hora programada (null = lo antes posible) |
| `subtotal` | decimal(10,2) | NOT NULL | Suma de productos + modificadores |
| `delivery_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de envío |
| `total` | decimal(10,2) | NOT NULL | Subtotal + delivery_cost |
| `payment_method` | enum('cash','terminal','transfer') | NOT NULL | Método de pago seleccionado |
| `cash_amount` | decimal(10,2) | NULLABLE | Monto con el que paga el cliente (solo para cash) |
| `distance_km` | decimal(6,2) | NULLABLE | Distancia real por calles (Google Distance Matrix) |
| `address_street` | varchar(255) | NULLABLE | Calle de la dirección de entrega |
| `address_number` | varchar(50) | NULLABLE | Número exterior/interior |
| `address_colony` | varchar(255) | NULLABLE | Colonia |
| `address_references` | text | NULLABLE | Referencias adicionales |
| `latitude` | decimal(10,8) | NULLABLE | Coordenadas del cliente (del pin del mapa) |
| `longitude` | decimal(11,8) | NULLABLE | Coordenadas del cliente (del pin del mapa) |
| `cancellation_reason` | text | NULLABLE | Motivo de cancelación |
| `cancelled_at` | timestamp | NULLABLE | Fecha/hora de cancelación |
| `edited_at` | timestamp | NULLABLE | Última fecha de edición |
| `edit_count` | unsigned int | NOT NULL, DEFAULT 0 | Número de veces que el pedido fue editado |
| `coupon_id` | bigint | FK(coupons), NULLABLE, NULL ON DELETE | Cupón aplicado (si lo hubo) |
| `coupon_code` | varchar(255) | NULLABLE | Snapshot del código al momento del pedido |
| `discount_amount` | decimal(10,2) | NOT NULL, DEFAULT 0 | Descuento aplicado al subtotal |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `restaurant_id`, `branch_id`, `customer_id`, `(restaurant_id, status)`, `(restaurant_id, created_at)`, `(restaurant_id, cancelled_at)`

**Regla de negocio:** El estatus solo puede avanzar hacia adelante (no se puede revertir). Edición permitida solo en `received` o `preparing`.

**Fórmula de total:** `total = subtotal - discount_amount + delivery_cost` (el cupón nunca descuenta el envío).

**Relaciones:**
- Belongs to: `restaurant`, `branch`, `customer`, `coupon`
- Has many: `order_items`, `order_audits`, `coupon_uses`

---

### 12. `order_items`

Productos incluidos en un pedido.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `order_id` | bigint | FK(orders), NOT NULL | |
| `product_id` | bigint | FK(products), NULLABLE | Referencia a producto (mutuamente excluyente con `promotion_id`) |
| `promotion_id` | bigint | FK(promotions), NULLABLE, NULL ON DELETE | Referencia a promoción (mutuamente excluyente con `product_id`) |
| `product_name` | varchar(255) | NOT NULL | Nombre del ítem (snapshot — sirve tanto para productos como para promociones) |
| `quantity` | integer | NOT NULL, DEFAULT 1 | Cantidad |
| `unit_price` | decimal(10,2) | NOT NULL | Precio unitario al momento del pedido |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción (snapshot al momento del pedido) |
| `notes` | text | NULLABLE | Notas libres del cliente (sin costo) |

**Regla de integridad (aplicada en aplicación, no DB):** cada fila tiene EXACTAMENTE `product_id` XOR `promotion_id`. Nunca ambos, nunca ninguno.

**Índices:** `order_id`, `product_id`, `promotion_id`

**Relaciones:**
- Belongs to: `order`, `product` (nullable), `promotion` (nullable)
- Has many: `order_item_modifiers`

---

### 13. `order_item_modifiers`

Modificadores seleccionados para cada ítem de pedido.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `order_item_id` | bigint | FK(order_items), NOT NULL | |
| `modifier_option_id` | bigint | FK(modifier_options), NOT NULL | |
| `modifier_option_name` | varchar(255) | NOT NULL | Nombre de la opción (snapshot al momento del pedido) |
| `price_adjustment` | decimal(10,2) | NOT NULL, DEFAULT 0 | Ajuste de precio registrado al momento del pedido |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción (snapshot al momento del pedido) |

**Índices:** `order_item_id`

**Relaciones:**
- Belongs to: `order_item`, `modifier_option`

---

### 14. `order_audits`

Historial de ediciones realizadas a pedidos después de su creación.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `order_id` | bigint | FK(orders), CASCADE | Pedido editado |
| `user_id` | bigint | FK(users), NULLABLE, SET NULL ON DELETE | Admin que realizó la edición |
| `action` | varchar(30) | NOT NULL | Tipo de cambio: `items_modified`, `address_modified`, `location_modified`, `payment_method_changed` |
| `changes` | json | NOT NULL | Diff estructurado (added/removed/modified para items, old/new para campos) |
| `reason` | text | NULLABLE | Motivo del cambio (opcional, ingresado por el admin) |
| `old_total` | decimal(10,2) | NULLABLE | Total antes de la edición |
| `new_total` | decimal(10,2) | NULLABLE | Total después de la edición |
| `ip_address` | varchar(45) | NULLABLE | IP del admin que realizó el cambio |
| `created_at` | timestamp | useCurrent | |

**Índices:** `(order_id, created_at)`

**Relaciones:**
- Belongs to: `order`, `user`

---

### 15. `modifier_group_templates`

Catálogo de grupos de modificadores reutilizables a nivel restaurante. Se vinculan a productos via pivote many-to-many.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL, CASCADE | Tenant |
| `name` | varchar(255) | NOT NULL | Nombre del grupo template |
| `selection_type` | enum('single','multiple') | NOT NULL, DEFAULT 'single' | Tipo de selección |
| `is_required` | boolean | NOT NULL, DEFAULT false | Si es obligatorio |
| `max_selections` | unsigned int | NULLABLE | Máximo de selecciones (solo para multiple) |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden de visualización |

**Sin timestamps.**

**Relaciones:**
- Belongs to: `restaurant`
- Has many: `modifier_option_templates`
- Belongs to many: `products` (via `product_modifier_group_template`)

---

### 16. `modifier_option_templates`

Opciones dentro de un grupo template del catálogo.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `modifier_group_template_id` | bigint | FK(modifier_group_templates), NOT NULL, CASCADE | |
| `name` | varchar(255) | NOT NULL | Nombre de la opción |
| `price_adjustment` | decimal(10,2) | NOT NULL, DEFAULT 0 | Ajuste de precio |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción (solo admin) |
| `is_active` | boolean | NOT NULL, DEFAULT true | Estado activo/inactivo |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden |

**Sin timestamps.**

**Relaciones:**
- Belongs to: `modifier_group_template`

---

### 17. `product_modifier_group_template` (pivote)

Vincula productos con grupos template del catálogo (many-to-many).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `product_id` | bigint | FK(products), NOT NULL, CASCADE | |
| `modifier_group_template_id` | bigint | FK(modifier_group_templates), NOT NULL, CASCADE | |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | Orden por producto |

**Unique constraint:** `(product_id, modifier_group_template_id)`

---

### Columnas agregadas a tablas existentes

- `modifier_groups`: `is_active` (bool, default true), `max_selections` (uint, nullable)
- `modifier_options`: `is_active` (bool, default true)
- `orders`: `source`, `edited_at`, `edit_count`, `coupon_id`, `coupon_code`, `discount_amount`
- `order_items`: `promotion_id` (nullable), `product_id` ahora es nullable

---

## Tablas de Promociones

### `promotions`

Promociones standalone — son productos independientes con su propio precio y costo, no descuentos sobre productos existentes. Ver [docs/modules/15-promotions.md](./modules/15-promotions.md).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL, CASCADE | Tenant |
| `name` | varchar(255) | NOT NULL | |
| `description` | text | NULLABLE | |
| `price` | decimal(10,2) | NOT NULL | Precio de la promoción (no es descuento) |
| `production_cost` | decimal(10,2) | NOT NULL, DEFAULT 0 | Costo de producción |
| `image_path` | varchar(255) | NULLABLE | Imagen en Storage |
| `is_active` | boolean | NOT NULL, DEFAULT false | |
| `active_days` | json | NOT NULL | Array de ints 0–6 (0=domingo) |
| `starts_at` | time | NULLABLE | Hora de inicio (soporta wrap-around) |
| `ends_at` | time | NULLABLE | Hora de fin |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | |
| `created_at`, `updated_at` | timestamp | | |

**Índices:** `restaurant_id`, `(restaurant_id, is_active)`

**Relaciones:**
- Belongs to: `restaurant`
- Has many: `modifier_groups` (inline, per-promotion)
- Belongs to many: `modifier_group_templates` (via `promotion_modifier_group_template`)
- Has many: `order_items` (via `promotion_id`)

---

### `promotion_modifier_group_template` (pivote)

Vincula promociones con templates del catálogo de modifiers.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `promotion_id` | bigint | FK(promotions), CASCADE | |
| `modifier_group_template_id` | bigint | FK(modifier_group_templates), CASCADE | |
| `sort_order` | integer | NOT NULL, DEFAULT 0 | |

**Unique constraint:** `(promotion_id, modifier_group_template_id)`

---

## Tablas de Cupones

### `coupons`

Cupones de descuento por restaurante. Ver [docs/modules/16-coupons.md](./modules/16-coupons.md).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL, CASCADE | Tenant |
| `code` | varchar(20) | NOT NULL | Código (UPPERCASE, unique por restaurante) |
| `discount_type` | enum('fixed','percentage') | NOT NULL | |
| `discount_value` | decimal(10,2) | NOT NULL | Monto MXN o % según tipo |
| `max_discount` | decimal(10,2) | NULLABLE | Cap para porcentajes |
| `min_purchase` | decimal(10,2) | NULLABLE | Subtotal mínimo requerido |
| `starts_at` | timestamp | NULLABLE | Inicio de vigencia |
| `ends_at` | timestamp | NULLABLE | Fin de vigencia |
| `max_uses_per_customer` | unsigned int | NULLABLE | Límite por customer_phone |
| `max_total_uses` | unsigned int | NULLABLE | Límite global del cupón |
| `is_active` | boolean | NOT NULL, DEFAULT true | |
| `created_at`, `updated_at` | timestamp | | |

**Índice único:** `(restaurant_id, code)` — dos restaurantes pueden tener el mismo código sin colisión.

**Reglas:**
- El descuento aplica sólo al `subtotal` del pedido, nunca al `delivery_cost`.
- Un cupón por pedido máximo.
- Tracking por `customer_phone` (no hay cuenta de usuario).

**Relaciones:**
- Belongs to: `restaurant`
- Has many: `coupon_uses`, `orders` (via `coupon_id`)

---

### `coupon_uses`

Registro inmutable de cada uso exitoso de un cupón.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `coupon_id` | bigint | FK(coupons), CASCADE | |
| `order_id` | bigint | FK(orders), CASCADE | |
| `customer_phone` | varchar(255) | NOT NULL | Teléfono del cliente que lo usó |
| `created_at` | timestamp | NOT NULL | **Sin `updated_at`** |

**Índice:** `(coupon_id, customer_phone)` — para validación eficiente de `max_uses_per_customer`.

**Reglas:**
- Las órdenes canceladas NO liberan el uso — el registro persiste.
- Si se edita una orden y el cupón se vuelve inválido (`subtotal < min_purchase`), el `coupon_use` SÍ se elimina (libera el conteo).

---

## Tablas de Billing / Suscripciones

### 18. `plans`

Catálogo de planes de suscripción. Incluye un plan de gracia para restaurantes nuevos.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `name` | varchar(255) | NOT NULL | Nombre del plan |
| `slug` | varchar(255) | UNIQUE, NOT NULL | Identificador URL |
| `description` | text | NULLABLE | Descripción del plan |
| `orders_limit` | unsigned int | NOT NULL | Pedidos máximos por periodo |
| `max_branches` | unsigned int | NOT NULL | Sucursales permitidas |
| `monthly_price` | decimal(10,2) | NOT NULL | Precio mensual MXN |
| `yearly_price` | decimal(10,2) | NOT NULL | Precio anual MXN |
| `stripe_product_id` | varchar(255) | NULLABLE | ID de Product en Stripe |
| `stripe_monthly_price_id` | varchar(255) | NULLABLE | ID de Price mensual en Stripe |
| `stripe_yearly_price_id` | varchar(255) | NULLABLE | ID de Price anual en Stripe |
| `is_default_grace` | boolean | NOT NULL, DEFAULT false | Plan de gracia (solo uno) |
| `is_active` | boolean | NOT NULL, DEFAULT true | Disponible para nuevas suscripciones |
| `sort_order` | unsigned int | NOT NULL, DEFAULT 0 | Orden en pricing |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

**Índices:** `slug` (unique)

**Relaciones:**
- Has many: `restaurants`

---

### 19. `billing_settings`

Configuración global de billing (key-value). Gestionada por el SuperAdmin.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `key` | varchar(255) | UNIQUE, NOT NULL | Nombre de la configuración |
| `value` | varchar(255) | NOT NULL | Valor |
| `created_at` | timestamp | NULLABLE | |
| `updated_at` | timestamp | NULLABLE | |

Keys: `initial_grace_period_days`, `payment_grace_period_days`, `reminder_days_before_expiry`

---

### 20. `billing_audits`

Registro de todas las acciones de billing y cambios de suscripción.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK, auto-increment | |
| `restaurant_id` | bigint | FK(restaurants), NULLABLE, NULL ON DELETE | |
| `actor_type` | varchar(255) | NOT NULL | 'super_admin', 'restaurant_admin', 'system', 'stripe' |
| `actor_id` | bigint | NULLABLE | ID del actor |
| `action` | varchar(255) | NOT NULL | Tipo de acción |
| `payload` | jsonb | NULLABLE | Detalle del cambio |
| `ip_address` | varchar(255) | NULLABLE | |
| `created_at` | timestamp | NULLABLE | Solo created_at |

Acciones: `restaurant_created`, `plan_changed`, `subscription_started`, `subscription_canceled`, `payment_succeeded`, `payment_failed`, `grace_period_started`, `grace_period_extended`, `suspended`, `reactivated`, `disabled`, `enabled`

**Relaciones:**
- Belongs to: `restaurant`

---

### 21-22. `subscriptions` / `subscription_items` (Laravel Cashier)

Tablas gestionadas automáticamente por Laravel Cashier. Almacenan el estado de las suscripciones de Stripe.

> ⚠️ En este proyecto, `subscriptions.user_id` apunta a **`restaurants.id`** (no a `users.id`). El `Billable` de Cashier es el `Restaurant`, no el `User`, porque un restaurante puede tener varios admins.

Columnas adicionales (migración `2026_04_06_082039_add_billing_period_to_subscriptions_table.php`):
- `current_period_start` (timestamp nullable)
- `current_period_end` (timestamp nullable)

---

### 23. `stripe_webhook_events`

Deduplicación de webhooks de Stripe. Si el mismo evento llega dos veces (reintento de Stripe), el segundo se ignora con HTTP 200.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `stripe_event_id` | varchar(255) | UNIQUE, NOT NULL | `evt_XXXXXX` de Stripe |
| `type` | varchar(255) | NOT NULL | `invoice.paid`, `customer.subscription.deleted`, etc. |
| `processed_at` | timestamp | NULLABLE | Marcada al finalizar el procesamiento exitoso |
| `created_at`, `updated_at` | timestamp | | |

**Patrón:** `insertOrIgnore()` antes de procesar — si retorna 0, es duplicado.

---

## Tablas de Sistema Laravel (existentes)

| Tabla | Descripción |
|---|---|
| `users` | Admins de restaurante (guard `web`) |
| `sessions` | Sesiones de usuario |
| `cache`, `cache_locks` | Cache del sistema |
| `jobs`, `job_batches`, `failed_jobs` | Cola de trabajos |

> La tabla `users` se extenderá para agregar la relación con `restaurants` (columna `restaurant_id` o tabla pivot).

---

## Diagrama de Relaciones (resumen)

```
plans ──< restaurants (1:N)
restaurants
├── plan (N:1, via plan_id)
├── pending_plan (N:1, via pending_plan_id)
├── subscription (1:1, Cashier Billable — subscriptions.user_id = restaurants.id)
├── billing_audits (1:N)
├── branches (1:N)
├── restaurant_schedules (1:N)
├── restaurant_special_dates (1:N)
├── categories (1:N)
│   └── products (1:N)
│       ├── modifier_groups (1:N, inline per-product)
│       │   └── modifier_options (1:N)
│       └── product_modifier_group_template (N:M, catalog)
│           └── modifier_group_templates (N:M)
│               └── modifier_option_templates (1:N)
├── promotions (1:N)
│   ├── modifier_groups (1:N, inline per-promotion)
│   │   └── modifier_options (1:N)
│   └── promotion_modifier_group_template (N:M, catalog)
├── modifier_group_templates (1:N, catalog)
│   └── modifier_option_templates (1:N)
├── payment_methods (1:N)
├── delivery_ranges (1:N)
├── coupons (1:N)
│   └── coupon_uses (1:N, → orders)
└── orders (1:N)
    ├── order_items (1:N, product_id XOR promotion_id)
    │   └── order_item_modifiers (1:N)
    ├── order_audits (1:N)
    ├── coupon (N:1, nullable)
    └── customers (N:1)

# Infraestructura de billing (global, no tenant)
stripe_webhook_events (dedup global)
billing_settings (key-value global)

# POS (módulo independiente de orders)
restaurants (1:N)
├── pos_sales (1:N)               cashier_user → users
│   ├── pos_sale_items (1:N)      → products (snapshot)
│   │   └── pos_sale_item_modifiers (1:N)  → modifier_options OR modifier_option_templates
│   └── pos_payments (1:N)        registered_by → users
└── branches (1:N)                pos_sales.branch_id
```

---

## Tablas del módulo POS (caja rápida)

### 14. `pos_sales`

Ventas en mostrador. **No comparten tabla con `orders`** por decisión arquitectónica explícita.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `restaurant_id` | bigint | FK(restaurants), NOT NULL | Tenant |
| `branch_id` | bigint | FK(branches), NOT NULL | Sucursal donde se vende |
| `cashier_user_id` | bigint | FK(users), NOT NULL | Cajero que crea la venta |
| `ticket_number` | varchar(32) | NOT NULL | Formato `POS-NNNN`, unique por restaurante |
| `status` | enum('preparing','ready','paid','cancelled') | NOT NULL, DEFAULT 'preparing' | State machine simplificado |
| `subtotal` | decimal(10,2) | NOT NULL | Suma de items + modifiers |
| `total` | decimal(10,2) | NOT NULL | = subtotal (sin envío, sin descuento) |
| `notes` | text | NULLABLE | Nota general del ticket |
| `cancellation_reason` | text | NULLABLE | |
| `cancelled_at` | timestamp | NULLABLE | |
| `prepared_at` | timestamp | NULLABLE | Cuando cocina marcó `ready` |
| `paid_at` | timestamp | NULLABLE | Cuando se cerró el cobro |
| `created_at`, `updated_at` | timestamp | | |

**Índices:** unique `(restaurant_id, ticket_number)`, `(restaurant_id, status, created_at)`, `(branch_id, status)`.

### 15. `pos_sale_items`

Items de una venta POS.

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `pos_sale_id` | bigint | FK(pos_sales) cascade | |
| `product_id` | bigint | FK(products), NOT NULL | Solo `product_id`, NUNCA `promotion_id` |
| `product_name` | string | NOT NULL | Snapshot |
| `quantity` | unsignedInt | NOT NULL | |
| `unit_price` | decimal(10,2) | NOT NULL | Snapshot del precio configurado |
| `production_cost` | decimal(10,2) | DEFAULT 0 | Snapshot para cálculo de profit |
| `notes` | text | NULLABLE | Nota por item |

### 16. `pos_sale_item_modifiers`

Modifiers seleccionados por item (inline o catálogo, mismo set que orders).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `pos_sale_item_id` | bigint | FK(pos_sale_items) cascade | |
| `modifier_option_id` | bigint | FK(modifier_options), NULLABLE | Inline |
| `modifier_option_template_id` | bigint | FK(modifier_option_templates), NULLABLE | Catálogo |
| `modifier_option_name` | string | NOT NULL | Snapshot |
| `price_adjustment` | decimal(10,2) | NOT NULL | Snapshot |
| `production_cost` | decimal(10,2) | DEFAULT 0 | Snapshot |

**CHECK constraint** `(modifier_option_id IS NULL) XOR (modifier_option_template_id IS NULL)` — exactamente uno por fila.

### 17. `pos_payments`

Splits de cobro (pagos mixtos).

| Columna | Tipo | Constraints | Descripción |
|---|---|---|---|
| `id` | bigint | PK | |
| `pos_sale_id` | bigint | FK(pos_sales) cascade | |
| `payment_method_type` | enum('cash','terminal','transfer') | NOT NULL | |
| `amount` | decimal(10,2) | NOT NULL | Lo que cubre del total |
| `cash_received` | decimal(10,2) | NULLABLE | Solo cash, lo entregado por el cliente |
| `change_given` | decimal(10,2) | NULLABLE | `cash_received − amount` |
| `registered_by_user_id` | bigint | FK(users), NOT NULL | Cajero que registró |
| `created_at` | timestamp | DEFAULT now | Sin updated_at (inmutable) |

---

_PideAqui — Esquema de Base de Datos v1.7 — Abril 2026 — POS module_
