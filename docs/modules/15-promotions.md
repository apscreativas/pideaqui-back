# Módulo 15 — Promociones

> **Tipo:** Extensión del dominio de Menú
> **Estado:** Implementado (Mar 2026 — rediseño a productos standalone)
> **Pantallas de referencia:** sin mockup (post-MVP)

Las **promociones** en GuisoGo son **productos independientes con precio propio**, NO descuentos aplicados sobre productos existentes. Tienen su propio ciclo de vida (días activos, horario, imagen, modificadores) y se listan para el cliente como una categoría virtual llamada "Promociones" cuando están vigentes.

---

## Decisión de diseño

**Rediseño de marzo 2026:** la primera implementación trataba las promociones como descuentos vinculados a productos existentes (con `discount_type` + `discount_value` y una tabla pivote `promotion_product`). Se eliminó porque el flujo era confuso para los operadores y difícil de reportar en ganancias.

Hoy:

- Cada promoción es un **item de catálogo standalone** con `price`, `production_cost`, imagen propia y modificadores propios.
- En un `order_item`, **o viene `product_id` o viene `promotion_id`** — nunca ambos y nunca ninguno.
- El reporte de ganancias usa el mismo camino (snapshots `unit_price` / `production_cost` en `order_items`), por lo que la contabilidad es idéntica a los productos.

---

## Schema

### Tabla `promotions`

Migración: `database/migrations/2026_03_17_122043_create_promotions_table.php` + transformación en `2026_03_18_000001_transform_promotions_to_standalone.php`.

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | |
| `restaurant_id` | FK | `cascadeOnDelete` |
| `name` | string | |
| `description` | text | nullable |
| `price` | decimal(8,2) | agregado en el rediseño |
| `production_cost` | decimal(8,2) | agregado en el rediseño |
| `image_path` | string | nullable |
| `is_active` | boolean | default `false` |
| `active_days` | JSON | array de ints 0–6 (0=domingo) — ej. `[0,2,5]` |
| `starts_at` | time | nullable — soporta wrap-around (ej. 22:00–02:00) |
| `ends_at` | time | nullable |
| `sort_order` | integer | default `0` |
| `timestamps` | | |

**Eliminadas en el rediseño:** `discount_type`, `discount_value`, y la tabla pivote `promotion_product`.

### ALTER `order_items`

Migración: `2026_03_18_000002_add_promotion_id_to_order_items.php`.

- `product_id` → se vuelve **nullable**
- `promotion_id` → nueva FK nullable con `nullOnDelete`

Constraint en aplicación (no DB): un `order_item` tiene **exactamente** `product_id` **XOR** `promotion_id`.

---

## Modelo

`app/Models/Promotion.php`

**Fillable:** `restaurant_id, name, description, price, production_cost, image_path, is_active, active_days, starts_at, ends_at, sort_order`

**Casts:**

```php
'price' => 'decimal:2',
'production_cost' => 'decimal:2',
'is_active' => 'boolean',
'active_days' => 'array',
'sort_order' => 'integer',
```

**Appends:** `image_url` (computed — URL pública desde el disk de Storage)

**Relaciones:**

- `restaurant()` — `BelongsTo`
- `modifierGroups()` — `HasMany` (inline, per-promotion)
- `modifierGroupTemplates()` — `BelongsToMany` (catálogo reutilizable vía pivote `promotion_modifier_group_template`)

**Métodos:**

- `isCurrentlyActive(): bool` — valida:
  1. `is_active === true`
  2. El día actual está en `active_days`
  3. La hora actual está dentro de `[starts_at, ends_at]` (soporta horario que cruza medianoche)
  4. Si `starts_at` y `ends_at` son null → activa todo el día
- `getAllModifierGroups(): Collection` — mergea modificadores inline + catálogo; cada grupo se anota con `source: 'inline' | 'catalog'` (ver `04-menu.md` → Catálogo de Modificadores).

**Tenant:** usa `BelongsToTenant` — todas las queries desde admin filtran por `restaurant_id` del usuario autenticado.

---

## Admin — Panel del Restaurante

### Controller

`app/Http/Controllers/PromotionController.php`

| Ruta | Método | Endpoint | Acción |
| --- | --- | --- | --- |
| `promotions.index` | GET | `/promotions` | Lista con badges de vigencia |
| `promotions.create` | GET | `/promotions/create` | Form + templates de modifiers |
| `promotions.store` | POST | `/promotions` | Valida + guarda imagen + sincroniza modifiers |
| `promotions.edit` | GET | `/promotions/{promotion}/edit` | |
| `promotions.update` | PUT | `/promotions/{promotion}` | |
| `promotions.destroy` | DELETE | `/promotions/{promotion}` | Borra imagen del Storage |
| `promotions.toggle` | PATCH | `/promotions/{promotion}/toggle` | Flip `is_active` |
| `promotions.reorder` | PATCH | `/promotions/reorder` | Reordena por drag-and-drop |

Usa el trait `Concerns\SyncsModifierGroups` (compartido con `ProductController`) para manejar modifiers inline + catálogo.

### Form Requests

`StorePromotionRequest` y `UpdatePromotionRequest` (idénticos en reglas):

