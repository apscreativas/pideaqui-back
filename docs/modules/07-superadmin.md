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

Organizado en 4 tabs: **Overview**, **Revenue**, **Subscriptions**, **Alertas**.

**Tab Overview** — KPIs globales:

| KPI | Descripción |
|---|---|
| MRR | Monthly Recurring Revenue computado de Stripe subscriptions activas |
| Suscripciones activas | Conteo de `subscriptions.stripe_status='active'` |
| Restaurantes totales | Todos los registros en `restaurants` |
| Nuevos este mes | Registros con `created_at` en el mes actual |
| Pedidos totales del mes | Suma global de `orders` del mes |
| Distribución por status | `active`, `past_due`, `grace_period`, etc. |
| Distribución por plan | Cada plan con conteo + revenue atribuido |

**Tab Alertas** (actualizado Abr 2026) — 8 cards click-through:

_Sección "Alertas accionables":_
| Card | Query | Filtro URL |
|---|---|---|
| Gracia expira ≤3 días | `status='grace_period' AND grace_period_ends_at BETWEEN now() AND now()+3d AND is_active=true` | `?alert=grace_expiring` |
| ≥80% del límite | Restaurantes activos con `orders_limit > 0` y `period_orders_count / orders_limit >= 0.8` (subquery correlacionado, SQL-side ratio) | `?alert=orders_near_limit` |
| Modo manual | `billing_mode='manual' AND is_active=true` | `?alert=billing_manual` |
| Nuevos en 7 días | Split `signup_source=self_signup` vs `super_admin`, `is_active=true` | `?alert=new_this_week` |

_Sección "Estado general" (también click-through):_
| Card | Filtro URL |
|---|---|
| Past due | `?alert=past_due` |
| Periodo de gracia (total) | `?alert=grace_period` |
| Suspendidos | `?alert=suspended` |
| Sin suscripción | `?alert=no_subscription` (stripe_id IS NULL, status != disabled) |

Debajo de las cards: tabla "Restaurantes en riesgo" existente + feed de últimos 15 `BillingAudit`.

### Lista de Restaurantes (`/super/restaurants`)

- Tabla paginada de todos los restaurantes.
- Columnas: nombre + **badges inline** (`Gracia Nd`, `80%+`, `Manual`), slug, pedidos del mes con progress bar (usa scope `Restaurant::withPeriodOrdersCount()` — sin N+1), sucursales activas, estado.
- **Filtros combinables:**
  - Status: `?status=0|1`
  - Alerta: `?alert=...` (8 tipos — ver Dashboard arriba)
- Banner superior cuando hay filtro de alerta activo con conteo + botón "Limpiar filtro".
- Pills de filtros rápidos (accionables) arriba de la tabla.
- Acciones por fila: activar/desactivar, ver detalle.
- Botón "+ Crear restaurante".

### Detalle del Restaurante (`/super/restaurants/{id}`)

Layout redesign Abr 2026 para mejor densidad horizontal:

- **Hero:** breadcrumb + h1 con status pill + línea de pills inline (slug mono, modo de billing + plan, `signup_source`, fecha de creación, ID) + botón primario Activar/Desactivar.
- **KPI row (4 cards horizontales):**
  - Pedidos del mes con progress bar
  - Sucursales con progress bar
  - Gracia / Suscripción (card con urgencia visual — rojo si ≤1 día, naranja si ≤3)
  - Stripe (conectado / sin suscripción)
- **Grid 3/2:**
  - Izquierda: Administrador (avatar inicial + email + acciones Restablecer password y Enviar verificación), Plan y límites (dl read-only + editor manual inline cuando aplica).
  - Derecha: Menú público (QR 200px client-side con `qrcode` npm + URL + Copiar/Descargar QR como PNG + Renombrar slug inline con `SlugInput` y checkbox de confirmación).
- **Acciones disponibles:**
  - Toggle activar/desactivar
  - Reset password del admin
  - **Enviar correo de verificación al admin** (escape hatch — audit entry `verification_email_sent_manually`)
  - Editar límites manuales / Cambiar a manual
  - Iniciar periodo de gracia / Extender
  - Renombrar slug (con checkbox obligatorio que advierte invalidación de QR impresos y links compartidos; audita `restaurant_slug_renamed` con `{old_slug, new_slug}`)

### Crear Restaurante (`/super/restaurants/create`)

**Campos:**
- Nombre del restaurante. Requerido.
- **Slug** — visible, editable en vivo con `SlugInput.vue`. Autocompleta desde el nombre (debounce 500ms, cached), valida formato (`config/tenants.php` regex + reserved list de 42 entradas) y disponibilidad contra `GET /api/slug-check`. Muestra sugerencias clickeables si está tomado o reservado. Backend valida de nuevo con `ValidSlug` rule + `Rule::unique` en `CreateRestaurantRequest`.
- Logo (upload a cloud storage). Opcional.
- **Nombre del administrador** (`admin_name`). Requerido.
- **Correo del administrador** (`admin_email`, se crea el `User` con este correo). Requerido.
- Contraseña inicial del admin. Requerido (el admin puede cambiarla después).
- `orders_limit` — límite de pedidos del periodo. Requerido.
- `orders_limit_start` — fecha de inicio del periodo (date). Requerido.
- `orders_limit_end` — fecha de fin del periodo (date). Requerido.
- `max_branches` — límite de sucursales. Requerido.

