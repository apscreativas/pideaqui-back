# Módulo 07 — Panel SuperAdmin

> Pantallas de referencia: Sin stitch visual (ver PRD §7.3 para descripción de pantallas)
> Ruta base: `/super/...`

---

## Descripción General

Panel de administración de la plataforma PideAqui como SaaS. Solo accesible por el equipo interno de PideAqui. Permite crear restaurantes, configurar sus límites individuales, monitorear el uso y activar/desactivar restaurantes.

No existe registro público para SuperAdmin. El acceso es manual y restringido.

---

## Guard y Modelo

| Guard | Modelo | Tabla |
|---|---|---|
| `superadmin` | `SuperAdmin` | `super_admins` |

El modelo `SuperAdmin` es **completamente separado** del modelo `User` (Admin Restaurante). No comparten sesión ni guard.

El SuperAdmin usa el **login unificado** en `/login`. El `LoginController@store` detecta automáticamente si las credenciales corresponden a un SuperAdmin o a un admin de restaurante (intenta el guard `superadmin` primero, luego `web`). Ver [01-auth.md](./01-auth.md) para detalles del flujo de login.

---

## Pantallas

### Dashboard SuperAdmin (`/super/dashboard`)

KPIs globales de la plataforma:

| KPI | Descripción |
|---|---|
| Restaurantes activos | Total de restaurantes con `is_active = true` |
| Pedidos totales del mes | Suma de pedidos de todos los restaurantes en el mes |
| Nuevos restaurantes | Registros creados en el mes actual |
| Feed de actividad | Lista de acciones recientes (creaciones, bloqueos, etc.) |

### Lista de Restaurantes (`/super/restaurants`)

- Tabla paginada de todos los restaurantes.
- Columnas: nombre, slug, pedidos del mes, sucursales activas, límite mensual, estado.
- Filtros: por estado (activo/inactivo).
- Acciones por fila: activar/desactivar, ver detalle.
- Botón "+ Crear restaurante".

### Detalle del Restaurante (`/super/restaurants/{id}`)

- Información completa: nombre, slug, logo, correo del admin, `access_token`.
- **Límites configurados:** `orders_limit`, `orders_limit_start` (date), `orders_limit_end` (date), `max_branches`.
- **Uso del periodo:** pedidos realizados / límite (dentro del rango de fechas), sucursales creadas / límite.
- **Botones de acción:**
  - Activar / Desactivar restaurante.
  - Editar límites (abre formulario inline o modal).
  - Ver/copiar `access_token` (para configurar el SPA del cliente).
  - Regenerar `access_token` (con modal de confirmación — invalida integraciones activas).

### Crear Restaurante (`/super/restaurants/create`)

**Campos:**
- Nombre del restaurante. Requerido.
- ~~Slug (URL amigable)~~ — **removido del UI (abr 2026)**. Se genera automáticamente en el backend a partir del nombre con `Str::slug()` y sufijo numérico (`-2`, `-3`, …) si colisiona. La columna `slug` permanece en `restaurants` (UNIQUE) para identificación interna.
- Logo (upload a cloud storage). Opcional.
- **Nombre del administrador** (`admin_name`). Requerido.
- **Correo del administrador** (`admin_email`, se crea el `User` con este correo). Requerido.
- Contraseña inicial del admin. Requerido (el admin puede cambiarla después).
- `orders_limit` — límite de pedidos del periodo. Requerido.
- `orders_limit_start` — fecha de inicio del periodo (date). Requerido.
- `orders_limit_end` — fecha de fin del periodo (date). Requerido.
- `max_branches` — límite de sucursales. Requerido.

Al crear el restaurante (transacción DB):
1. Se crea el registro en `restaurants` con `access_token` generado automáticamente (SHA256).
2. Se crea el `User` (Admin Restaurante) con `admin_name` y `admin_email`, vinculado con `restaurant_id`.
3. Se inicializan los 3 métodos de pago por defecto: cash (activo), terminal (inactivo), transfer (inactivo).

### Estadísticas Globales (`/super/statistics`)

- Gráfica de pedidos por día (últimos 30 días).
- Gráfica de nuevos restaurantes por mes.
- Tabla de top restaurantes por pedidos del mes.
- Todos los datos son de **todos los restaurantes** (vista global, no por tenant).

### Ajustes de la Plataforma (`/super/settings`)

- Nombre de la plataforma (PideAqui).
- Dominio base.
- Logo de la plataforma.
- Configuración general interna.

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `SuperAdmin` | `super_admins` | Administrador de la plataforma |
| `Restaurant` | `restaurants` | Tenants — creados y configurados aquí |
| `User` | `users` | Admin del restaurante — creado aquí |
| `Branch` | `branches` | Para monitoreo de uso |
| `Order` | `orders` | Para estadísticas globales |

---

## Reglas de Negocio

