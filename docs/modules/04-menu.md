# Módulo 04 — Gestión de Menú

> Pantallas de referencia: `ar_07_menu_management_accordion`, `ar_08_product_creation_editing`, `ar_09_category_editor_modal`, `ar_10_modifiers_management`

---

## Descripción General

Módulo para crear y administrar el menú del restaurante: categorías, productos y grupos de modificadores. El menú es **global por restaurante** — se comparte entre todas las sucursales. No existe configuración de menú por sucursal.

El menú configurado aquí es el que se muestra en el frontend del cliente (SPA), servido por la API pública.

---

## Pantallas

### `ar_07` — Gestión del Menú (`/menu`)

Vista en acordeón: lista de categorías expandibles, cada una mostrando sus productos.

**Por categoría:**
- Toggle activo/inactivo (activa/desactiva toda la categoría y sus productos en el frontend del cliente).
- Botón editar categoría (abre modal `ar_09`).
- Botón eliminar (solo si no tiene productos activos).
- Drag-and-drop para reordenar categorías (campo `sort_order`).
- Botón "+ Agregar categoría".

**Por producto (dentro de la categoría):**
- Foto miniatura, nombre, precio, badge de activo/inactivo.
- Toggle activo/inactivo individual.
- Botón editar (abre formulario `ar_08`).
- Botón eliminar.
- Drag-and-drop para reordenar productos dentro de la categoría.

### `ar_09` — Editor de Categoría (modal)

Modal deslizante sobre la pantalla `ar_07`.

**Campos:**
- Nombre de la categoría (requerido).
- Descripción (opcional).
- Imagen de la categoría (upload a cloud storage).
- Estado activo/inactivo.
- Nota: `sort_order` se autoasigna al crear (max + 1) y se reordena por drag-and-drop en la vista principal. No hay campo manual.

### `ar_08` — Crear / Editar Producto (`/menu/products/create` o `/menu/products/{id}/edit`)

Formulario completo en pantalla completa (no modal).

**Sección básica:**
- Nombre del producto (requerido).
- Descripción (opcional).
- Precio de venta base (requerido, decimal).
- **Costo de producción** (requerido, decimal) — campo **solo visible para el admin**, nunca expuesto al cliente ni a la API pública.
- Categoría (selector del restaurante actual).
- Foto del producto (upload a cloud storage).
- Estado activo/inactivo.

**Sección de modificadores (inline):**
- Formulario inline dentro del producto para crear y gestionar grupos de modificadores.
- Botón "Agregar grupo" para crear un nuevo grupo.
- Cada grupo tiene: nombre, tipo de selección (única/múltiple select), toggle obligatorio, botón eliminar.
- Dentro de cada grupo: lista de opciones con nombre, precio adicional ($), costo de producción ($).
- Botón "Agregar opción" dentro de cada grupo.
- Botón eliminar por opción (mínimo 1 opción por grupo).
- Al guardar el producto, se sincronizan los grupos y opciones: existentes se actualizan, nuevos se crean, eliminados se borran.

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `Category` | `categories` | Categorías del menú |
| `Product` | `products` | Productos del menú |
| `ModifierGroup` | `modifier_groups` | Grupos de modificadores por producto |
| `ModifierOption` | `modifier_options` | Opciones dentro de cada grupo |

### Estructura de datos de Modificadores

```
product (HasMany)
└── modifier_groups[]
    ├── name: "Tipo de tortilla"
    ├── selection_type: "single"
    ├── is_required: true
    └── modifier_options[]
        ├── { name: "Maíz", price_adjustment: 0, production_cost: 1.50 }
        └── { name: "Harina", price_adjustment: 15.00, production_cost: 2.00 }
```

El precio final de un producto en el carrito es:
```
precio_final = product.price + Σ modifier_option.price_adjustment (de las opciones seleccionadas)
```

---

## Reglas de Negocio

