# Módulo 01 — Autenticación

> Pantallas de referencia: `ar_01_admin_login`, `ar_01b_register`, `ar_01c_verify_email`

---

## Descripción General

Módulo que controla el acceso al Panel del Administrador del Restaurante y al Panel SuperAdmin mediante un **login unificado**, y permite **registro público de restaurantes** (self-signup) con verificación obligatoria de correo.

Hay **dos guards** en la plataforma, pero comparten un **único formulario de login** en `/login`:

| Guard | Modelo | Descripción |
|---|---|---|
| `web` | `User` | Admin del restaurante |
| `superadmin` | `SuperAdmin` | SuperAdmin de la plataforma |

El `LoginController@store` intenta autenticar primero con el guard `superadmin`; si falla, intenta con el guard `web`.

---

## Pantallas

### `ar_01` — Login Unificado (`/login`)

- Formulario centrado con logo de PideAqui.
- Campos: **correo electrónico** y **contraseña**.
- Checkbox "Recordarme".
- Link "¿No tienes cuenta? Crear cuenta" → `/register`.
- Link "¿Olvidé mi contraseña?" → `/forgot-password`.
- Al autenticarse correctamente:
  - Si es SuperAdmin → redirige a `/super/dashboard`.
  - Si el User NO tiene `restaurant_id` → rechazo con error.
  - Si `!hasVerifiedEmail()` → redirige a `/email/verify` (típico self-signup sin verificar).
  - Si todo OK → `/dashboard`.

### `ar_01b` — Registro público (`/register`)

- Formulario abierto (guard `guest`, `throttle:3,1`).
- Campos: **nombre del restaurante**, **tu nombre**, **correo**, **contraseña + confirmación**.
- Texto informativo: "14 días gratis — incluye 50 pedidos y 1 sucursal".
- Link "¿Ya tienes cuenta? Iniciar sesión" → `/login`.
- Al submit exitoso:
  - Se crea Restaurant (plan de gracia, `signup_source='self_signup'`) + User admin (sin verificar) + 3 PaymentMethods stub + BillingAudit.
  - Dispara `event(new Registered($admin))` → envía correo de verificación (branding PideAqui, texto en español).
  - `Auth::login($admin)` → redirige a `/email/verify`.

### `ar_01c` — Verificación de correo (`/email/verify`)

- Pantalla que invita al usuario a revisar su bandeja.
- Botón "Reenviar correo" (`POST /email/verification-notification`, `throttle:6,1`).
- Botón "Cerrar sesión" (accesible sin verificar).
- Al hacer click en el link del correo → `/email/verify/{id}/{hash}` (middleware `signed` + `throttle:6,1`) → marca `email_verified_at=now()` → redirige a `/dashboard?verified=1`.

---

## Modelos Involucrados

| Modelo | Tabla | Descripción |
|---|---|---|
| `User` | `users` | Admin del restaurante. `implements MustVerifyEmail`. Tiene `restaurant_id` FK. |
| `Restaurant` | `restaurants` | Tenant. `signup_source` indica origen del alta. |

Campos relevantes del modelo `User`:
- `email` — credencial de acceso (lowercase, unique).
- `password` — cast `'hashed'`.
- `email_verified_at` — timestamp. Si null, middleware `verified` bloquea acceso al grupo admin.
- `restaurant_id` — vincula al restaurante propietario (tenant).
- `remember_token` — sesión persistente.

Notificación personalizada: `App\Notifications\VerifyEmailNotification` extiende `Illuminate\Auth\Notifications\VerifyEmail` con subject/greeting/action text en español. `User::sendEmailVerificationNotification()` la dispara.

---

## Reglas de Negocio

