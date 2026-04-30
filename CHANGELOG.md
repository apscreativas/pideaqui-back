# CHANGELOG — PideAquí Backend (`pideaqui-back`)

> Historial consolidado por fecha descendente. Reemplaza las 34 entradas cronológicas que vivían en `STATUS.md`.
> Para estado actual, ver [STATUS.md](./STATUS.md).

---

## Abril 2026

### 2026-04-29

- **Feature: datos bancarios de transferencia en mensaje de WhatsApp del pedido** — Cuando el cliente final cierra un pedido pagando por transferencia, el resumen que se abre en WhatsApp del restaurante ahora incluye los datos completos para transferir, no sólo la etiqueta "Transferencia". `OrderService::buildWhatsAppMessage()` (en `app/Services/OrderService.php`) consulta `payment_methods` filtrando por `restaurant_id` y `type=transfer`, y agrega bloque "🏦 Datos para transferencia:" con `Banco`, `Beneficiario`, `CLABE` y `Alias` (cada línea aparece sólo si el campo está capturado). El mensaje lo construye el backend, así que no requirió cambios en el SPA cliente — éste sólo recibe `whatsapp_message` vía `OrderConfirmationResource` y lo abre tal cual. Aislamiento por restaurante automático: cada `payment_method` ya pertenece a su tenant.
- **Fix: login se quedaba recargando la misma URL sin error / superadmin podía abrir `/login` autenticado** — Dos defectos en el flujo de login resueltos en una sola pasada (`app/Http/Controllers/Auth/LoginController.php` + nuevo `app/Http/Middleware/RedirectIfAuthenticated.php`):
  - **`url.intended` rezagado apuntando a `/login`** o a otra ruta guest hacía que `redirect()->intended(route('dashboard'))` redirigiera de vuelta al login después de un attempt exitoso, sin generar error en consola ni en el request. El síntoma se "destrababa" cuando otro usuario iniciaba sesión (su flujo regenera/limpia la sesión). Solución: nuevo helper `safeIntended()` en `LoginController` que descarta `url.intended` si apunta a `/login`, `/logout`, `/register`, `/forgot-password`, `/reset-password`, `/email/verify` o a un host externo, y usa el fallback (`/dashboard` o `/super/dashboard`).
  - **Sesiones cruzadas entre guards** (`web` ↔ `superadmin`): si quedaba residuo de auth de un guard, el otro intento dejaba dos identidades simultáneas en la misma sesión y los middlewares posteriores podían tomar el guard incorrecto. Solución: `LoginController::store()` cierra ambos guards antes del `attempt`.
  - **Guard `superadmin` invisible al middleware `guest`**: el `RedirectIfAuthenticated` por defecto de Laravel sólo revisa el guard default (`web`), por lo que un superadmin autenticado podía abrir `/login` y ver el formulario en vez de ser redirigido. Solución: nuevo `App\Http\Middleware\RedirectIfAuthenticated` que revisa ambos guards y redirige al dashboard correcto (`/super/dashboard` para superadmin, `/dashboard` para web). Registrado como alias `guest` en `bootstrap/app.php`, así reemplaza al default sin tocar las rutas.
  - Tests existentes pasan (45/45 en suites de Auth/Login).

### 2026-04-28

- **Fix: pedido con la misma opción del catálogo en varios productos era rechazado** — Confirmar el pedido desde el SPA cliente con varias hamburguesas que compartían modificadores del catálogo (ej. tres burgers todas con "Lemon Pepper" o "Tocino para burger") fallaba con `The items.0.modifiers.0.modifier_option_template_id field has a duplicate value.` (más un error por cada duplicado). Raíz: la regla `distinct` aplicada con dos wildcards anidados (`items.*.modifiers.*.modifier_option_template_id`) toma la ruta explícita previa al primer wildcard (`items`) como contexto y aplana **todos** los items en una sola lista, así que la misma opción usada en items distintos contaba como duplicado. La intención original era "no repetir la misma opción dentro de un mismo item" (caso cubierto por `OrderApiTest::test_modifier_duplicate_within_same_item_is_rejected`). Solución:
  - Nuevo trait `app/Http/Requests/Concerns/ValidatesItemModifiers.php` que vía `withValidator()` recorre los items y rechaza repeticiones de `modifier_option_id` o `modifier_option_template_id` **dentro de un mismo item** (mensaje en español).
  - Se quita `distinct` y se aplica el trait en las 4 form requests afectadas: `StoreOrderRequest` (cliente público), `UpdateOrderRequest` (edición admin), `StoreManualOrderRequest` (pedidos manuales) y `StorePosSaleRequest` (POS / caja).
  - Tests existentes siguen pasando (96/96 entre `OrderApiTest` y `PosSaleTest`), incluyendo el caso negativo de duplicado en mismo item.
