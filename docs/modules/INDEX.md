# PideAqui — Índice de Módulos

> Este índice es el punto de entrada para toda la documentación técnica de módulos.
> Antes de implementar cualquier requerimiento, localiza su módulo aquí y lee el archivo correspondiente.

---

## Sistema de Diseño

| Elemento | Valor |
|---|---|
| **Color primario** | `#FF5722` (naranja) |
| **Color primario claro** | `#FFCCBC` |
| **Color primario oscuro** | `#D84315` |
| **Fondo admin** | `#FAFAFA` |
| **Fondo cliente** | `#f6f8f7` |
| **Fuente** | Inter (Google Fonts) |
| **Iconos** | Material Symbols Outlined (Google) |
| **Dark mode** | Soportado en ambas interfaces |

**Layout Admin (desktop):** Sidebar fijo de 260px + contenido principal con `ml-[260px]`
**Layout Cliente (mobile-first):** Header sticky + carrito flotante inferior, máx ~390px de ancho

---

## Módulos del Panel Admin Restaurante

| # | Módulo | Archivo | Pantallas de referencia |
|---|---|---|---|
| 1 | Autenticación | [01-auth.md](./01-auth.md) | `ar_01`, `ar_02`, `ar_03` |
| 2 | Dashboard | [02-dashboard.md](./02-dashboard.md) | `ar_04` |
| 3 | Gestión de Pedidos | [03-orders.md](./03-orders.md) | `ar_05`, `ar_06` |
| 4 | Gestión de Menú | [04-menu.md](./04-menu.md) | `ar_07`, `ar_08`, `ar_09`, `ar_10` |
| 5 | Gestión de Sucursales | [05-branches.md](./05-branches.md) | `ar_11`, `ar_12`, `ar_13` |
| 6 | Configuración | [06-settings.md](./06-settings.md) | `ar_14`, `ar_15`, `ar_16`, `ar_17`, `ar_18`, `ar_19`, `ar_20` |
| 11 | Cancelaciones | [11-cancellations.md](./11-cancellations.md) | Sin mockup (post-MVP) |
| 12 | Mapa Operativo | [12-map.md](./12-map.md) | Sin mockup (post-MVP) |
| 14 | POS · Caja | [14-pos.md](./14-pos.md) | Sin mockup (post-MVP, layout 3 columnas Square) |
| 15 | Promociones | [15-promotions.md](./15-promotions.md) | Sin mockup (post-MVP) |
| 16 | Cupones de Descuento | [16-coupons.md](./16-coupons.md) | Sin mockup (post-MVP) |

## Módulos del Panel SuperAdmin

| # | Módulo | Archivo | Pantallas de referencia |
|---|---|---|---|
| 7 | Panel SuperAdmin | [07-superadmin.md](./07-superadmin.md) | Sin stitch (ver PRD §7.3) |

## Módulos del Frontend del Cliente

| # | Módulo | Archivo | Pantallas de referencia |
|---|---|---|---|
| 8 | Flujo del Cliente (SPA) | [08-customer-flow.md](./08-customer-flow.md) | `c_01`…`c_06` |

## Módulos Técnicos / Servicios

| # | Módulo | Archivo | Descripción |
|---|---|---|---|
| 9 | Servicio de Delivery | [09-delivery-service.md](./09-delivery-service.md) | Haversine + Google Distance Matrix |
| 10 | API Pública (Cliente) | [10-api.md](./10-api.md) | REST API consumida por el SPA |
| 13 | WebSockets (Tiempo Real) | [13-websockets.md](./13-websockets.md) | Laravel Reverb + Laravel Echo |
| 17 | Billing SaaS (Stripe) | [17-billing.md](./17-billing.md) | Cashier + planes + gate operacional |

---

## Fases de Implementación

Ver [../PHASES.md](../PHASES.md) para el orden de implementación recomendado y el estado de cada fase.

---

## Rutas de los mockups

