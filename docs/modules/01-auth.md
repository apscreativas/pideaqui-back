# Módulo 01 — Autenticación

> Pantallas de referencia: `ar_01_admin_login`

---

## Descripción General

Módulo que controla el acceso al Panel del Administrador del Restaurante y al Panel SuperAdmin mediante un **login unificado**. Es el módulo de entrada: ninguna otra pantalla de los paneles es accesible sin pasar por él.

Hay **dos guards** en la plataforma, pero comparten un **único formulario de login** en `/login`:

| Guard | Modelo | Descripción |
|---|---|---|
| `web` | `User` | Admin del restaurante |
| `superadmin` | `SuperAdmin` | SuperAdmin de la plataforma |

El `LoginController@store` intenta autenticar primero con el guard `superadmin`; si falla, intenta con el guard `web`. Esto permite que ambos tipos de usuario accedan desde la misma pantalla de login.

---

## Pantallas

### `ar_01` — Login Unificado (`/login`)

- Formulario centrado con logo de PideAqui.
- Campos: **correo electrónico** y **contraseña**.
- Checkbox "Recordarme".
- Sin opción de registro — los admins son creados por el SuperAdmin.
- Al autenticarse correctamente:
  - Si es SuperAdmin → redirige a `/super/dashboard`.
  - Si es Admin Restaurante → redirige a `/dashboard`.

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `User` | `users` | Admin del restaurante. Tiene `restaurant_id` FK. |

Campos relevantes del modelo `User`:
- `email` — credencial de acceso.
- `password` — hash bcrypt.
- `restaurant_id` — vincula al restaurante propietario (tenant).
- `remember_token` — para sesión persistente.

---

## Reglas de Negocio

- No existe registro público. El `User` (admin) es creado únicamente por el SuperAdmin al crear un restaurante.
- Un `User` está asociado a exactamente **un restaurante** (`restaurant_id`). No puede acceder a datos de otro restaurante.
- Después del login, el middleware de tenant (`EnsureTenantContext`) inyecta automáticamente el contexto del restaurante en cada request.
- El login es **unificado**: `POST /login` autentica tanto Admin Restaurante como SuperAdmin. El `LoginController@store` intenta el guard `superadmin` primero, luego `web`.
- El logout de SuperAdmin es `POST /super/logout`; el de Admin Restaurante es `POST /logout`.
- **Rate limiting:** El endpoint `POST /login` tiene `throttle:5,1` (máximo 5 intentos por minuto por IP).
- **Password hashing:** El modelo `User` tiene cast `'password' => 'hashed'`. Los controllers NO deben usar `Hash::make()` antes de asignar — el cast lo hashea automáticamente.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[07-superadmin.md](./07-superadmin.md)** | El SuperAdmin crea los `User` admins al crear restaurantes. Este módulo depende de esa creación previa. |
| **[02-dashboard.md](./02-dashboard.md)** | Destino tras login exitoso. |
| **Todos los módulos admin** | Requieren autenticación. El middleware de auth protege todas las rutas del panel. |

---

## Implementación Backend

```
Routes (web):
  GET  /login                → Auth\LoginController@create
  POST /login                → Auth\LoginController@store  (login unificado: superadmin guard primero, luego web)
  POST /logout               → Auth\LoginController@destroy (Admin Restaurante)
  POST /super/logout         → SuperAdmin\AuthController@logout (SuperAdmin)

Middleware aplicado a rutas protegidas del panel admin:
  - auth (guard: web)
  - EnsureTenantContext

Middleware aplicado a rutas protegidas del SuperAdmin:
  - auth (guard: superadmin)
```

**Guard `web`:** Usa el modelo `User` con `restaurant_id`.
**Guard `superadmin`:** Usa el modelo `SuperAdmin`.
**Middleware de tenant:** Al autenticar como admin de restaurante, inyecta `auth()->user()->restaurant` como contexto global de queries.

---

## Notas de Diseño (ar_01)

- Layout centrado en pantalla completa, fondo `#FAFAFA`.
- Logo de PideAqui en la parte superior del formulario.
- Card blanca con sombra suave y bordes redondeados (`rounded-xl`).
- Color primario `#FF5722` para el botón de submit.
- Tipografía Inter.
- Sin sidebar — esta es la única pantalla del admin sin sidebar.

---

_PideAqui — Módulo Auth v1.0 — Febrero 2026_