- **Feature: drag-and-drop para reordenar grupos de modificadores y sus opciones** — Hasta hoy, el orden de grupos y opciones se fijaba al momento de captura (`sort_order` autoasignado en cada `push`) y no se podía reordenar en UI. Ahora:
  - Nuevo composable `resources/js/Composables/useDragSort.js` — helper genérico de HTML5 drag & drop para arrays. Soporta múltiples listas independientes en una misma vista vía un parámetro `scope` (string), de modo que arrastres entre `'groups'` y `'options:0'`/`'options:1'`/... no se mezclan.
  - **Catálogo de modificadores** (`/modifier-catalog`, modal de crear/editar grupo en `resources/js/Pages/Modifiers/Index.vue`): se agrega icono `drag_indicator` por opción y handlers DnD para reordenar las opciones del template.
  - **Producto crear** (`resources/js/Pages/Products/Create.vue`) **y editar** (`Pages/Products/Edit.vue`): se agrega DnD tanto a la lista de **grupos de modificadores** (toda la tarjeta del grupo es arrastrable, con borde naranja al hacer hover de drop) como a la lista de **opciones dentro de cada grupo**. Los handlers de opciones llevan `.stop` para no propagar al drag del grupo padre.
  - **Persistencia**: sin cambios de backend ni de migración. El `sort_order` ya se derivaba del índice del array (`foreach ($options as $sortOrder => $optData)`) en `app/Http/Controllers/Concerns/SyncsModifierGroups.php` (productos) y `app/Http/Controllers/ModifierCatalogController.php@syncOptions` (catálogo). Reordenar el array reactivo en el frontend antes de submit es suficiente.
  - **Aislamiento por restaurante**: se hereda automáticamente. Cada `modifier_group(_template)` ya pertenece al `restaurant_id` del usuario autenticado; reordenar sólo afecta los registros de ese tenant.
  - UI: hint contextual "Arrastra los grupos o sus opciones..." cuando hay >1 grupo, "Arrastra las opciones..." cuando hay 1. Estado visual: `opacity-50` para el item arrastrado, `ring-2 ring-[#FF5722]/40` + tinte naranja para el item bajo el cursor.
- **Fix: `production_cost` vacío ya no rompe la creación/edición de productos** — El campo "Costo de producción" en `/menu/products/create` y `/menu/products/{id}/edit` causaba un server error al dejarse en blanco. Raíz: Laravel 12 no incluye `ConvertEmptyStringsToNull` en el middleware global por defecto, por lo que el string vacío `""` llegaba a la regla `numeric` de validación y fallaba (a pesar de ser `nullable`). Solución: se agrega `prepareForValidation()` en `StoreProductRequest` y `UpdateProductRequest` que normaliza `""` y `null` a `0` antes de la validación. En el frontend (`Create.vue` y `Edit.vue`) el inicializador cambia de `''` a `null` para consistencia semántica. Producción default: `$0.00`.
- **Feature: eliminar imagen de producto en edición** — La vista `/menu/products/{id}/edit` no ofrecía forma de quitar la imagen de un producto; el único camino era reemplazarla por otra. Se agrega:
  - Botón "Eliminar imagen" (rojo, aparece debajo del preview solo cuando hay imagen) en `resources/js/Pages/Products/Edit.vue`. Al pulsarlo: limpia el preview, pone `form.image = null` y activa `form.remove_image = true`. Mensaje informativo "Se usará la imagen predeterminada al guardar." aparece tras eliminación.
  - Al seleccionar una nueva imagen, `remove_image` se resetea a `false` automáticamente — no pueden coexistir ambas acciones.
  - Campo `remove_image` agregado a `UpdateProductRequest` (`nullable|boolean`).
  - En `ProductController@update`: si `remove_image=true`, borra el archivo del storage y pone `image_path = null`; si llega un archivo nuevo, aplica el reemplazo normal (`if/elseif` — mutuamente excluyentes).

### 2026-04-27

