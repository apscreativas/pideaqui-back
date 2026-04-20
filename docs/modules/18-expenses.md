# 18. Gastos (Expenses)

Registro operacional de gastos del restaurante (renta, servicios, insumos, nómina, mantenimiento, etc.) con categorización jerárquica de dos niveles, adjuntos de comprobantes y agregaciones para análisis.

Diseñado para que el dueño mida rentabilidad real: los gastos se cruzan con el revenue del dashboard/estadísticas para calcular utilidad neta.

---

## Pantallas

Sin mockup Stitch (feature post-MVP). Se accede desde el sidebar del admin: **"Gastos"** (icon `receipt_long`). La gestión de categorías vive en **Settings → Categorías de gastos**.

---

## Rutas

Todas requieren `auth`, `tenant` y `role:admin` (los operadores no ven ni registran gastos).

### Gastos

| Método | URI | Controlador | Nombre |
|--------|-----|-------------|--------|
| `GET` | `/expenses` | `ExpenseController@index` | `expenses.index` |
| `GET` | `/expenses/create` | `ExpenseController@create` | `expenses.create` |
| `POST` | `/expenses` | `ExpenseController@store` | `expenses.store` |
| `GET` | `/expenses/{expense}` | `ExpenseController@show` | `expenses.show` |
| `GET` | `/expenses/{expense}/edit` | `ExpenseController@edit` | `expenses.edit` |
| `PUT` | `/expenses/{expense}` | `ExpenseController@update` | `expenses.update` |
| `DELETE` | `/expenses/{expense}` | `ExpenseController@destroy` | `expenses.destroy` |
| `DELETE` | `/expenses/attachments/{attachment}` | `ExpenseController@destroyAttachment` | `expenses.attachments.destroy` |

### Categorías y subcategorías

| Método | URI | Controlador | Nombre |
|--------|-----|-------------|--------|
| `GET` | `/settings/expense-categories` | `ExpenseCategoryController@index` | `settings.expense-categories.index` |
| `POST` | `/settings/expense-categories` | `ExpenseCategoryController@store` | `settings.expense-categories.store` |
| `PUT` | `/settings/expense-categories/{category}` | `ExpenseCategoryController@update` | `settings.expense-categories.update` |
| `PATCH` | `/settings/expense-categories/{category}/toggle` | `ExpenseCategoryController@toggle` | `settings.expense-categories.toggle` |
| `DELETE` | `/settings/expense-categories/{category}` | `ExpenseCategoryController@destroy` | `settings.expense-categories.destroy` |
| `POST` | `/settings/expense-categories/{category}/subcategories` | `ExpenseCategoryController@storeSubcategory` | `settings.expense-subcategories.store` |
| `PUT` | `/settings/expense-subcategories/{subcategory}` | `ExpenseCategoryController@updateSubcategory` | `settings.expense-subcategories.update` |
| `PATCH` | `/settings/expense-subcategories/{subcategory}/toggle` | `ExpenseCategoryController@toggleSubcategory` | `settings.expense-subcategories.toggle` |
| `DELETE` | `/settings/expense-subcategories/{subcategory}` | `ExpenseCategoryController@destroySubcategory` | `settings.expense-subcategories.destroy` |

---

## Modelo de datos

### `expenses`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `restaurant_id` | FK(restaurants) | Tenant |
| `branch_id` | FK(branches), nullable | Gasto a nivel sucursal o global del restaurante |
| `expense_category_id` | FK(expense_categories) | |
| `expense_subcategory_id` | FK(expense_subcategories) | Debe pertenecer a la categoría seleccionada |
| `created_by_user_id` | FK(users) | Quién registró el gasto |
| `title` | varchar(255) | |
| `description` | text, nullable | |
| `amount` | decimal(10,2) | `> 0.00`, tope 99,999,999.99 |
| `expense_date` | date | `≤ hoy` (no se aceptan fechas futuras) |
| `created_at` / `updated_at` | timestamp | |

**Índices:** `(restaurant_id, expense_date)`, `(restaurant_id, expense_category_id)`, `(restaurant_id, branch_id, expense_date)`.

### `expense_categories`

| Columna | Tipo | Notas |
|---|---|---|
| `id`, `restaurant_id` | | Tenant |
| `name` | varchar(120) | |
| `is_active` | boolean default true | |
| `sort_order` | uint | Se auto-asigna `MAX + 1` al crear |

### `expense_subcategories`

| Columna | Tipo | Notas |
|---|---|---|
| `id`, `expense_category_id` | FK, cascade on delete | |
| `name`, `is_active`, `sort_order` | igual que categorías | |

### `expense_attachments`

| Columna | Tipo | Notas |
|---|---|---|
| `id`, `expense_id` | FK, cascade on delete | |
| `file_path` | string | Ruta en `MEDIA_DISK` (`public` o `s3`) |
| `file_name` | string | Nombre original subido por el usuario |
| `mime_type` | varchar(100) | |
| `size_bytes` | uint | |

El modelo expone vía accessors:

- `url` — URL pública resuelta contra el disco configurado
- `is_image` — `true` si el MIME/extensión es imagen
- `is_pdf` — `true` si el MIME/extensión es PDF

---

## Form Requests

### `StoreExpenseRequest` / `UpdateExpenseRequest`

```
title               required, string, max:255
description         nullable, string
amount              required, numeric, min:0.01, max:99999999.99
expense_date        required, date, before_or_equal:today
branch_id           required, exists:branches,id (scoped al restaurant actual)
expense_category_id required, exists:expense_categories,id (is_active=true en Store)
expense_subcategory_id required, exists:expense_subcategories,id (is_active=true en Store)
attachments         nullable, array, max:10
attachments.*       file, mimes:jpeg,jpg,png,webp,pdf, max:5120 (KB)
```