Los diseños de referencia están en:
```
PideAqui/
├── stitch_admin_restaurant_desktop/   ← Admin panel (desktop)
│   ├── ar_01_admin_login/
│   ├── ar_02_password_recovery/
│   ├── ar_03_reset_password/
│   ├── ar_04_admin_dashboard/
│   ├── ar_05_order_kanban_board/
│   ├── ar_06_order_details_view/
│   ├── ar_07_menu_management_accordion/
│   ├── ar_08_product_creation_editing/
│   ├── ar_09_category_editor_modal/
│   ├── ar_10_modifiers_management/
│   ├── ar_11_branch_list_management/
│   ├── ar_12_branch_creation_map_positioning/
│   ├── ar_13_branch_opening_hours/
│   ├── ar_14_general_restaurant_settings/
│   ├── ar_15_delivery_methods_setup/
│   ├── ar_16_shipping_rates_setup/
│   ├── ar_17_payment_methods_configuration/
│   ├── ar_18_qr_code_public_link/
│   ├── ar_19_admin_profile_settings/
│   └── ar_20_usage_plan_limits/
└── stitch_customer_mobile/            ← Cliente final (mobile)
    ├── menu_home_c_01/
    ├── product_detail_and_modifiers_c_02/
    ├── cart_summary_c_03/
    ├── delivery_location_selection_c_04/
    ├── payment_and_order_confirmation_c_05/
    └── order_confirmed_c_06/
```

Cada carpeta contiene:
- `screen.png` — captura visual de la pantalla
- `code.html` — código HTML/Tailwind de referencia del diseño

---

## Mapa de Features por Módulo

Referencia rápida de dónde vive cada feature implementada:

| Feature | Módulo principal | Módulos relacionados |
| --- | --- | --- |
| Login, password recovery, guards separados | [01-auth](./01-auth.md) | |
| KPIs del restaurante | [02-dashboard](./02-dashboard.md) | |
| Kanban de pedidos, detalle, cancelación | [03-orders](./03-orders.md) | [13-websockets](./13-websockets.md) |
| **Edición de pedidos post-creación + audit** | [03-orders](./03-orders.md) — sección "Edición Post-Creación" | [16-coupons](./16-coupons.md) |
| **Pedidos manuales (admin)** | [03-orders](./03-orders.md) — sección "Pedidos Manuales" | [17-billing](./17-billing.md) |
| Categorías, productos, modifiers inline | [04-menu](./04-menu.md) | |
| **Catálogo de modifiers reutilizables** | [04-menu](./04-menu.md) — sección "Catálogo de Modificadores" | |
| Sucursales + geolocalización | [05-branches](./05-branches.md) | [09-delivery-service](./09-delivery-service.md) |
| Ajustes (general, delivery, pagos, usuarios, branding, suscripción) | [06-settings](./06-settings.md) | [17-billing](./17-billing.md) |
| Horarios del restaurante | [06-settings](./06-settings.md) — sección "Horarios" | |
| **Fechas especiales / días festivos** | [06-settings](./06-settings.md) — sección "Fechas Especiales" | |
| SuperAdmin (restaurantes, planes, billing settings) | [07-superadmin](./07-superadmin.md) | [17-billing](./17-billing.md) |
| SPA del cliente | [08-customer-flow](./08-customer-flow.md) | [10-api](./10-api.md), [16-coupons](./16-coupons.md) |
| Delivery (Haversine + Google) | [09-delivery-service](./09-delivery-service.md) | |
| API pública | [10-api](./10-api.md) | [15-promotions](./15-promotions.md), [16-coupons](./16-coupons.md) |
| Reporte de cancelaciones | [11-cancellations](./11-cancellations.md) | |
| Mapa operativo | [12-map](./12-map.md) | |
| WebSockets (Reverb + Echo) | [13-websockets](./13-websockets.md) | [03-orders](./03-orders.md) |
| POS · Caja | [14-pos](./14-pos.md) | [17-billing](./17-billing.md) |
| **Promociones standalone** | [15-promotions](./15-promotions.md) | [04-menu](./04-menu.md) |
| **Cupones de descuento** | [16-coupons](./16-coupons.md) | [03-orders](./03-orders.md) |
| **Billing SaaS (Stripe + Cashier)** | [17-billing](./17-billing.md) | [07-superadmin](./07-superadmin.md), [06-settings](./06-settings.md) |

---

_PideAqui — Índice de Módulos v1.1 — Actualizado Abril 2026 (módulos 15, 16, 17; nuevas features en 03, 04, 06)_