- **Self-signup obligado a verificar:** usuarios creados vía `/register` tienen `email_verified_at = null`; deben hacer click en el link del correo para acceder. El middleware `verified` en el grupo admin los bloquea hasta entonces.
- **SuperAdmin-created: pre-verificados.** `RestaurantProvisioningService::createAdminUser()` setea `email_verified_at = now()` cuando `source='super_admin'`. El admin entra directo al dashboard sin fricción de verificación.
- **Usuarios antiguos (pre-2026-04-22):** backfill one-shot en la migración `2026_04_22_100039_backfill_email_verified_at_on_users` marcó como verificados a todos los existentes. No se les pide verificar al acceder.
- **Un `User` está asociado a exactamente un restaurante** (`restaurant_id`). No puede acceder a datos de otro tenant.
- **Login unificado:** `POST /login` autentica tanto Admin Restaurante como SuperAdmin. Intenta guard `superadmin` primero, luego `web`.
- **Logout:** `POST /logout` (admin) vive en el grupo `auth` (sin `verified`), así un user sin verificar puede salir desde la pantalla de verificación. `POST /super/logout` (SuperAdmin).
- **Rate limiting:**
  - `POST /login` → `throttle:5,1`
  - `POST /register` → `throttle:3,1`
  - `POST /email/verification-notification` → `throttle:6,1`
  - `GET /email/verify/{id}/{hash}` → `throttle:6,1` + `signed`
- **Password hashing:** cast `'password' => 'hashed'` es idempotente — no re-hashear manualmente.
- **Password strength para self-signup:** `Password::min(8)->letters()->mixedCase()->numbers()` (más estricto que SuperAdmin manual que usa `min:8|confirmed`).
- **Normalización de email en self-signup:** `prepareForValidation()` hace `strtolower(trim())` antes de validar unique.
- **Botón "Enviar correo de verificación" en SuperAdmin/Restaurants/Show:** escape hatch voluntario que envía el correo sin desverificar al admin. Audita como `verification_email_sent_manually`.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[07-superadmin.md](./07-superadmin.md)** | El SuperAdmin crea manualmente vía `POST /super/restaurants` usando el mismo `RestaurantProvisioningService`. |
| **[02-dashboard.md](./02-dashboard.md)** | Destino tras login exitoso (requiere email verificado). |
| **[17-billing.md](./17-billing.md)** | Self-signup crea restaurant en plan de gracia (14 días, 50 pedidos, 1 sucursal). `BillingAudit` entry con actor_type `self_signup`. |
| **Todos los módulos admin** | Requieren `['auth','verified','tenant']`. |

---

## Implementación Backend

```
Routes guest (web):
  GET  /login                → Auth\LoginController@create
  POST /login                → Auth\LoginController@store   (throttle:5,1)
  GET  /register             → Auth\RegisterController@create
  POST /register             → Auth\RegisterController@store  (throttle:3,1)
  GET  /forgot-password      → Auth\ForgotPasswordController@create
  POST /forgot-password      → Auth\ForgotPasswordController@store  (throttle:5,1)
  GET  /reset-password/{t}   → Auth\ResetPasswordController@create
  POST /reset-password       → Auth\ResetPasswordController@store   (throttle:5,1)

Routes auth (sin 'verified' — user no-verificado puede salir y verificar):
  POST /logout                          → Auth\LoginController@destroy
  GET  /email/verify                    → Auth\VerifyEmailController@notice
  GET  /email/verify/{id}/{hash}        → Auth\VerifyEmailController@verify  (signed, throttle:6,1)
  POST /email/verification-notification → Auth\VerifyEmailController@send    (throttle:6,1)

Routes admin (auth + verified + tenant):
  GET /dashboard …

Servicios relevantes:
  - Services\Onboarding\RestaurantProvisioningService — orquestador compartido
  - Services\Onboarding\Dto\ProvisionRestaurantData  — DTO immutable
  - Notifications\VerifyEmailNotification            — correo custom en español
  - Notifications\ResetPasswordNotification          — password reset custom

Tests:
  - tests/Feature/AuthTest.php (login/logout)
  - tests/Feature/Auth/RegisterTest.php (self-signup, 11 tests)
  - tests/Feature/Auth/EmailVerificationTest.php (verification + backfill, 10 tests)
  - tests/Unit/RestaurantProvisioningServiceTest.php (13 tests)
```

---

## Notas de Diseño

- Login, Register y VerifyEmail comparten la estética minimal centrada con logo naranja.
- Color primario `#FF5722` para submits y links.
- Tipografía Inter.
- Sin sidebar — son las únicas pantallas del admin sin sidebar.
- El correo de verificación usa el template blade default de Laravel con botón primario naranja (#FF5722 via `config/mail.php` theme).

---

_PideAqui — Módulo Auth v2.0 — Abril 2026_