**Validación cruzada:** la subcategoría debe pertenecer a la categoría seleccionada. Si no, error `422`. Mensajes en español.

### `StoreExpenseCategoryRequest` / `UpdateExpenseCategoryRequest` / Subcategoría

```
name      required, string, max:120
is_active nullable, boolean
```

---

## Pages (Inertia)

| Archivo | Rol |
|---|---|
| `Pages/Expenses/Index.vue` | Tabla paginada con filtros (fecha, sucursal, categoría, subcategoría, rango de monto). Agregaciones arriba: total, promedio, desglose por categoría |
| `Pages/Expenses/Create.vue` | Formulario con upload múltiple de adjuntos, select de categoría/subcategoría dinámico, validación client-side de MIME y tamaño |
| `Pages/Expenses/Edit.vue` | Idéntico a Create, pre-poblado. Permite eliminar adjuntos existentes y subir nuevos |
| `Pages/Expenses/Show.vue` | Vista de solo lectura. Galería de adjuntos con preview inline para imágenes y viewer para PDFs. Botones edit/delete |
| `Pages/Expenses/Categories.vue` | Gestor inline de categorías y subcategorías (drag-handle visual, toggles, modals) |

---

## Controller (métodos y responsabilidad)

### `ExpenseController`

- `index` — lista filtrable + agregaciones. Eager load `category`, `subcategory`, `branch`, `attachments`
- `create` — retorna categorías/subcategorías activas y sucursales del tenant
- `store` — transacción: crea gasto, persiste adjuntos al disco `MEDIA_DISK`, crea `expense_attachments`
- `show` — detalle con relaciones eager
- `edit` — formulario pre-poblado
- `update` — transacción: actualiza campos, permite subir adjuntos nuevos (no remueve existentes salvo por `destroyAttachment`)
- `destroy` — elimina gasto + todos sus adjuntos del storage
- `destroyAttachment` — borra un adjunto puntual sin tocar el gasto

### `ExpenseCategoryController`

- `index` — tree view de categorías con subcategorías ordenadas + count de gastos por nodo
- `store` / `update` / `toggle` — CRUD de categoría, `destroy` rechaza con `422` si hay gastos asociados
- `storeSubcategory` / `updateSubcategory` / `toggleSubcategory` / `destroySubcategory` — análogo para subcategorías

---

## Policies

Ambos recursos siguen la misma regla:

| Acción | Regla |
|---|---|
| `viewAny`, `create` | `user.isAdmin()` ∧ `user.restaurant_id` no nulo |
| `view`, `update`, `delete` | `user.isAdmin()` ∧ `user.restaurant_id == resource.restaurant_id` |

Los operadores (`role: operator`) **no** tienen acceso al módulo.

---

## Reglas de Negocio

- **Tenant-scoped**: `Expense` y `ExpenseCategory` usan trait `BelongsToTenant`; un restaurante nunca ve gastos de otro.
- **Branch opcional**: un gasto puede ser global del restaurante (`branch_id = null`) o asignado a una sucursal específica.
- **Subcategoría obligatoria**: los gastos deben clasificarse hasta subcategoría, no solo categoría. Esto asegura reportes consistentes.
- **Pertenencia cross-validada**: si se envía `expense_category_id = A` y `expense_subcategory_id = B` donde B pertenece a otra categoría, el request falla con `422`.
- **Sin fechas futuras**: `expense_date` ≤ hoy. Evita inflar artificialmente KPIs futuros.
- **Categoría inactiva**: al **crear** se rechaza usar categoría/subcategoría inactiva. Al **editar** se permite (si la categoría se desactivó después, el gasto puede seguir editándose).
- **Elimina categoría solo si está vacía**: no hay soft-delete cascade; el dueño debe re-clasificar o eliminar los gastos primero.
- **Adjuntos**: máximo 10 por gasto, 5 MB cada uno, solo JPEG/PNG/WebP/PDF. SVG bloqueado por seguridad.
- **Sin API pública**: no existe `ExpenseResource` ni rutas en `api.php`. Los gastos son datos internos del admin.
- **Sin broadcast**: no emite eventos WebSocket. No requiere actualización en tiempo real para otros usuarios.

---

## Integración con Estadísticas

El `StatisticsService` del dashboard combina:

- **Revenue** (orders + pos_sales) en el período
- **Expenses** (suma de `amount` en el período)
- **Utilidad neta** = revenue − costo de producción (snapshot) − expenses

Ver [02-dashboard.md](./02-dashboard.md) para los KPIs expuestos.

---

## Tests

`tests/Feature/ExpenseTest.php` cubre (20 casos):

- **Autorización**: guest bloqueado, operator bloqueado, admin autorizado
- **Creación**: validación de FK de sucursal, pertenencia subcategoría→categoría, rechazo de fecha futura, rechazo de categoría inactiva
- **Adjuntos**: persisten al disco, se sirven URL/flags correctos al frontend, `destroyAttachment` borra archivo
- **Update/Delete**: admin puede editar y eliminar sus gastos
- **Categorías**: CRUD completo, toggle, rechazo de delete si hay gastos asociados
- **Tenant isolation**: un admin de otro restaurante recibe `404` al intentar acceder

---

## Módulos Relacionados

- [02 — Dashboard](./02-dashboard.md) — Consume agregaciones de gastos para utilidad neta
- [06 — Configuración](./06-settings.md) — La gestión de categorías vive bajo Settings
- [05 — Sucursales](./05-branches.md) — `expense.branch_id` referencia sucursales del tenant