Al crear el restaurante, el controller delega en `Services\Onboarding\RestaurantProvisioningService::provision()` con `source='super_admin'` (ver [01-auth.md](./01-auth.md#implementación-backend) para el flujo completo). La transacción crea:

1. Restaurant con `signup_source='super_admin'` y los campos correspondientes al modo (grace usa defaults del Plan, manual usa los límites del formulario).
2. `User` admin con `restaurant_id`, `role='admin'` y **`email_verified_at = now()` (pre-verificado)** — admins creados por SuperAdmin no necesitan verificar correo.
3. 3 PaymentMethod stub: cash (activo), terminal (inactivo), transfer (inactivo).
4. `BillingAudit` entry con `action='restaurant_created'`, `actor_type='super_admin'`.

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
  - El SPA universal retorna `410 {code:"tenant_unavailable"}` en `/api/public/{slug}/*` y muestra `TenantUnavailable.vue`.
  - El panel del admin del restaurante sigue siendo accesible para el admin (puede ver historial).
  - La política oficial `status=suspended` (Abr 2026): NO opera (pedidos, POS, API pública) pero SÍ puede preparar (editar catálogo, branding, horarios, cupones, promociones). Documentado en `ARCHITECTURE.md §2.7`.
- El `slug` del restaurante es el identificador público (URL `/r/{slug}`). Se valida y sugiere en vivo con el componente `SlugInput.vue` (reutilizado en self-signup y SuperAdmin create/rename). El antiguo `access_token` fue removido en Abr 2026.
- El SuperAdmin no puede hacer pedidos ni gestionar el menú de los restaurantes. Solo gestiona la plataforma.

### Platform Settings (nueva pantalla 2026-04)

Ruta: `/super/platform-settings`. Tabla de soporte: `platform_settings` (key/value, cacheada con `Cache::rememberForever`).

Setting manejado hoy: `public_menu_base_url` — URL base que usa `Restaurant::menuPublicUrl()` para construir el enlace canónico del menú (default: `config('app.url') . '/r/' . $slug`). Si el SuperAdmin la sobreescribe a `https://menu.pideaqui.mx`, todos los QR y URLs en UI del admin/SuperAdmin usan esa base.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[01-auth.md](./01-auth.md)** | El SuperAdmin crea los `User` (admins de restaurante) al crear restaurantes. |
| **[05-branches.md](./05-branches.md)** | `max_branches` configurado aquí limita cuántas sucursales puede crear el admin del restaurante. |
| **[03-orders.md](./03-orders.md)** | `orders_limit` configurado aquí bloquea nuevos pedidos cuando se alcanza el límite del periodo. |
| **[06-settings.md](./06-settings.md)** | `ar_20` (Mis Límites) muestra al admin del restaurante los valores que el SuperAdmin configuró aquí. |
| **[10-api.md](./10-api.md)** | El `slug` definido aquí es el identificador URL público (`/r/{slug}`) consumido por la SPA universal. |

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
  PATCH /super/restaurants/{id}/slug       → SuperAdmin\RestaurantController@renameSlug
  PUT  /super/restaurants/{id}/reset-password   → SuperAdmin\RestaurantController@resetAdminPassword
  POST /super/restaurants/{id}/send-verification → SuperAdmin\RestaurantController@sendVerification
  POST /super/restaurants/{id}/start-grace  → SuperAdmin\RestaurantController@startGracePeriod
  POST /super/restaurants/{id}/extend-grace → SuperAdmin\RestaurantController@extendGrace
  GET  /super/platform-settings            → SuperAdmin\PlatformSettingsController@index
  PUT  /super/platform-settings            → SuperAdmin\PlatformSettingsController@update
  GET  /super/statistics                   → SuperAdmin\StatisticsController@index
  GET  /super/settings                     → SuperAdmin\SettingsController@index
  PUT  /super/settings                     → SuperAdmin\SettingsController@update

Login: El SuperAdmin usa el login unificado en /login (ver 01-auth.md).
  POST /login → Auth\LoginController@store (detecta guard automáticamente)

Form Requests:
  CreateRestaurantRequest
    - Valida: name, slug (ValidSlug + Rule::unique), admin_name, admin_email (único en users), password, billing_mode (grace|manual). En modo manual: orders_limit, orders_limit_start (date), orders_limit_end (date), max_branches.
  UpdateRestaurantLimitsRequest
    - Valida: orders_limit, orders_limit_start (date), orders_limit_end (date), max_branches
  UpdateRestaurantSlugRequest
    - Valida: slug (ValidSlug + Rule::unique ignore self), confirm (accepted).

Acciones especiales:
  renameSlug — Cambia el slug con confirmación explícita. Audita action `restaurant_slug_renamed` con `{old_slug, new_slug}`.
  resetAdminPassword — Resetea la contraseña del admin del restaurante. Requiere `password` + `password_confirmation`.
  sendVerification — Reenvía correo de verificación al admin (audit `verification_email_sent_manually`).
  startGracePeriod / extendGrace — Gestión manual del período de gracia.

Creación de restaurante (delegada a `App\Services\Onboarding\RestaurantProvisioningService`):
  DB::transaction(function () {
    1. Restaurant::create([...]) con slug resuelto por SlugSuggester (usa el user-provided
       si es válido y libre, de lo contrario auto-genera desde el nombre).
    2. User::create([admin_name, admin_email, ..., restaurant_id => restaurant.id]).
       Si source=super_admin → email_verified_at=now().
       Si source=self_signup → email_verified_at=null (requiere verificación).
    3. PaymentMethod::insert([cash is_active=true, terminal/transfer is_active=false]).
    4. BillingAudit::log('restaurant_created', ...).
  });
  Retry una vez si QueryException por slug unique violation (forza auto-generación).

El mismo service es reutilizado por `Auth\RegisterController` (self-signup) y `SuperAdmin\RestaurantController@store`.
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
