# PideAquí — Estado Actual

> Snapshot del estado del producto al **28 de abril de 2026**.
> Para el historial cronológico detallado, ver [CHANGELOG.md](./CHANGELOG.md).

---

## Resumen

- **Versión del producto:** v3.1 (MVP + post-MVP + self-signup público + SuperAdmin alertas accionables)
- **Branch activa:** `main` (existen también `pagos_stripe`, `test` como rastro histórico)
- **Stack:** Laravel 12 + PostgreSQL 18 + Inertia + Vue 3 + Reverb + Stripe (Cashier)
- **Tests:** **697 funciones `test_*`** (backend Laravel, 2874 assertions, ~21 s). SPA y landing sin tests.

---

## Estado por área

| Área | Estado | Referencia |
|---|---|---|
| Infraestructura + Laravel Herd (dev) | ✅ Estable | [README.md](./README.md) |
| Base de datos (18 tablas dominio + billing + Cashier) | ✅ Estable | [docs/DATABASE.md](./docs/DATABASE.md) |
| Autenticación + multi-tenancy + 2 roles (admin/operator) | ✅ Estable | [docs/modules/01-auth.md](./docs/modules/01-auth.md) |
| Panel Admin — Menú + DnD reorder | ✅ Estable | [docs/modules/04-menu.md](./docs/modules/04-menu.md) |
| Panel Admin — Sucursales + branch activo obligatorio | ✅ Estable | [docs/modules/05-branches.md](./docs/modules/05-branches.md) |
| Panel Admin — Pedidos + Kanban + edición + audit | ✅ Estable | [docs/modules/03-orders.md](./docs/modules/03-orders.md) |
| Panel Admin — Dashboard + Estadísticas con utilidad neta | ✅ Estable | [docs/modules/02-dashboard.md](./docs/modules/02-dashboard.md) |
| Panel Admin — Configuración (general, branding, horarios, fechas especiales, usuarios, suscripción) | ✅ Estable | [docs/modules/06-settings.md](./docs/modules/06-settings.md) |
| Panel Admin — Cupones | ✅ Estable | [docs/modules/16-coupons.md](./docs/modules/16-coupons.md) |
| Panel Admin — Promociones standalone | ✅ Estable | [docs/modules/15-promotions.md](./docs/modules/15-promotions.md) |
| Panel Admin — Gastos + categorías jerárquicas | ✅ Estable | [docs/modules/18-expenses.md](./docs/modules/18-expenses.md) |
| Panel Admin — Cancelaciones (analytics) | ✅ Estable | [docs/modules/11-cancellations.md](./docs/modules/11-cancellations.md) |
| Panel Admin — Mapa operativo | ✅ Estable | [docs/modules/12-map.md](./docs/modules/12-map.md) |
| Panel Admin — POS (caja mostrador) | ✅ Estable | [docs/modules/14-pos.md](./docs/modules/14-pos.md) |
| Panel SuperAdmin — Restaurantes, planes, billing settings, tab Alertas accionables | ✅ Estable | [docs/modules/07-superadmin.md](./docs/modules/07-superadmin.md) |
| Self-signup público `/register` + email verification | ✅ Estable (Abr 22) | [docs/modules/01-auth.md](./docs/modules/01-auth.md) |
| API pública REST (7 endpoints, 3 rate-limited) | ✅ Estable | [docs/modules/10-api.md](./docs/modules/10-api.md) |
| DeliveryService (Google + Haversine pre-filtro) | ✅ Estable | [docs/modules/09-delivery-service.md](./docs/modules/09-delivery-service.md) |
| WebSockets (Reverb + Echo, 7 eventos en 2 canales) | ✅ Estable | [docs/modules/13-websockets.md](./docs/modules/13-websockets.md) |
| Billing SaaS (Stripe + Cashier + gate operacional) | ✅ Estable | [docs/modules/17-billing.md](./docs/modules/17-billing.md) |
| Cliente SPA (repo `pideaqui-front`) | ✅ Estable | [docs/modules/08-customer-flow.md](./docs/modules/08-customer-flow.md) |
| Landing page (repo `landing-pideaqui`) | ✅ Estable | `../landing/README.md` |

---

## Trabajo reciente (Abr 28)