- **Branding visual unificado con logotipo de marca** — se reemplazaron los placeholders icónicos (`material-symbols/local_fire_department` + texto "PideAqui") por la imagen de marca en los puntos de contacto principales:
  - Asset agregado en `public/images/logo.png` (carpeta estándar de Laravel para estáticos servidos directamente; URL pública `/images/logo.png`).
  - **Login** (`resources/js/Pages/Auth/Login.vue`): bloque de icono + título "PideAqui" sustituido por `<img>` del logo (`h-20`), conservando el subtítulo "Panel de administración".
  - **Sidebar autenticado** (`resources/js/Layouts/AppLayout.vue`): mismo reemplazo en el header del sidebar (`h-10`), conservando el subtítulo "Panel Admin". Aplica a `/dashboard` y todas las vistas que extienden `AppLayout`.
  - **Header de correos transaccionales** (`resources/views/vendor/mail/html/header.blade.php`): el `<span>` con texto `🔥 PideAqui` cambia a `<img src="{{ asset('images/logo.png') }}">` con `height="60"` (atributo + `style` inline para máxima compatibilidad con clientes de correo). Aplica a verify-email, password reset, new order y grace expiring (todas usan el layout `vendor/mail/html`).
  - **Detalle Vite/Rollup**: el `<img src="/images/logo.png">` estático rompía `npm run build` porque `@vitejs/plugin-vue` con `transformAssetUrls.includeAbsolute=true` (default) intenta resolver rutas absolutas como imports. Solución localizada: binding dinámico `:src="'/images/logo.png'"` para que el compilador del template no lo procese y la URL se resuelva en runtime contra el origen del sitio.
  - El `SuperAdminLayout.vue` (`/super/...`) sigue con el icono antiguo intencionalmente; pendiente unificar si se decide.