- **El menú es global por restaurante.** Todas las sucursales comparten el mismo menú. No se puede configurar un menú diferente por sucursal.
- Un producto desactivado (`is_active = false`) **no aparece** en el frontend del cliente. Una categoría desactivada oculta todos sus productos, independientemente del estado individual de cada producto.
- Un grupo de modificadores **pertenece a un producto** (relación HasMany). No se comparten entre productos.
- Si se elimina un `ModifierGroup` que tiene opciones seleccionadas en pedidos pasados, los datos del pedido se preservan en `order_item_modifiers` (snapshot del precio al momento del pedido). No se eliminan en cascada.
- El `production_cost` es **obligatorio** al crear o editar un producto. Nunca se devuelve en la API pública.
- Las categorías y productos se pueden **reordenar** mediante drag-and-drop (HTML5 DnD nativo) en la vista del menú. El `sort_order` se autoasigna al crear (`max + 1` dentro del scope) y se actualiza vía endpoints `PATCH reorder`. La UI usa optimistic updates con una `localCategories` ref para respuesta inmediata.
- Los productos solo se pueden reordenar **dentro de su misma categoría**.
- El campo manual "Orden de visualización" fue eliminado de CategoryModal, Products/Create y Products/Edit.
- No se puede eliminar una categoría que tenga productos activos.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[10-api.md](./10-api.md)** | El endpoint `GET /api/menu` devuelve las categorías y productos activos al SPA del cliente. `production_cost` se excluye siempre de esta respuesta. |
| **[08-customer-flow.md](./08-customer-flow.md)** | El cliente navega el menú en `c_01` y `c_02`. Lo que se muestra depende de lo configurado aquí. |
| **[03-orders.md](./03-orders.md)** | El `production_cost` de los productos se usa en el dashboard para calcular la ganancia neta. Los `order_item_modifiers` registran el `price_adjustment` al momento del pedido. |
| **[02-dashboard.md](./02-dashboard.md)** | La ganancia neta del dashboard se calcula usando `product.production_cost` vs. precio de venta. |

---

## Implementación Backend

```
Routes (admin panel):
  GET    /menu                           → MenuController@index
  GET    /menu/products/create           → ProductController@create
  POST   /menu/products                  → ProductController@store
  GET    /menu/products/{id}/edit        → ProductController@edit
  PUT    /menu/products/{id}             → ProductController@update
  DELETE /menu/products/{id}             → ProductController@destroy
  PATCH  /menu/products/{id}/toggle      → ProductController@toggle
  POST   /menu/categories                → CategoryController@store
  PUT    /menu/categories/{id}           → CategoryController@update
  DELETE /menu/categories/{id}           → CategoryController@destroy
  PATCH  /menu/categories/reorder        → CategoryController@reorder
  PATCH  /menu/products/reorder          → ProductController@reorder

Form Requests:
  StoreCategoryRequest, UpdateCategoryRequest
  StoreProductRequest, UpdateProductRequest
  → Modifier groups y opciones se validan inline en StoreProductRequest/UpdateProductRequest

Policy:
  CategoryPolicy, ProductPolicy
  → Verifican que restaurant_id del recurso === restaurant_id del admin autenticado

Servicio:
  ImageUploadService → sube imágenes de productos/categorías a cloud storage (S3 o compatible)
```

---

## Notas de Diseño

### Gestión del Menú (ar_07)
- Acordeón: cada categoría es un encabezado expandible. Los productos se muestran en grid de 2 columnas dentro.
- Toggle switch (pill style) en naranja cuando está activo.
- Imagen del producto en miniatura cuadrada (48px) a la izquierda de cada fila de producto.
- Drag handle (`drag_indicator`) a la izquierda para reordenar.

### Editor de Producto (ar_08)
- Formulario largo con tres secciones: "Información básica", "Precios y Costos", "Modificadores".
- El campo **Costo de producción** tiene una nota visual discreta: "Solo visible para administradores".
- Upload de imagen con preview.
- Modificadores inline: cada grupo es una tarjeta con opciones (nombre, $ precio, $ costo producción).

### Editor de Categoría (ar_09)
- Modal lateral (slide-over) desde la derecha.
- Campos compactos. Preview de imagen arriba.