- **Feature: DnD para reordenar grupos de modificadores y opciones** — `/modifier-catalog` (modal) y `/menu/products/{create,edit}` ahora permiten reordenar grupos y opciones por arrastre. Implementado con HTML5 DnD nativo vía nuevo composable `resources/js/Composables/useDragSort.js` (parámetro `scope` para aislar listas: `'groups'` vs `'options:${gi}'`). Backend sin cambios — `sort_order` se sigue derivando del índice del array al guardar (`SyncsModifierGroups` y `ModifierCatalogController@syncOptions`). El orden queda guardado por restaurante automáticamente vía `restaurant_id`.
- **Fix `production_cost` vacío** — dejar el campo "Costo de producción" en blanco al crear o editar un producto generaba server error. Causa: Laravel 12 no incluye `ConvertEmptyStringsToNull` por defecto; el string vacío `""` fallaba la validación `numeric`. `prepareForValidation()` en `StoreProductRequest` y `UpdateProductRequest` normaliza a `0`. Inicializadores en `Create.vue` y `Edit.vue` cambiados de `''` a `null`.
- **Feature: eliminar imagen en edición de producto** — `/menu/products/{id}/edit` ahora incluye botón "Eliminar imagen" bajo el preview. Al confirmar, `image_path` se pone a `null` en BD y el archivo se borra del storage. El producto queda con imagen predeterminada. Campo `remove_image` agregado a `UpdateProductRequest` y manejado en `ProductController@update`.

## Trabajo reciente (Abr 27)

- **Branding con logotipo de marca** — se reemplazó el icono `local_fire_department` + texto "PideAqui" por la imagen `public/images/logo.png` en los tres puntos de contacto principales: pantalla de login (`/login`), header del sidebar autenticado (`AppLayout`, visible en `/dashboard` y demás), y header de correos transaccionales (`vendor/mail/html/header.blade.php`, aplica a verify-email, password reset, new order, grace expiring). En el login y sidebar se usa binding dinámico `:src="'/images/logo.png'"` para evitar que Vite/Rollup intente resolver la ruta absoluta como import en build. En correos se usa `asset('images/logo.png')` para garantizar URL absoluta. Detalle en [CHANGELOG.md](./CHANGELOG.md) entrada 2026-04-27. **Pendiente**: `SuperAdminLayout.vue` sigue con el icono antiguo.
- **(Cliente SPA — pideaqui-front)** Bug fix de navegación en el flujo de checkout: los botones "back" del header en `CartSummary`, `DeliveryLocation` y `PaymentConfirmation` usaban `router.back()`, lo que podía sacar al usuario del sitio cuando llegaba desde un enlace externo (WhatsApp, redes). Ahora navegan a la ruta del paso previo del flujo (`/`, `/cart`, `/delivery`); el guard del router reescribe a `/r/:slug/...` automáticamente.

## Trabajo reciente (Abr 23)

- **Migración del entorno de desarrollo a Laravel Herd** — se dejó de usar Laravel Sail/Docker para dev. PHP 8.4 corre nativo servido por Herd, PostgreSQL como servicio de Herd en `127.0.0.1:5432`, sitio en `https://pideaqui-backend.test` con TLS local automático. Se removió el paquete `laravel/sail` del `composer.json`, se eliminó `compose.yaml`, y se reescribió la doc (`README.md`, `CONTRIBUTING.md`, `docs/OPERATIONS.md`, `docs/ARCHITECTURE.md`, `docs/modules/13-websockets.md`, `GEMINI.md`, `.env.example`). Detalle completo en [CHANGELOG.md](./CHANGELOG.md) entrada 2026-04-23.

## Trabajo reciente (Abr 22)

Entregadas en sesión del 2026-04-22:

