# PideAquí — Índice de Documentación

Punto único de entrada para toda la documentación del backend. Si llegaste aquí primera vez, empieza por [PRD](./PRD.md) → [ARCHITECTURE](./ARCHITECTURE.md) → [modules/INDEX](./modules/INDEX.md).

---

## Documentos globales

| Documento | Para qué | Cuándo leerlo |
|---|---|---|
| [PRD.md](./PRD.md) | Requerimientos del producto (v3.0 MVP + post-MVP) | Onboarding, decisiones de scope |
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Decisiones técnicas actuales (v3.0 Abril 2026) | Antes de tocar servicios, middleware o schema |
| [ROADMAP.md](./ROADMAP.md) | Fases MVP + post-MVP implementado + backlog | Cuando planees nuevo trabajo |
| [DATABASE.md](./DATABASE.md) | Esquema completo (18 tablas dominio + billing + Cashier) | Antes de escribir migraciones o queries |
| [BILLING_SPEC.md](./BILLING_SPEC.md) | Spec detallada del módulo de billing (Stripe + Cashier) | Para trabajo específico de billing |
| [OPERATIONS.md](./OPERATIONS.md) | Runbooks: billing cron, Reverb, backups, rotación de secrets | Incidentes en producción |
| [BACKEND_ARCHITECTURE_DIAGRAMS.md](./BACKEND_ARCHITECTURE_DIAGRAMS.md) | Diagramas Mermaid: ER, rutas, flujos (vista legacy) | Visualización rápida |

---

## Módulos (ver [modules/INDEX.md](./modules/INDEX.md) para detalle por pantalla)

### Admin del restaurante
| # | Módulo |
|---|---|
| 01 | [Auth](./modules/01-auth.md) |
| 02 | [Dashboard](./modules/02-dashboard.md) |
| 03 | [Pedidos (Kanban, edición, audit)](./modules/03-orders.md) |
| 04 | [Menú (categorías, productos, modifiers)](./modules/04-menu.md) |
| 05 | [Sucursales](./modules/05-branches.md) |
| 06 | [Configuración](./modules/06-settings.md) |
| 11 | [Cancelaciones (analytics)](./modules/11-cancellations.md) |
| 12 | [Mapa operativo](./modules/12-map.md) |
| 14 | [POS · Caja](./modules/14-pos.md) |
| 15 | [Promociones standalone](./modules/15-promotions.md) |
| 16 | [Cupones](./modules/16-coupons.md) |
| 18 | [Gastos](./modules/18-expenses.md) |

### Panel SuperAdmin
| # | Módulo |
|---|---|
| 07 | [SuperAdmin](./modules/07-superadmin.md) |

### Servicios técnicos e integraciones
| # | Módulo |
|---|---|
| 09 | [DeliveryService (Haversine + Google)](./modules/09-delivery-service.md) |
| 13 | [WebSockets (Reverb + Echo)](./modules/13-websockets.md) |
| 17 | [Billing SaaS (Stripe + Cashier)](./modules/17-billing.md) |

### Cliente SPA y API
| # | Módulo |
|---|---|
| 08 | [Flujo del cliente](./modules/08-customer-flow.md) |
| 10 | [API pública (REST)](./modules/10-api.md) |

---

## Documentos del repositorio (nivel raíz admin)

| Documento | Propósito |
|---|---|
| [../README.md](../README.md) | Setup + env + deploy (tres modos) |
| [../CLAUDE.md](../CLAUDE.md) | Reglas para agentes AI (convenciones Laravel Boost + docs obligatorias) |
| [../AGENTS.md](../AGENTS.md) | Alias de CLAUDE.md para Codex |
| [../GEMINI.md](../GEMINI.md) | Alias de CLAUDE.md para Gemini |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | Branches, tests, Pint, convenciones de commit |
| [../STATUS.md](../STATUS.md) | Estado actual del proyecto |
| [../CHANGELOG.md](../CHANGELOG.md) | Historial cronológico consolidado |
| [../LICENSE](../LICENSE) | Propietaria — APS Creativas |

---

## Artefactos de sesión (`superpowers/`)

Specs y planes de trabajo activos de features nuevas. Orientación Skill-based:

- `superpowers/specs/` — diseño de alto nivel antes de implementar
- `superpowers/plans/` — plan operativo paso a paso durante la implementación

Una vez que la feature entra a producción, se documenta en `modules/XX-*.md` y los specs/plans quedan como rastro histórico.

---

_Documentación PideAquí — Índice v1.0 — Abril 2026_