---

## Catálogo de Modificadores Reutilizables

> **Agregado Mar 2026** — sistema híbrido: coexiste con los modificadores inline descritos arriba.

Hasta marzo de 2026, cada producto tenía sus modificadores **inline** (relación `HasMany` directa: `modifier_groups.product_id → products.id`). Funciona bien cuando los modificadores son únicos por producto, pero se vuelve tedioso cuando los mismos grupos se repiten en muchos items (ej. "Tamaño", "Término", "Sin ingredientes").

La solución fue agregar un **catálogo a nivel restaurante** sin eliminar los modificadores inline — ambos conviven y pueden mezclarse libremente en un mismo producto o promoción.

### Decisión de diseño

- **Los modificadores inline siguen funcionando igual.** No hay deprecación ni migración forzada de datos.
- El catálogo es **opcional** — los restaurantes con pocos productos pueden ignorarlo.
- La estructura de `order_item_modifiers` **no cambió**. Un modifier del catálogo se guarda con `modifier_option_id = null` + snapshot de nombre/precio.
- El patrón es idéntico al de productos vs promociones: el order item tiene o un ID o el otro.

### Schema

**Nueva tabla `modifier_group_templates`** (migración `2026_03_23_143001_create_modifier_catalog_tables.php`):

| Columna | Tipo | Notas |
| --- | --- | --- |
| `restaurant_id` | FK | |
| `name` | string | |
| `selection_type` | enum | `single` \| `multiple` |
| `is_required` | boolean | |
| `max_selections` | int nullable | sólo para `multiple` |
| `is_active` | boolean | |
| `sort_order` | int | |
| `timestamps` | | |

**Nueva tabla `modifier_option_templates`:**

| Columna | Tipo | Notas |
| --- | --- | --- |
| `modifier_group_template_id` | FK | |
| `name` | string | |
| `price_adjustment` | decimal | puede ser negativo |
| `production_cost` | decimal | |
| `is_active` | boolean | |
| `sort_order` | int | |
| `timestamps` | | |

**Pivote `product_modifier_group_template`** — relaciona productos con templates del catálogo:

| Columna | Tipo | Notas |
| --- | --- | --- |
| `product_id` | FK | |
| `modifier_group_template_id` | FK | |
| `sort_order` | int | orden dentro del producto |

Existe una pivote equivalente `promotion_modifier_group_template` para que las promociones también puedan usar el catálogo.

**ALTER `modifier_groups` y `modifier_options`** (misma migración del mismo timestamp):

- `is_active` — boolean
- `max_selections` — int nullable (sólo para `selection_type='multiple'`)

### Modelos

- **`app/Models/ModifierGroupTemplate.php`**
  - Relaciones: `options()` → `HasMany(ModifierOptionTemplate)`, `products()` → `BelongsToMany(Product)`
  - Usa `BelongsToTenant`
- **`app/Models/ModifierOptionTemplate.php`**
  - Relación: `group()` → `BelongsTo(ModifierGroupTemplate)`

### `Product::getAllModifierGroups()`

`app/Models/Product.php:79–126`

```php
public function getAllModifierGroups(): Collection
{
    // 1. Modifiers inline (HasMany directa)
    $inline = $this->modifierGroups->map(fn($g) => $this->decorate($g, 'inline'));

    // 2. Modifiers del catálogo (BelongsToMany via pivote)
    $catalog = $this->modifierGroupTemplates->map(fn($g) => $this->decorate($g, 'catalog'));

    return $inline->concat($catalog)->sortBy('sort_order')->values();
}
```

Cada grupo expuesto incluye el campo `source: 'inline' | 'catalog'`. El mismo patrón se aplica en `Promotion::getAllModifierGroups()`.

### Admin — Gestión del catálogo

**Controller:** `app/Http/Controllers/ModifierCatalogController.php`