- El SuperAdmin puede ver datos de **todos** los restaurantes. No aplica multitenancy a este guard.
- Los límites (`orders_limit`, `orders_limit_start`, `orders_limit_end`, `max_branches`) son **manuales por restaurante**. No existen planes ni tiers automáticos.
- Al desactivar un restaurante (`is_active = false`):
  - El SPA del cliente muestra una pantalla de "Restaurante no disponible" (o similar).
  - El panel del admin del restaurante sigue siendo accesible para el admin (puede ver historial).
  - La API pública retorna error si el token pertenece a un restaurante inactivo.
- El `access_token` se genera automáticamente (UUID o token seguro) y es único por restaurante. Es la clave que usa el SPA del cliente para identificar su restaurante en la API.
- El SuperAdmin no puede hacer pedidos ni gestionar el menú de los restaurantes. Solo gestiona la plataforma.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[01-auth.md](./01-auth.md)** | El SuperAdmin crea los `User` (admins de restaurante) al crear restaurantes. |
| **[05-branches.md](./05-branches.md)** | `max_branches` configurado aquí limita cuántas sucursales puede crear el admin del restaurante. |
| **[03-orders.md](./03-orders.md)** | `orders_limit` configurado aquí bloquea nuevos pedidos cuando se alcanza el límite del periodo. |
| **[06-settings.md](./06-settings.md)** | `ar_20` (Mis Límites) muestra al admin del restaurante los valores que el SuperAdmin configuró aquí. |
| **[10-api.md](./10-api.md)** | El `access_token` generado aquí es el que usa el SPA del cliente en cada request a la API. |

---

## Implementación Backend

```
Guard:
  'superadmin' → modelo: SuperAdmin, tabla: super_admins

Routes (prefijo /super, middleware: auth:superadmin):
  POST /super/logout                       → SuperAdmin\AuthController@logout

  GET  /super/dashboard                    → SuperAdmin\DashboardController@index
  GET  /super/restaurants                  → SuperAdmin\RestaurantController@index
  GET  /super/restaurants/create           → SuperAdmin\RestaurantController@create
  POST /super/restaurants                  → SuperAdmin\RestaurantController@store
  GET  /super/restaurants/{id}             → SuperAdmin\RestaurantController@show
  PUT  /super/restaurants/{id}/limits      → SuperAdmin\RestaurantController@updateLimits
  PATCH /super/restaurants/{id}/toggle     → SuperAdmin\RestaurantController@toggleActive
  POST /super/restaurants/{id}/regenerate-token → SuperAdmin\RestaurantController@regenerateToken
  PUT  /super/restaurants/{id}/reset-password   → SuperAdmin\RestaurantController@resetAdminPassword
  GET  /super/statistics                   → SuperAdmin\StatisticsController@index
  GET  /super/settings                     → SuperAdmin\SettingsController@index
  PUT  /super/settings                     → SuperAdmin\SettingsController@update

Login: El SuperAdmin usa el login unificado en /login (ver 01-auth.md).
  POST /login → Auth\LoginController@store (detecta guard automáticamente)

Form Requests:
  CreateRestaurantRequest
    - Valida: name, admin_name, admin_email (único en users), password, billing_mode (grace|manual). En modo manual: orders_limit, orders_limit_start (date), orders_limit_end (date), max_branches.
    - El slug NO se valida — se auto-genera en `RestaurantController::generateUniqueSlug()` desde el name.
  UpdateRestaurantLimitsRequest
    - Valida: orders_limit, orders_limit_start (date), orders_limit_end (date), max_branches

Acciones especiales:
  regenerateToken — Regenera el access_token (SHA256). Requiere confirmación modal en frontend.
  resetAdminPassword — Resetea la contraseña del admin del restaurante. Requiere `password` + `password_confirmation`.

Al crear restaurante (transacción DB):
  1. Restaurant::create([...]) con access_token = hash('sha256', Str::random(40))
  2. User::create([admin_name, admin_email, ..., restaurant_id => restaurant.id])
  3. PaymentMethod::insert([
       {type: cash, is_active: true},      // ← activo por defecto
       {type: terminal, is_active: false},
       {type: transfer, is_active: false},
     ])
```

**Generación del `access_token`:**
```php
'access_token' => Str::random(64)
// o mejor:
'access_token' => hash('sha256', Str::random(40))
```

---

## Notas de Diseño

Sin stitch visual disponible. Seguir el mismo sistema de diseño del panel admin:
- Sidebar izquierdo (posiblemente diferente color/branding para distinguir que es SuperAdmin).
- Mismo color primario `#FF5722`, fuente Inter, Material Symbols.
- Tablas con paginación para la lista de restaurantes.
- Dashboard con grid de 3–4 cards KPI.

---

_PideAqui — Módulo SuperAdmin v1.0 — Febrero 2026_
