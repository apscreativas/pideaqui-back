# PideAquí — Roadmap

> Reemplaza el antiguo `PHASES.md` (Feb 2026, lineal por fases MVP) con el mapa real del producto post-MVP.
> Última actualización: Abril 2026.

---

## Cómo leer este documento

El MVP se completó en tres bloques lineales (DB/auth → dominio → API + SPA). Lo que vino después fue iterativo, guiado por necesidad. Por eso este roadmap ya no es una lista de fases secuenciales sino:

1. **MVP (completado)** — columna vertebral del producto, ya en producción.
2. **Post-MVP implementado** — features que entraron después y también están en producción.
3. **Backlog priorizado** — trabajo pendiente (requiere actualizarse por quien toma ownership).

---

## 1. MVP (completado — Febrero–Marzo 2026)

| # | Bloque | Módulos cubiertos | Referencia |
|---|---|---|---|
| 1 | DB + modelos del dominio | 13 tablas base | [DATABASE.md](./DATABASE.md) §1–17 |
| 2 | Autenticación + multi-tenancy | `web` y `superadmin` guards, policies | [01-auth.md](./modules/01-auth.md), [07-superadmin.md](./modules/07-superadmin.md) |
| 3 | Panel admin — menú y sucursales | Categorías, productos, modificadores, sucursales + horarios | [04-menu.md](./modules/04-menu.md), [05-branches.md](./modules/05-branches.md) |
| 4 | API pública — lectura | `GET /api/restaurant`, `/menu`, `/branches` | [10-api.md](./modules/10-api.md) |
| 5 | DeliveryService | Haversine pre-filtro + Distance Matrix | [09-delivery-service.md](./modules/09-delivery-service.md) |
| 6 | API pública — creación de pedido | `POST /api/delivery/calculate`, `POST /api/orders` | [10-api.md](./modules/10-api.md) |
| 7 | Panel admin — pedidos + dashboard | Kanban, detalle, advance status, KPIs | [03-orders.md](./modules/03-orders.md), [02-dashboard.md](./modules/02-dashboard.md) |
| 8 | Panel admin — configuración | General, delivery methods, shipping rates, payment methods, profile | [06-settings.md](./modules/06-settings.md) |
| 9 | Panel SuperAdmin | CRUD restaurantes, toggle, límites | [07-superadmin.md](./modules/07-superadmin.md) |
| 10 | Cliente SPA | `c_01` → `c_06` en Vue 3 + Pinia | [08-customer-flow.md](./modules/08-customer-flow.md) |

**Entregable MVP:** 382 tests pasando, flujo completo cliente → admin → WhatsApp, despliegue inicial.

---

## 2. Post-MVP implementado

Ordenado por orden cronológico de entrega. Cada ítem vive en producción.

### 2.1 Febrero — Marzo 2026

- **Horarios por restaurante** (no por sucursal). Tabla `restaurant_schedules`, `Restaurant::isCurrentlyOpen()`, soporte overnight. Cliente muestra banner cerrado + menú grayscale.
- **Catálogo/QR removido del admin** (queda solo en SuperAdmin).
- **Modifiers inline (per-product)**: se eliminó el pivote many-to-many. Cada producto tiene su `modifier_groups` vía HasMany. `modifier_options.production_cost` agregado.
- **Sucursal activa obligatoria**: no se puede desactivar/eliminar la última sucursal activa.
- **Límite de pedidos por período configurable**: `orders_limit_start/end` (date). `LimitService::limitReason()` retorna `period_not_started | period_expired | limit_reached | null`.
- **Auditoría de seguridad** (13+ fixes): rate limiting, hashed password cast, SVG bloqueado, required modifiers, IDOR category_id, dedup `modifier_option_id distinct`, cross-product modifier validation.
- **Cancelaciones como módulo**: `CancellationService` + página admin con KPIs, desglose por razón/sucursal/día. [11-cancellations.md](./modules/11-cancellations.md).
- **WebSockets** con Laravel Reverb + Echo. 3 eventos (`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`) en canal `restaurant.{id}`. [13-websockets.md](./modules/13-websockets.md).
- **Mapa operativo** interactivo con markers por status. [12-map.md](./modules/12-map.md).
- **Snapshot histórico** en `order_items` y `order_item_modifiers` (product_name, production_cost, modifier_option_name). `StatisticsService::netProfit()` usa snapshot.
- **Google Maps driving distance en todos los flujos** (se eliminó fallback a Haversine).
- **Menú DnD reorder** (HTML5 nativo). `sort_order` automático.
- **Email de nuevos pedidos** con toggle `notify_new_orders`.
- **Promociones standalone** (no descuento sobre productos existentes). Tabla `promotions`, `order_items.promotion_id`. [15-promotions.md](./modules/15-promotions.md).
- **Fechas especiales y días festivos**: `restaurant_special_dates`, `Restaurant::getResolvedScheduleForDate()`. 7 holidays mexicanos como preset.
- **Catálogo de modificadores reutilizables** (sistema híbrido inline + catálogo). [04-menu.md](./modules/04-menu.md) §"Catálogo de Modificadores".