| Ruta | Método | Endpoint |
| --- | --- | --- |
| `modifiers.index` | GET | `/modifiers` |
| `modifiers.store` | POST | `/modifiers` |
| `modifiers.update` | PUT | `/modifiers/{template}` |
| `modifiers.destroy` | DELETE | `/modifiers/{template}` |
| `modifiers.toggle` | PATCH | `/modifiers/{template}/toggle` |
| `modifiers.reorder` | PATCH | `/modifiers/reorder` |

**Vista:** `resources/js/Pages/Modifiers/Index.vue` — CRUD con modal, permite agregar/quitar opciones dinámicamente dentro del modal.

### Integración con productos y promociones

En `Pages/Products/Create.vue` y `Pages/Products/Edit.vue` (y equivalentes para Promociones), hay un selector **"Agregar del catálogo"** separado del editor inline. Los grupos agregados desde el catálogo aparecen con:

- Badge indigo (distinto del badge de grupos inline)
- Campos **read-only** — no se pueden editar desde el producto (sólo desde `/modifiers`)

Al guardar, el controller invoca el trait `Concerns\SyncsModifierGroups`, que sincroniza ambas relaciones (inline `HasMany` + pivote `BelongsToMany` del catálogo).

### Form Requests

`StoreModifierGroupTemplateRequest`:

| Campo | Reglas |
| --- | --- |
| `name` | required, string, max:255 |
| `selection_type` | required, in:single,multiple |
| `is_required` | boolean |
| `is_active` | boolean |
| `max_selections` | nullable, required_if:selection_type,multiple, integer, min:2 |
| `options` | required, array, min:1 |
| `options.*.name` | required, string |
| `options.*.price_adjustment` | numeric |
| `options.*.production_cost` | numeric, min:0 |

### API Pública

`GET /api/menu` expone modifiers unificados:

```json
{
  "modifier_groups": [
    {
      "id": 12,
      "source": "inline",
      "name": "Tamaño",
      "selection_type": "single",
      "is_required": true,
      "max_selections": null,
      "options": [
        { "id": 34, "source": "inline", "name": "Chico", "price_adjustment": 0 }
      ]
    },
    {
      "id": 7,
      "source": "catalog",
      "name": "Extras",
      "selection_type": "multiple",
      "is_required": false,
      "max_selections": 3,
      "options": [
        { "id": 20, "source": "catalog", "name": "Queso extra", "price_adjustment": 15 }
      ]
    }
  ]
}
```

El cliente SPA **no distingue** inline vs catalog para la UX — sólo para construir el payload correcto al crear el pedido.

### Cliente SPA — `ProductModal.vue`

- Renderiza grupos inline y de catálogo indistintamente
- Respeta `max_selections` deshabilitando checkboxes adicionales cuando se alcanza el límite
- Al enviar el payload, para cada modifier seleccionado incluye:
  - `modifier_option_id` si es inline
  - `modifier_option_template_id` si es catálogo

Mismo patrón que productos vs promociones.

### OrderService — Validación y snapshot

`app/Services/OrderService.php` (líneas ~250–407 y ~540–560):

1. Valida cada modifier contra el grupo (inline u option template) — mismo flujo para ambos
2. Valida `max_selections`, `is_required`, `selection_type`
3. Valida que los modifiers pertenezcan al producto (o promoción) correcto
4. Al persistir cada `OrderItemModifier`:
   - `modifier_option_id` = id real si es inline, **`null` si es catálogo**
   - `modifier_option_name` = snapshot (siempre con el nombre actual)
   - `price_adjustment` y `production_cost` = snapshot

El snapshot garantiza que editar/eliminar un template del catálogo no rompa el historial de pedidos.

### Tests

`tests/Feature/ModifierCatalogTest.php` — 581 líneas, incluye:

- Admin CRUD del catálogo
- Aplicar un template a un producto
- `max_selections` honrado en la API pública
- Validación en `OrderService` cuando se mezclan inline + catálogo
- Snapshot funciona aunque el template se elimine después

---

_PideAqui — Módulo Menú v1.2 — Actualizado Marzo 2026 (catálogo de modifiers)_