- **(Cliente SPA)** Botones "back" del flujo de checkout dejan de usar `router.back()` — ver [pideaqui-front commit `4ccc39f`](https://github.com/apscreativas/pideaqui-front/commit/4ccc39f). El back del navegador podía sacar al usuario del sitio cuando llegaba desde un enlace externo (WhatsApp, redes), perdiendo el contexto del tenant. Ahora `CartSummary → /` (menú), `DeliveryLocation → /cart`, `PaymentConfirmation → /delivery`. El guard del router reescribe esas rutas legacy al slug activo. Se anota aquí para trazabilidad cross-repo.

### 2026-04-23

- **Migración del entorno de desarrollo de Laravel Sail a Laravel Herd** — PHP nativo (8.4) servido por Herd + PostgreSQL como servicio de Herd. El sitio queda en `https://pideaqui-backend.test` con TLS local automático. Impacto en docs y dependencias:
  - `composer remove --dev laravel/sail` ejecutado. `vendor/laravel/sail/` eliminado.
  - `compose.yaml` eliminado (Docker Compose residual de Sail).
  - `README.md` reescrito: requisitos previos, instalación, desarrollo, comandos frecuentes y sección de "detener entorno" convertidos a flujo Herd. Ejemplos `curl` actualizados a `https://pideaqui-backend.test`.
  - `CONTRIBUTING.md`: reglas de `./vendor/bin/sail` sustituidas por comandos directos (`php`, `composer`, `npm`, `vendor/bin/pint`).
  - `docs/OPERATIONS.md`: todos los `./vendor/bin/sail artisan` reemplazados por `php artisan`.
  - `docs/ARCHITECTURE.md`: stack actualizado (PHP 8.4, Laravel Herd 1.28+ en lugar de Laravel Sail 1.x).
  - `docs/modules/13-websockets.md`: comando de arranque de Reverb sin prefijo Sail + nota de `herd proxy` para exponerlo vía TLS local.
  - `STATUS.md`: fila de infraestructura actualizada ("Laravel Herd (dev)").
  - `GEMINI.md` resincronizado con `CLAUDE.md` (se removió el bloque obsoleto `=== sail rules ===` que Laravel Boost ya no genera al tener `"sail": false` en `boost.json`).
  - `.env.example`: `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, `DB_PASSWORD=` (vacío) — defaults de Herd PostgreSQL. `APP_URL=https://pideaqui-backend.test`.
  - Versión de PHP documentada unificada en 8.4 (antes se mezclaba 8.4 real con 8.5 en docs de deploy).
- **MCP `laravel-boost` movido a configuración global de Claude Code** (`~/.claude.json`, user scope) ejecutando `php /Users/jdportales/Herd/pideaqui-orchestator/admin/artisan boost:mcp` con ruta absoluta — ya no depende del CWD de la sesión. El `admin/.mcp.json` versionado se mantiene para otros clientes/devs.

### 2026-04-22

- **Rate limits de API pública recalibrados + 429 en español** — `routes/api.php`:
  - Grupo `public/{slug}/*` (GET): **60 → 120 req/min** (la SPA hace 3-4 fetches en boot + re-fetch en `visibilitychange`; 60 se quedaba corto con reloads).
  - `POST /delivery/calculate`: **10 → 30 req/min** (el pin del mapa se mueve varias veces en checkout).
  - `POST /coupons/validate`: **10 → 20 req/min** (permite probar varios códigos sin banear).
  - `POST /orders`: **30 req/min** (sin cambio, escritura anti-spam).
  - Nuevo handler en `bootstrap/app.php` para `ThrottleRequestsException` — retorna JSON `{code:'too_many_requests', message:'Demasiadas solicitudes...', retry_after:N}` en lugar del string inglés `Too Many Attempts.` de Laravel. Aplica cuando `$request->is('api/*')` o `expectsJson()`.
  - Cliente: `PaymentConfirmation.vue` ahora lee `err.response?.data?.message` en el catch del cupón (ya lo hacía en order submit y DeliveryLocation).
- **Self-signup público de restaurantes** — nueva ruta `/register` (guest + `throttle:3,1`). Flujo: valida con `RegisterRestaurantRequest` (restaurant_name, admin_name, email lowercase, password `min:8`+letters+mixedCase+numbers), delega a `RestaurantProvisioningService`, dispara `event(new Registered($admin))`, `Auth::login()`, redirige a `/email/verify`. 11 tests nuevos (`tests/Feature/Auth/RegisterTest.php`).
- **`RestaurantProvisioningService` + DTO `ProvisionRestaurantData`** — orquestador único de provisioning. Envuelve en `DB::transaction`: Restaurant → User (`restaurant_id` directo, `role='admin'`) → 3 PaymentMethod stub → BillingAudit (`actor_type=source`). Reutilizado por SuperAdmin (`RestaurantController@store` pasa `source='super_admin'`) y `RegisterController` (`source='self_signup'`). Soporta `billing_mode='grace'|'manual'`. 13 tests unit (`tests/Unit/RestaurantProvisioningServiceTest.php`), incluyendo rollback cuando falla el audit.
- **Email verification obligatoria solo para self_signup**:
  - `User implements Illuminate\Contracts\Auth\MustVerifyEmail` (el trait ya estaba via `Illuminate\Foundation\Auth\User`).
  - Migración `2026_04_22_100039_backfill_email_verified_at_on_users` marca todos los users existentes como verified (`email_verified_at = created_at`).
  - Service setea `email_verified_at=now()` cuando `source='super_admin'` — admins creados por SuperAdmin entran sin fricción.
  - `Auth\VerifyEmailController` (notice/verify/send) + `Pages/Auth/VerifyEmail.vue` + rutas `/email/verify*` bajo grupo `auth` (sin `verified` para que el user pueda verificar o logout).
  - Grupo admin ahora requiere middleware `['auth','verified','tenant']`. Logout movido al grupo `auth` solo.
  - `LoginController::store` redirige a `verification.notice` si `!user->hasVerifiedEmail()`.
  - Botón "Enviar correo de verificación" en `SuperAdmin/Restaurants/Show.vue` — envía el correo voluntariamente sin desverificar al admin. Audit entry `verification_email_sent_manually`.
  - 10 tests (`tests/Feature/Auth/EmailVerificationTest.php`).
- **Correo de verificación en español con branding naranja** — `App\Notifications\VerifyEmailNotification` extiende `Illuminate\Auth\Notifications\VerifyEmail` y override `toMail()`. Subject `Verifica tu correo — PideAqui`, greeting `¡Bienvenido a PideAqui!`, action text `Verificar mi correo`. User::sendEmailVerificationNotification() override.
- **Columna `restaurants.signup_source`** (migración `2026_04_22_095705`) — values `super_admin|self_signup`. Backfill a `super_admin` para históricos. Índice. RestaurantFactory gana estados `selfSignup()` y `grace()`.
- **Columna `restaurants.access_token` eliminada** (migración `2026_04_22_122940_drop_access_token_from_restaurants_table`). Todas las referencias removidas de Restaurant model, RestaurantFactory y RestaurantProvisioningService. La SPA universal resuelve tenant exclusivamente por slug — ya no se necesita token por restaurante.
- **SuperAdmin Dashboard — Tab "Alertas" accionables** (`DashboardController@index`):
  - 4 KPIs nuevos en `alerts`: `grace_expiring_soon` (≤3 días), `orders_near_limit` (≥80%), `billing_manual` (activos), `new_this_week` (split self_signup/super_admin).
  - Todos aplican filtro `is_active=true`.
  - `orders_near_limit` usa el scope `Restaurant::withPeriodOrdersCount()` para batch query.
  - Frontend: 8 cards click-through (4 accionables + 4 de estado general) — cada una navega a `Restaurants/Index?alert=...`.
- **Filtros en SuperAdmin/Restaurants/Index** — `?alert=grace_expiring|orders_near_limit|billing_manual|new_this_week|past_due|grace_period|suspended|no_subscription`. Filtros combinables con `?status=0|1`. Banner arriba de la tabla cuando hay filtro activo con botón "Limpiar filtro". Pills de filtros rápidos para los 4 accionables. Badges inline por row: `Gracia Nd`, `80%+`, `Manual`.
- **Fix N+1 en SuperAdmin/Restaurants/Index** — `period_orders_count` ahora usa el scope `Restaurant::withPeriodOrdersCount()` con subquery correlacionado (`SELECT COUNT(*) FROM orders WHERE restaurant_id = restaurants.id AND created_at BETWEEN orders_limit_start AND orders_limit_end`). Antes: 1+N queries por página, ahora: 2 queries.
- **Redesign SuperAdmin/Restaurants/Show.vue** — hero con pills inline (status + slug + modo + plan + origen + fecha + id) + KPI row horizontal (Pedidos, Sucursales, Gracia con urgencia visual, Stripe) + grid que ahora vive sin el card de Access Token (removido): main 3/5 (Admin, Plan y límites) + side 2/5 (QR grande, URL pública, rename slug inline con SlugInput + checkbox de confirmación). Mejor densidad horizontal.
- **Desmantelamiento completo de API pública legacy** — API ahora es exclusivamente `/api/public/{slug}/*`:
  - Middleware `AuthenticateRestaurantToken` eliminado. Alias `auth.restaurant` eliminado de `bootstrap/app.php`.
  - Grupo de rutas legacy `/api/restaurant`, `/api/menu`, `/api/branches`, `/api/orders`, `/api/delivery/calculate`, `/api/coupons/validate` eliminadas.
  - `SuperAdmin\RestaurantController@regenerateToken` + ruta `POST /super/restaurants/{id}/regenerate-token` eliminados.
  - Card "Access Token (API)" + modal de regeneración + refs (`showToken`, `showRegenerateModal`, `regenerating`) + funciones (`copyToken`, `regenerateToken`) removidos de `SuperAdmin/Restaurants/Show.vue`.
  - `RestaurantProvisioningService::generateAccessToken()` eliminado. Factory, seeder, controllers y tests purgados.
  - `tests/Feature/ApiTest.php` reescrito para usar `/api/public/{slug}/*`. Tests obsoletos de token auth (`test_requests_without_token_return_401`, etc.) reemplazados por `test_unknown_slug_returns_404`. 13 archivos de test actualizados con perl/python scripts para cambiar `authHeaders($r)` → URL con slug.
  - Cliente SPA: `VITE_RESTAURANT_TOKEN` removido de `.env`/`.env.example`. Feature flag `VITE_MULTI_TENANT_MODE` eliminado (modo universal es el único). Router forzado a `createWebHistory()`. `src/services/api.js` simplificado (sin ramas condicionales). Stores, cookies, storage y router sin código legacy.
- **Sistema de slugs con UX consciente**:
  - Tabla nueva `platform_settings` (key/value cacheado con `Cache::rememberForever`) y modelo `App\Models\PlatformSetting` con API `::get/set/forget`.
  - `config/tenants.php` — regex del slug, min/max length, lista de 42 `reserved_slugs` (admin, super, api, webhook, stripe, r, b, cart, delivery, etc.) extensible sin deploy.
  - `App\Rules\ValidSlug` — rule reutilizable. Formato + reserved + longitud. No valida unicidad (se combina con `Rule::unique`).
  - `App\Services\SlugSuggester` — `sanitize`, `generateUnique`, `suggest`, `isTaken`, `isReserved`. Retry 1x con slug auto-generado en `RestaurantProvisioningService` si colisión (QueryException unique violation).
  - Endpoint público `GET /api/slug-check?slug=x` (`SlugCheckController`, `throttle:120,1`) retorna `{available, reason?: 'taken'|'reserved'|'invalid_format', message, suggestions[]}`. Compartido por self-signup y SuperAdmin.
  - SuperAdmin: nueva página `/super/platform-settings` (`PlatformSettingsController`) para editar `public_menu_base_url`. Rename de slug via `PATCH /super/restaurants/{id}/slug` con `UpdateRestaurantSlugRequest` (requiere checkbox `confirm`), audita `restaurant_slug_renamed` con `{old_slug, new_slug}`.
  - UI: componentes reutilizables `SlugInput.vue` (debounce 500ms + caché + badge estado + sugerencias clickeables + badge `throttled` como soft-fail sin bloquear submit) y `QrCode.vue` (canvas 200×200 con `qrcode` npm, expone `download()`).
  - Admin `Settings/General.vue`: card "Tu enlace público" con QR + URL + botones Copiar / Descargar PNG. Admin NO puede renombrar — solo SuperAdmin.
  - Inertia comparte `menu_base_url` globalmente via `HandleInertiaRequests` para construir URLs consistentes.
  - 25 tests nuevos: `SlugCheckTest` (9), `PlatformSettingTest` (6), `SlugProvisioningTest` (10).
- **Rename policy oficial para slug**: admin panel NO puede renombrar (evita romper QR impresos accidentalmente). SuperAdmin puede con modal de advertencia explícita. Sin redirect del slug viejo (404 inmediato).
- **Política `status=suspended` documentada**: `ARCHITECTURE.md §2.7` + `docs/modules/17-billing.md`. Restaurante suspendido NO opera (API 410, manual/POS bloqueados por `canOperate()`) pero SÍ puede preparar (editar catálogo, branding, horarios, cupones, promociones). Decisión intencional — reduce fricción de reactivación.
- **Rate limits de API pública recalibrados + 429 en español** — `routes/api.php`:
  - Grupo `public/{slug}/*` (GET): **60 → 120 req/min** (la SPA hace 3-4 fetches en boot + re-fetch en `visibilitychange`; 60 se quedaba corto con reloads).
  - `POST /delivery/calculate`: **10 → 30 req/min**.
  - `POST /coupons/validate`: **10 → 20 req/min**.
  - `POST /orders`: **30 req/min** (sin cambio).
  - `GET /api/slug-check`: **20 → 120 req/min** (UX del SlugInput tipea 1 check por keystroke debounce 500ms).
  - Handler global en `bootstrap/app.php` para `ThrottleRequestsException` — JSON `{code:'too_many_requests', message, retry_after}` en español + header `Retry-After`.
- **Universal SPA client hardening (R1-R4 + §2.1 + §2.2)** — auditorías identificaron riesgos de contaminación cross-tenant. Fixes aplicados en repo `client/`:
  - **R2**: `AbortController` tenant-scoped en `src/services/api.js`. `abortTenantRequests(slug)` cancela todos los fetches en vuelo al cambiar de tenant. Signal inyectado en cada request automáticamente.
  - **R3**: `router.beforeEach` async bloqueante (`src/router/index.js`) — aborta, hidrata cart/order, awaita `bootstrapTenant()`. Navegación no completa hasta que los stores están hidratados, eliminando el flash del tenant anterior. App.vue simplificado (quitado `watch(route.params.slug)`).
  - **R1**: Slug guard en `bootstrapTenant()` (`src/stores/restaurant.js`) — `requestedSlug` capturado al inicio, comparación contra `currentSlug.value` antes de cada mutación de estado (try/catch/finally). Late responses del tenant anterior se descartan.
  - **R4**: Guard en `watch()` de `cart.js` y `order.js` — solo persisten si `activeSlug === currentSlugFromLocation()`. Previene que writes debounced del tenant anterior contaminen la key del nuevo tenant.
  - **§2.1**: `<RouterView :key="route.params.slug">` en `App.vue` — fuerza remount de vistas al cambiar slug. Resetea refs locales (`searchQuery`, `activeCategory`, `selectedProduct` en MenuHome) que Vue Router por default preserva al reusar la instancia del componente.
  - **§2.2**: `PaymentConfirmation.vue` revalida automáticamente (`onMounted`) cualquier cupón persistido en el store contra `/api/public/{slug}/coupons/validate`. Si responde inválido (expirado, max_uses, o código de otro tenant por defensa en profundidad), limpia el cupón silenciosamente. Evita mostrar descuento fantasma.

### 2026-04-17

- **Documentación reorganizada**: docs se migraron al repo `admin/` (anterior carpeta `docs/` raíz del workspace eliminada). `ARCHITECTURE.md`, `PRD.md`, `DATABASE.md` consolidados bajo `admin/docs/`.
- **Landing page** (Nuxt 4) creada en repo independiente `landing-pideaqui`.

### 2026-04-15

- **Auto-generación de slug en SuperAdmin**: `RestaurantController::generateUniqueSlug()` aplica `Str::slug()` + sufijo `-2/-3` ante colisiones. Input manual de slug removido. Columna `slug` preservada (UNIQUE, expuesta en `RestaurantResource`). Tests actualizados.
- **Settings General sin redes sociales**: inputs de Instagram/Facebook/TikTok removidos de `Settings/General.vue`. Columnas DB preservadas (nullable, sin uso). 29 tests de Settings verdes.
- **Hardening Stripe**:
  - Deduplicación de webhooks vía tabla `stripe_webhook_events` (unique constraint).
  - `startGracePeriod()` cancela suscripción Stripe activa con `cancelNow()` antes de forzar gracia.
  - `SubscriptionController::checkout` rechaza restaurantes en modo manual o ya suscritos.
  - Fallback subscription-sin-plan genera `BillingAudit` además del log.
  - 9 tests nuevos en `StripeWebhookTest`. Total: 614 tests.
- **Gate operacional para canales internos**: `Restaurant::canOperate()` bloquea creación manual/POS cuando status ∈ {suspended, disabled, past_due, incomplete} o período manual vencido/no iniciado. POS preserva cerrar ventas en curso. **No bloquea por `orders_limit`.** Helper `BillingMessages` para textos. Banners rojos + botones disabled en UI. 605 tests pasando (+36).

### 2026-04-14

- **Escalabilidad POS + Cancelaciones**: cursor pagination POS, paginador clásico en Cancelaciones. KPIs en 1 query agregada. Fix `byBranch` con filtro de sucursal. Eliminación N+1. 3 índices nuevos (`orders(restaurant_id,cancelled_at)`, `pos_sales(restaurant_id,cancelled_at)`, `pos_payments(pos_sale_id,payment_method_type)`). 569 tests pasando.

### 2026-04-13

- **Módulo POS (caja mostrador)**: entidad separada (`pos_sales`, `pos_sale_items`, `pos_sale_item_modifiers`, `pos_payments`), pagos mixtos, ticket imprimible, Kanban POS, reporte de ventas. No consume `orders_limit`. Broadcast en canal aislado `restaurant.{id}.pos`.
- **Pedidos manuales desde Tablero admin**: campo `orders.source` = `api | manual`. Admin puede crear pedido sin pasar por SPA del cliente.

---

## Marzo 2026

### 2026-03-24 — Edición de pedidos post-creación

- Admins pueden editar pedidos en status `received` o `preparing`. Bloqueado en `on_the_way/delivered/cancelled`.
- Tabla nueva `order_audits` (action, changes JSON, reason, totales antes/después, IP, user_id).
- `OrderEditService` con `lockForUpdate` + optimistic lock vía `expected_updated_at` (409 Conflict si stale).
- Tres tipos de cambio: items, dirección, método de pago.
- `OrderUpdated` broadcast event (ShouldBroadcastNow).
- `UpdateOrderRequest`, `OrderPolicy::edit()`, página `Orders/Edit.vue`, historial collapsible en Show, badge "editado" en Index.
- 29 tests nuevos, 342 total.

### 2026-03-24 — Cupones de descuento por restaurante

- 2 tablas: `coupons` + `coupon_uses`. 3 columnas en `orders`: `coupon_id` (FK nullable), `coupon_code` (string snapshot), `discount_amount`.
- Fórmula: `total = subtotal - discount_amount + delivery_cost`. Descuento aplica SOLO al subtotal.
- `discount_amount` calculado server-side (anti-tampering).
- Admin CRUD con toggle, delete modal. API `POST /api/coupons/validate`.
- OrderEditService recalcula descuento al editar items (remueve si `min_purchase` no se cumple).
- Un cupón por pedido. Tracking por `customer_phone` (sin cuentas).
- Cancelled orders mantienen `coupon_use` (no se libera).
- 40 tests nuevos, 382 total.

### 2026-03-23 — Catálogo de modificadores reutilizables

- Sistema híbrido: inline (HasMany per producto) + catálogo a nivel restaurante.
- 3 tablas nuevas: `modifier_group_templates`, `modifier_option_templates`, `product_modifier_group_template` (pivot).
- Columnas agregadas: `is_active`, `max_selections` en `modifier_groups`/`modifier_options`.
- `Product::getAllModifierGroups()` mergea ambas fuentes con campo `source`.
- API acepta `modifier_option_id` (inline) o `modifier_option_template_id` (catalog). `order_item_modifiers` sin cambios — snapshot preserva datos.
- 269 tests (17 nuevos).

### 2026-03-23 — Fechas especiales y días festivos

- Tabla `restaurant_special_dates` con `date`, `type` (closed|special), opens_at, closes_at, label, is_recurring.
- `Restaurant::getResolvedScheduleForDate(Carbon)` con cadena de prioridad: special_date > regular schedule. Recurring matchea mes+día.
- API expone `closure_reason`, `closure_label`, `today_schedule`, `upcoming_closures`.
- OrderService valida `scheduled_at` contra horario resuelto.
- Admin Settings/Schedules con sub-sección + modal CRUD + botón "Festivos comunes" (7 holidays mexicanos).
- 16 tests nuevos.

### 2026-03-18 — Promociones rediseñadas

- **Cambio de modelo**: las promociones ya NO son descuentos sobre productos. Son items standalone con name, description, price, production_cost, active_days, starts_at/ends_at, is_active, sort_order.
- Eliminada tabla pivote `promotion_product`. Eliminados campos `discount_type`, `discount_value`.
- Agregado `promotion_id` FK nullable en `order_items` (`product_id` ahora nullable). Un order_item tiene product_id O promotion_id.
- API retorna promos en categoría virtual "Promociones" con `is_promotion: true`.
- `Cart.js` detecta `product.is_promotion` para enviar `promotion_id`.
- Componente `TimePicker.vue` reutilizable.

### 2026-03-13 — Snapshot histórico + Haversine fallback eliminado

- `order_items`: columnas `product_name`, `production_cost`. `order_item_modifiers`: `modifier_option_name`, `production_cost`.
- `StatisticsService::netProfit()` usa snapshot (no joins a tablas live).
- WhatsApp message usa snapshot names.
- `DeliveryService`: 1 sucursal → 1 Google call; 2+ → Haversine pre-filtra TOP 1 → 1 Google call. **Sin fallback a Haversine** — lanza `DomainException` si Google falla.
- Orders/Show.vue muestra `distance_km`, ganancia por item (verde), ganancia neta total.

### 2026-03-12 — Cancelaciones + WebSockets + Mapa + Email

- **Cancelaciones**: flujo completo backend + frontend con modal, razones predefinidas, columna en Kanban.
- **Reporte de Cancelaciones**: `CancellationService`, `CancellationController`, página con KPIs, motivos, por sucursal, por día, tabla. Sidebar con icon `cancel`. 12 tests.
- **WebSockets**: Laravel Reverb + Echo + pusher-js. 3 eventos (`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`). Canal privado `restaurant.{id}`. `BROADCAST_CONNECTION=reverb` en `.env`, `null` en `phpunit.xml`. 12 tests de broadcasting.
- **Mapa operativo**: `MapController`, `Map/Index.vue` con Google Maps JS API, markers coloreados por status, filtros, KPIs, info windows. 13 tests.
- **Email `NewOrderNotification`**: trait `Notifiable` + `routeNotificationForMail()` en Restaurant. Toggle `notify_new_orders`. 7 tests.
- **Multiple modifier selection** fix en cliente + validación cardinalidad backend.
- **Kanban UX**: horario programado + icono de pago en cards. Cliente: footer sticky, favicon dinámico, logo sin recorte.
- **Menú DnD reorder**: HTML5 nativo en Menu/Index.vue. `sort_order` auto en `max+1`. 10 tests nuevos en MenuTest.
- **Broadcast decoupling**: admin envuelve `broadcast()` en try/catch. Si Reverb cae, status se guarda igual.

### 2026-03-02 — Horarios por restaurante + Auditoría de seguridad

- **Horarios a nivel restaurante** (no sucursal): tabla `restaurant_schedules`. `Restaurant::isCurrentlyOpen()` con soporte overnight. API retorna `is_open` + `schedules`. DeliveryService usa `RestaurantSchedule`.
- **QR y token removidos del admin** (solo visibles en SuperAdmin).
- **Kanban drag-and-drop** HTML5 nativo con optimistic UI + validación transiciones.
- **Modifiers per-product** (inline): `modifier_groups.product_id` FK, `modifier_options.production_cost`. Pivote eliminado. Gestión inline en Products/Create y Edit.
- **Sucursal activa obligatoria**: `BranchController` valida no desactivar/eliminar la última activa.
- **Client SPA**: WhatsApp con Google Maps links, GPS error handling, time slots sin cap de 12, estado cerrado visual.
- **Payment method guard**: `UpdatePaymentMethodRequest` valida mínimo 1 método activo.
- **SuperAdmin tema claro**: sidebar y login unificados con estilo admin (bg-white). Filtros con `bg-[#FF5722]`.
- **Límites por periodo**: `max_monthly_orders` → `orders_limit` + `orders_limit_start/end` (date). `LimitService::limitReason()` retorna `null | period_not_started | period_expired | limit_reached`. API retorna `limit_reason`.
- **Auditoría de seguridad** (13+ fixes críticos):
  - Delivery cost server-side
  - TOCTOU fix con `lockForUpdate()`
  - Required modifiers validation
  - Inactive branch check
  - IDOR category_id scoped
  - Rate limiting login (`throttle:5,1`)
  - Password cast `hashed` (idempotente)
  - SVG uploads bloqueados (`mimes:jpeg,jpg,png,gif,webp`)
  - Overnight schedules soportados
  - Rate limiting API orders (`throttle:30,1`)
  - Cross-product modifier validation per-item
  - Duplicate `modifier_option_id` rechazado (`distinct`)
- `StoreOrderRequest` caps: `items max:50`, `quantity max:100`, `scheduled_at after:now`, `unit_price max:99999.99`.

---

## Febrero 2026

### 2026-02-27 (aprox.) — MVP completado

- Fases 1–10 entregadas. Backend Laravel + admin Inertia + SuperAdmin + cliente SPA + API pública.
- Integraciones: Google Maps JS, Google Distance Matrix, WhatsApp wa.me, S3, SMTP.
- Multi-tenancy row-level con `TenantScope` + `EnsureTenantContext` + 8 Policies.
- DeliveryService orquesta Haversine + Distance Matrix.
- OrderService con validación de límites, anti-tampering, WhatsApp message generation.
- Ziggy para `route()` en Vue.

---

_CHANGELOG — PideAquí Backend — Consolidado Abril 2026_