| Campo | Reglas |
| --- | --- |
| `name` | required, string, max:255 |
| `description` | nullable, string, max:2000 |
| `price` | required, numeric, min:0.01, max:99999.99 |
| `production_cost` | nullable, numeric, min:0, max:99999.99 |
| `image` | nullable, image, mimes:jpeg,jpg,png,gif,webp, max:5120 |
| `is_active` | boolean |
| `active_days` | required, array, min:1; items int 0–6 |
| `starts_at` | nullable, date_format:H:i |
| `ends_at` | nullable, date_format:H:i |
| `catalog_template_ids` | nullable, array; items `exists` tenant-scoped |
| `modifier_groups` | nullable, array (inline, misma forma que en productos) |

Mensajes en español.

### Policy

`app/Policies/PromotionPolicy.php` — tenant-scoped (`viewAny`, `view`, `create`, `update`, `delete`).

### Vistas

`resources/js/Pages/Promotions/`:

- `Index.vue` — tabla con drag-reorder, toggle, delete, badges (Vigente / Programada / Inactiva)
- `Create.vue` — form con image picker, campo `price`, grupos de modifiers inline + selector de templates del catálogo
- `Edit.vue` — idéntico a Create, pre-poblado

---

## API Pública (para el SPA del cliente)

`app/Http/Controllers/Api/MenuController.php` → `GET /api/menu`

Cuando existen promociones activas (`isCurrentlyActive() === true`), el endpoint retorna una **categoría virtual** llamada "Promociones" **como primer elemento** (flag `sort_order: -1`).

```json
{
  "categories": [
    {
      "id": "cat_promotions",
      "name": "Promociones",
      "is_promotion_category": true,
      "sort_order": -1,
      "products": [
        {
          "id": "promo_42",
          "promotion_id": 42,
          "is_promotion": true,
          "name": "Combo del día",
          "description": "...",
          "price": 70.00,
          "image_url": "https://.../storage/promotions/42.webp",
          "modifier_groups": [ ... ]
        }
      ]
    },
    { /* categorías normales de productos */ }
  ]
}
```

Notas:

- El `id` de la promoción se expone como string prefijado `promo_<id>` para no colisionar con IDs de productos en el cliente.
- `is_promotion: true` es la bandera que el cliente usa para construir el payload del pedido.
- La `price_adjustment` sigue funcionando igual en los modificadores (tanto inline como de catálogo).

---

## Cliente SPA

`client/src/stores/cart.js`:

- Al agregar un item con `product.is_promotion === true`, en vez de `product_id` se envía `promotion_id` en el payload de `POST /api/orders`.
- La detección de items equivalentes (para merge en carrito) usa el id prefijado `promo_42` — no colisiona con productos.

`client/src/components/ProductModal.vue` funciona idéntico para productos y promociones — reusa el mismo componente.

---

## OrderService — Validación y anti-tampering

`app/Services/OrderService.php` (PASO 4, líneas ~166–212):

1. Se hace **split de los items** del request en dos subsets: los que traen `product_id` y los que traen `promotion_id`.
2. Las promociones se cargan con `Promotion::query()->where('restaurant_id', $restaurant->id)->where('is_active', true)->whereIn('id', $promoIds)->with(['modifierGroups.options', 'modifierGroupTemplates.options'])->get()->filter(fn($p) => $p->isCurrentlyActive())`.
3. **Cada promoción debe pasar `isCurrentlyActive()`** en el momento del pedido — si ya no está vigente, se rechaza con 422.
4. Se normaliza cada item a una estructura unificada `{entity, owner_column, owner_id, product_id, promotion_id, ...}` de modo que el resto del flujo (validación de modifiers, anti-tampering, creación de OrderItem) no tenga que saber el tipo.
5. El **anti-tampering** valida `promo.price === item.unit_price` con tolerancia de ±$0.01.
6. Al persistir el `OrderItem` (línea ~541):

```php
'product_id' => $normalized['product_id'],   // null si es promo
'promotion_id' => $normalized['promotion_id'], // null si es producto
'product_name' => $normalized['entity']->name, // snapshot
'production_cost' => $normalized['entity']->production_cost, // snapshot
```

El snapshot asegura que cambios/eliminación posteriores de la promoción no afecten el reporte histórico de ganancias.

---

## Tests

`tests/Feature/PromotionTest.php` — **17 tests**:

| Grupo | Qué cubre |
| --- | --- |
| Admin CRUD | ver página, crear, crear con imagen, actualizar, eliminar, toggle, aislamiento por tenant |
| Validación | required fields, al menos un día activo |
| `isCurrentlyActive()` | en horario / fuera / día equivocado / deshabilitada / overnight / todo el día |
| API `/api/menu` | muestra categoría virtual cuando hay promos activas / la oculta si están fuera de horario / no rompe sin promos |

---

## Relación con otros módulos

- **`04-menu.md`** — comparte trait `SyncsModifierGroups` y el sistema de catálogo de modifiers.
- **`03-orders.md`** — `OrderService` y `OrderEditService` tratan `promotion_id` en paralelo a `product_id`.
- **`10-api.md`** — contrato de la categoría virtual "Promociones" en `/api/menu`.
- **`08-customer-flow.md`** — el SPA muestra la categoría virtual como primera tarjeta en la home.

---

_PideAqui / GuisoGo — Módulo 15: Promociones — Marzo 2026_