### 2.3 Abril 2026 (segunda quincena)

- **Self-signup público** (`/register`, `throttle:3,1`) — cualquier dueño de restaurante puede registrarse y obtener plan de gracia (14 días, 50 pedidos, 1 sucursal). Requiere verificación obligatoria de correo. SuperAdmin crea pre-verificados. [01-auth.md](./modules/01-auth.md).
- **`RestaurantProvisioningService`** — orquestador único del onboarding reutilizado por SuperAdmin manual y self-signup.
- **Email verification custom** con `VerifyEmailNotification` en español + branding naranja. User `implements MustVerifyEmail`. Backfill one-shot para usuarios existentes.
- **Columna `restaurants.signup_source`** para diferenciar `super_admin` vs `self_signup`.
- **SuperAdmin Dashboard — Tab "Alertas accionables"** con 8 cards click-through (gracia ≤3d, ≥80% límite, modo manual, nuevos 7d, past_due, grace total, suspended, sin subscription). Filtros `?alert=...` en Restaurants/Index con banner + badges inline. Fix N+1 con scope `Restaurant::withPeriodOrdersCount()`.
- **Redesign de `SuperAdmin/Restaurants/Show.vue`** con hero inline + KPI row horizontal + grid 3/2 para mejor densidad.
- **Universal SPA** — bundle único sirve a todos los restaurantes (tenant resuelto por URL `/r/{slug}`). Columna `access_token` eliminada. Hardening cross-tenant: `AbortController` por tenant, `router.beforeEach` bloqueante, slug guards en stores. Ver [STATUS.md](../STATUS.md) §"Multi-tenant SPA universal".

### 2.2 Marzo — Abril 2026

- **Edición de pedidos post-creación** con audit trail. Bloqueado en `on_the_way/delivered/cancelled`. Optimistic lock vía `expected_updated_at` (409 Conflict si stale). `OrderEditService`, tabla `order_audits`, evento `OrderUpdated`. [03-orders.md](./modules/03-orders.md) §"Edición Post-Creación".
- **Cupones de descuento por restaurante** (fixed/percentage, min_purchase, max_uses_per_customer, max_total_uses). Validación server-side anti-tampering. [16-coupons.md](./modules/16-coupons.md).
- **Pedidos manuales desde el Tablero** con `orders.source = 'manual'`. [03-orders.md](./modules/03-orders.md) §"Pedidos Manuales".
- **Módulo POS (caja)** — entidad separada, pagos mixtos, ticket imprimible, Kanban POS. No consume `orders_limit`. [14-pos.md](./modules/14-pos.md).
- **Historial POS y Cancelaciones — escalabilidad**: cursor pagination POS, paginador clásico Cancelaciones, KPIs en 1 query, 3 índices nuevos.
- **Gate operacional** (`Restaurant::canOperate()`) para canales internos (manual orders + POS). Bloquea por status no operacional o período vencido, no por `orders_limit`. [17-billing.md](./modules/17-billing.md) §"Gate operacional".
- **Billing SaaS con Stripe + Laravel Cashier**: modo `manual` vs `subscription`, planes, webhooks (6 eventos), checkout, portal, cron jobs, grace period. [17-billing.md](./modules/17-billing.md).
- **Hardening Stripe**: dedup webhooks con `stripe_webhook_events`, `startGracePeriod` cancela Stripe, fallback sub-sin-plan con audit.
- **Slug auto-generado en SuperAdmin** (Str::slug + sufijo `-2`/`-3` al colisionar).
- **Settings General sin redes sociales** (inputs removidos, columnas DB preservadas).
- **Gastos operacionales** (módulo 18): CRUD con adjuntos, categorización jerárquica de dos niveles, integración con utilidad neta del dashboard. [18-expenses.md](./modules/18-expenses.md).
- **Landing page independiente** en Nuxt 4 + Vercel (repo `landing-pideaqui`).