- **Self-signup público** (`/register`, 11 tests) con email verification obligatoria solo para `signup_source='self_signup'`. SuperAdmin pre-verifica (`email_verified_at=now()`) al crear manual.
- **`RestaurantProvisioningService`** — servicio compartido que orquesta Restaurant+User+PaymentMethods+BillingAudit en una sola transacción. Reutilizado por SuperAdmin y `RegisterController`.
- **Email verification Spanish-branded** vía `VerifyEmailNotification` custom que override `Illuminate\Auth\Notifications\VerifyEmail`.
- **SuperAdmin Dashboard — Tab "Alertas accionables"**: 4 cards click-through (gracia ≤3 días, ≥80% del límite, modo manual, nuevos en 7 días) + las 4 de "Estado general" también clickables. Filtros `?alert=...` en Restaurants/Index con banner + badges inline.
- **Fix N+1 del SuperAdmin Restaurants/Index** vía scope `Restaurant::withPeriodOrdersCount()` con subquery correlacionado.
- **Redesign completo de SuperAdmin/Restaurants/Show.vue**: hero con pills inline + KPI row (4 cards) + grid 3/2 (admin + plan + token vs. QR público). Mejor uso del espacio horizontal.
- **Hardening Universal SPA cliente (R1-R4)** — `AbortController` por tenant, router guard que espera hidratación, slug guard en `bootstrapTenant`, y guard en watchers de persistencia (cart + order). Cierra todos los riesgos de contaminación cross-tenant identificados en la auditoría.

### Multi-tenant SPA universal (Abr 2026)

API pública usa exclusivamente el patrón slug:

- `/api/public/{slug}/*` con middleware `ResolveTenantFromSlug` (alias `tenant.slug`). Resuelve el tenant en runtime por URL: 404 si el slug no existe, 410 si el restaurante no puede recibir pedidos.
- El camino legacy por `X-Restaurant-Token` fue removido junto con la columna `restaurants.access_token`, el middleware `AuthenticateRestaurantToken`, la generación de tokens en onboarding y el botón "Regenerar token" del SuperAdmin.

El cliente SPA (repo `pideaqui-front`) es un único bundle universal. Se acceden los menús por URL `/r/{slug}`. Ver `docs/modules/10-api.md` y `tests/Feature/PublicSlugResolutionTest.php` para el contrato público.

**Hardening Abr 22** — el SPA ahora implementa:
- `AbortController` tenant-scoped en `client/src/services/api.js` — cancela fetches en vuelo al cambiar de slug.
- `router.beforeEach` async bloqueante (`client/src/router/index.js`) que aborta, hidrata stores (cart, order) y awaita `bootstrapTenant()` antes de montar el componente de la nueva ruta.
- Slug guard en `bootstrapTenant` (`client/src/stores/restaurant.js`) — respuestas tardías del tenant anterior se descartan si el slug actual cambió.
- Guard en `watch()` de cart y order stores — solo persisten si `activeSlug === currentSlugFromLocation()`, evitando que writes debounced contaminen la key de otro tenant.
- `<RouterView :key="route.params.slug">` en `client/src/App.vue` — fuerza remontaje de las vistas hijas al cambiar de tenant, reseteando refs locales (búsqueda, categoría activa, etc.) que Vue Router por default preserva al reusar la instancia del componente.

### Política `status=suspended` (Abr 22)

Decisión de diseño oficial: un restaurante suspendido **NO opera** (público 410, manual/POS bloqueados por `canOperate()`) pero **SÍ puede preparar su negocio** — editar catálogo, branding, horarios, cupones, promociones, usuarios. Esto reduce la fricción de reactivación: al pagar y volver a `active`, el restaurante arranca con su catálogo al día. Documentado en `docs/ARCHITECTURE.md` §2.7. Los controllers de preparación intencionalmente **no** gatean `canOperate()`.

---

## Próximos pasos sugeridos

No hay deadlines duros activos. Prioridades identificadas en [ROADMAP.md](./docs/ROADMAP.md) §3:

**Alta prioridad:**
- Facturación fiscal (CFDI) — requerido para clientes empresariales
- Monitoreo/observabilidad (Sentry + logs estructurados)
- Tests de integración del SPA cliente (cobertura 0% hoy)

**Media prioridad:**
- Throttle compuesto `ip:slug` + captcha invisible (Turnstile) en `POST /orders` y `POST /coupons/validate` antes de abrir signup público masivo
- Refresh periódico en la SPA cliente de `is_open`/`orders_limit_reached` (re-fetch `/api/public/{slug}/restaurant` cada 60-120s + en `visibilitychange`)
- i18n + multi-currency si se expande fuera de México

---

_Estado — PideAquí Backend — 2026-04-28_
