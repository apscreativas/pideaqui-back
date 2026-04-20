# PideAquí — Estado Actual

> Snapshot del estado del producto al **17 de abril de 2026**.
> Para el historial cronológico detallado, ver [CHANGELOG.md](./CHANGELOG.md).

---

## Resumen

- **Versión del producto:** v3.0 (MVP + post-MVP completo en producción)
- **Branch activa:** `main` (existen también `pagos_stripe`, `test` como rastro histórico)
- **Stack:** Laravel 12 + PostgreSQL 18 + Inertia + Vue 3 + Reverb + Stripe (Cashier)
- **Tests:** **619 funciones `test_*` en 31 archivos** (conteo auditable al 2026-04-17)

---

## Estado por área

| Área | Estado | Referencia |
|---|---|---|
| Infraestructura + Docker Sail | ✅ Estable | [README.md](./README.md) |
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
| Panel SuperAdmin — Restaurantes, planes, billing settings | ✅ Estable | [docs/modules/07-superadmin.md](./docs/modules/07-superadmin.md) |
| API pública REST (7 endpoints, 3 rate-limited) | ✅ Estable | [docs/modules/10-api.md](./docs/modules/10-api.md) |
| DeliveryService (Google + Haversine pre-filtro) | ✅ Estable | [docs/modules/09-delivery-service.md](./docs/modules/09-delivery-service.md) |
| WebSockets (Reverb + Echo, 7 eventos en 2 canales) | ✅ Estable | [docs/modules/13-websockets.md](./docs/modules/13-websockets.md) |
| Billing SaaS (Stripe + Cashier + gate operacional) | ✅ Estable | [docs/modules/17-billing.md](./docs/modules/17-billing.md) |
| Cliente SPA (repo `pideaqui-front`) | ✅ Estable | [docs/modules/08-customer-flow.md](./docs/modules/08-customer-flow.md) |
| Landing page (repo `landing-pideaqui`) | ✅ Estable | `../landing/README.md` |

---

## Trabajo en curso

Commit history reciente muestra modificaciones sin commitear en:

- `ExpenseController` + `ExpenseTest` (afinación del módulo 18)
- `CouponController` + `Coupons/Index.vue` (mantenimiento)
- `SettingsController` + `UpdateGeneralSettingsRequest` + `Settings/General.vue` (ajustes de settings)
- `SuperAdmin/RestaurantController` + `CreateRestaurantRequest` (ajustes SuperAdmin)
- `BillingCommandsTest`, `SuperAdminTest` (actualizaciones de tests)

Ver `git status` para el delta exacto.

---

## Próximos pasos sugeridos

No hay deadlines duros activos. Prioridades identificadas en [ROADMAP.md](./docs/ROADMAP.md) §3:

**Alta prioridad:**
- Facturación fiscal (CFDI) — requerido para clientes empresariales
- Monitoreo/observabilidad (Sentry + logs estructurados)
- Tests de integración del SPA cliente (cobertura 0% hoy)

**Media prioridad:**
- Rotación automática de `access_token`
- i18n + multi-currency si se expande fuera de México

---

_Estado — PideAquí Backend — 2026-04-17_