---

## 3. Backlog priorizado

> **Nota**: esta sección debe mantenerla viva quien lidere el producto. Marcada hoy con el inventario que tengo a la vista.

### Alta prioridad

- **Facturación fiscal (CFDI)** — actualmente solo recibos de Stripe. Requerido para clientes empresariales en México. Ver [BILLING_SPEC.md](./BILLING_SPEC.md) §"Facturación fiscal".
- **Monitoreo y observabilidad** — no hay instrumentación formal (logs estructurados, Sentry, métricas). Se detecta solo por reportes.
- **Tests de integración del cliente SPA** — cobertura 0% hoy (solo tests backend). Riesgo de regresiones en checkout, especialmente en los fixes cross-tenant de universal SPA (R1-R4 del hardening Abr 22).
- **Captcha en `/register`** — hoy solo throttle 3/min + email verification. Considerar reCAPTCHA v3 o hCaptcha si aparece spam.
- **Cancelar cuenta (baja self-service)** — hoy no existe, solo SuperAdmin puede desactivar.

### Media prioridad

- **Unificación de modelos `Order` y `PosSale`** — hoy viven en tablas separadas y se consolidan solo en reportes. Evaluar si conviene converger.
- **Internacionalización**: el producto está hardcodeado en español MX. `APP_TIMEZONE=America/Mexico_City`, moneda MXN. Si se expande a otro país, requiere i18n + multi-currency en Cashier.
- **Panel del operator más rico** — hoy operators casi no tienen features dedicadas fuera de ver pedidos.
- **Historial de precios por producto** — hoy solo hay snapshot en `order_items`. No hay timeline de cambios a nivel catálogo.

### Baja prioridad / investigación

- ~~**Multi-restaurante por build en SPA**~~ — ✅ **Completado Abr 2026**: un único build universal sirve a todos los restaurantes (resolución runtime por URL `/r/{slug}`).
- **Reverb gestionado vs propio** — evaluar costo/beneficio de Laravel Cloud WebSockets vs Reverb en systemd.
- **Rework completo de `STATUS.md`** — ya lo consolidamos en `CHANGELOG.md`, pero faltaría automatizar la generación desde commits.
- **TTL / re-fetch en menú** — hoy el menú se fetcha una vez por `bootstrapTenant`. Si el admin desactiva un producto a mitad de checkout, el SPA no se entera hasta que el user re-fetcha manualmente o navega fuera y regresa. Backend rechaza el order, pero UX mejorable. Agregar TTL 5-10 min o re-fetch en `visibilitychange`.
- **Landing link a `/register`** — hoy landing Nuxt no tiene CTA a registro público. Agregar cuando haya copy de marketing listo.

---

## 4. Qué cambió respecto al antiguo `PHASES.md`

El anterior documento estructuraba el trabajo en 13 fases secuenciales con dependencias explícitas. Eso tuvo sentido durante el MVP, cuando construir el esqueleto era el trabajo entero. Hoy el producto es un sistema vivo con features paralelas y no tiene sentido seguir hablando de "fases 14, 15, 16…". Por eso:

- El inventario MVP queda preservado (sección 1) como referencia histórica.
- Las features post-MVP se documentan en orden cronológico (sección 2), cada una enlazada a su módulo.
- El backlog (sección 3) es el único contrato con el futuro — el resto son hechos.

---

_PideAquí — Roadmap v1.1 — Abril 2026_
