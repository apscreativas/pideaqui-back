# Módulo 06 — Configuración del Restaurante

> Pantallas de referencia: `ar_14_general_restaurant_settings`, `ar_15_delivery_methods_setup`, `ar_16_shipping_rates_setup`, `ar_17_payment_methods_configuration`, `ar_19_admin_profile_settings`, `ar_20_usage_plan_limits`
>
> El `SettingsLayout.vue` actual lista 10 secciones: General, Horarios, Métodos de entrega, Tarifas de envío, Métodos de pago, **Personalización**, **Usuarios**, Mi cuenta, Mis límites, **Suscripción**.

---

## Descripción General

Módulo que agrupa todas las configuraciones del restaurante y del administrador. Se divide en secciones accesibles desde el menú "Configuración" del sidebar. Estas configuraciones afectan directamente cómo el cliente experimenta el flujo de pedido.

---

## Pantallas y Secciones

### `ar_14` — Configuración General (`/settings/general`)

Información pública del restaurante.

**Campos:**
- Nombre del restaurante (el que ven los clientes).
- Logo del restaurante (imagen, upload a cloud storage). Se muestra en el header del SPA del cliente.
- **Notificaciones de nuevos pedidos** (`notify_new_orders`): toggle booleano. Cuando está habilitado, el restaurante recibe un email (`NewOrderNotification`) al email configurado cada vez que se crea un nuevo pedido.

**Afecta:** El SPA del cliente muestra el nombre y logo del restaurante en el header. Las notificaciones por email se envían usando el trait `Notifiable` del modelo `Restaurant` con `routeNotificationForMail()`.

> **Nota (abr 2026):** La sección "Redes sociales" (`instagram`, `facebook`, `tiktok`) fue removida del UI y de la API por desuso. Las columnas permanecen en la tabla `restaurants` (Opción A — sin migración destructiva). No se exponen ni se aceptan en requests.

---

### `ar_15` — Métodos de Entrega (`/settings/delivery-methods`)

Toggle para activar/desactivar cada tipo de entrega que el restaurante ofrece.

| Método | Descripción |
|---|---|
| 🛵 **A domicilio** | Activa el flujo completo de ubicación GPS y cálculo de envío |
| 🏃 **Recoger en local** | El cliente recoge en la sucursal |
| 🍽️ **Comer aquí** | Consumo en el establecimiento |

**Reglas:**
- Al menos un método de entrega debe estar activo para poder recibir pedidos.
- **No se puede activar "A domicilio" (`allows_delivery`) si no existen `DeliveryRanges` configuradas.** El `DeliveryMethodController` pasa `has_delivery_ranges` al frontend. Si no hay rangos, se muestra un banner de advertencia con link a la pantalla de tarifas de envío (`/settings/shipping-rates`).

**Afecta:** En el Paso 2 del flujo del cliente, solo aparecen los métodos activos.

---

### `ar_16` — Tarifas de Envío (`/settings/shipping-rates`)

Configuración de rangos de distancia con precio fijo de envío. Solo aplica si "A domicilio" está activo.

**Interfaz:**
- Tabla editable de rangos. Cada fila: desde (km), hasta (km), precio ($).
- Botón "+ Agregar rango".
- Botón de eliminar por rango.
- Los rangos se validan: deben ser contiguos y sin huecos.

**Ejemplo:**
| Desde (km) | Hasta (km) | Precio |
|---|---|---|
| 0 | 2 | $0 (gratis) |
| 2 | 5 | $30 |
| 5 | 10 | $60 |
| 10 | 15 | $90 |

**Reglas:**
- El "Desde" de cada rango debe ser igual al "Hasta" del rango anterior.
- El último rango define la **cobertura máxima**. Si el cliente está más lejos, es fuera de cobertura.
- Se pueden definir tantos rangos como se necesite.
- Si no hay rangos configurados, el delivery no tiene costo y sin límite de distancia (o se bloquea — decidir comportamiento al implementar).

**Afecta:** El `DeliveryService` usa estos rangos para calcular el costo de envío al asignar una sucursal en el Paso 2 del cliente.

---

### `ar_17` — Métodos de Pago (`/settings/payment-methods`)

Toggle para cada método de pago disponible para los clientes.

| Método | Toggle | Configuración adicional |
|---|---|---|
| Efectivo | Sí/No | Ninguna |
| Terminal física | Sí/No | Ninguna (el repartidor lleva la terminal) |
| Transferencia bancaria | Sí/No | Banco, titular, CLABE, alias |

**Datos de transferencia bancaria (campos visibles cuando está activo):**
- Banco (ej. BBVA, Banamex, HSBC).
- Nombre del titular.
- CLABE interbancaria (16 o 18 dígitos — validación `regex:/^\d{16}(\d{2})?$/`).
- Alias/CLABE o teléfono Oxxo Pay (opcional).

**Reglas:**
- No se puede activar "Transferencia bancaria" sin tener los 3 campos obligatorios (banco, titular, CLABE) completos.
- Si solo un método está activo, se preselecciona automáticamente en el Paso 3 del cliente.
- Los datos bancarios se muestran al cliente en el Paso 3 si seleccionó transferencia, y se incluyen en la comanda de WhatsApp.

**Afecta:** Paso 3 del flujo del cliente — solo se muestran los métodos activos.

---

### Horarios del Restaurante (`/settings/schedules`)

Configuración de horarios de apertura y cierre **a nivel restaurante** (no por sucursal). La tabla es `restaurant_schedules` — cada registro pertenece al restaurante, no a una sucursal individual.

**Interfaz:**
- 7 filas (una por día de la semana: lunes a domingo).
- Cada fila tiene: nombre del día, toggle abierto/cerrado, inputs de hora apertura y cierre.
- Si el toggle está en "Cerrado", los campos de hora se ocultan.
- Barra de acción fija inferior con botón "Guardar".

**Modelo:** `RestaurantSchedule` (tabla `restaurant_schedules`).

| Campo | Descripción |
|---|---|
| `restaurant_id` | Tenant |
| `day_of_week` | 0=Domingo, 1=Lunes, ..., 6=Sábado |
| `opens_at` | Hora de apertura (`HH:MM`) |
| `closes_at` | Hora de cierre (`HH:MM`) |
| `is_closed` | Si el restaurante cierra ese día |

**Reglas:**
- Constraint único: `[restaurant_id, day_of_week]`.
- Al entrar a la página por primera vez, se crean los 7 registros con valores por defecto (cerrado).
- Los horarios se truncan a `HH:MM` antes de enviar al frontend (PostgreSQL guarda `HH:MM:SS`).
- El formato de validación es `date_format:H:i`.

**Afecta:**
- La API pública retorna los horarios en `GET /api/restaurant` → campo `schedules`.
- El campo `is_open` se calcula en backend con `Restaurant::isCurrentlyOpen()`.
- El SPA del cliente muestra un banner "Fuera de horario" y deshabilita el menú cuando `is_open === false`.
- El `DeliveryService` usa los horarios del restaurante para determinar si está abierto al calcular delivery.
- Los time slots de "Programar pedido" en el cliente se generan a partir de estos horarios.
- **Horarios nocturnos (overnight) soportados:** Si `opens_at > closes_at` (ej. 20:00–02:00), la lógica de `Restaurant::isCurrentlyOpen()` y `DeliveryService::checkSchedule()` maneja correctamente el cruce de medianoche. La ventana de servicio se interpreta como "desde opens_at hasta closes_at del día siguiente".
- Si `opens_at` o `closes_at` son null (sin ser día cerrado), se considera cerrado como precaución.

---

### Fechas Especiales y Días Festivos (sub-sección de `/settings/schedules`)

> **Agregado Mar 2026** — 16 tests en `tests/Feature/SpecialDateTest.php`.

Permite al restaurante **sobrescribir el horario regular** para días específicos: cierres por feriados, horarios especiales por San Valentín, cierres inesperados, etc.

#### Tipos de fecha especial

- **`closed`** — el restaurante está cerrado ese día (ignora el horario regular). Campos `opens_at` / `closes_at` se nullifican.
- **`special`** — horario distinto al regular para ese día. `opens_at` y `closes_at` son requeridos.

Cada fecha puede marcarse como **recurring** (`is_recurring = true`) — en ese caso se matchea por **mes + día**, ignorando el año. Útil para feriados anuales como Navidad o Día de la Madre.

#### Schema

**Tabla `restaurant_special_dates`** (migración `2026_03_23_160512_create_restaurant_special_dates_table.php`):

| Columna | Tipo | Notas |
| --- | --- | --- |
| `restaurant_id` | FK | `cascadeOnDelete` |
| `date` | date | |
| `type` | enum | `closed` \| `special` |
| `opens_at` | time nullable | requerido si `type=special` |
| `closes_at` | time nullable | requerido si `type=special` |
| `label` | string nullable | ej. "Navidad", "Día del Padre" |
| `is_recurring` | boolean | default `false` |
| `timestamps` | | |

**Índice único:** `(restaurant_id, date)` — no puede haber dos reglas para la misma fecha exacta en un restaurante.

#### Cadena de prioridad — `Restaurant::getResolvedScheduleForDate(Carbon)`

Al preguntar "¿qué horario aplica para tal día?", el método evalúa en este orden:

1. **Exact match** — ¿hay una `RestaurantSpecialDate` con `date = $date`?
2. **Recurring match** — ¿hay una con `is_recurring=true` y el mismo mes+día?
3. **Regular schedule** — cae al `RestaurantSchedule` del `day_of_week` correspondiente.

Retorna:

```php
[
    'source' => 'closed' | 'special' | 'regular',
    'opens_at' => '09:00',   // null si cerrado
    'closes_at' => '22:00',  // null si cerrado
    'label' => 'Navidad',    // presente sólo si source !== 'regular'
]
```

Este método es el corazón: `Restaurant::isCurrentlyOpen()`, `DeliveryService::checkSchedule()` y la validación de `scheduled_at` en `OrderService` lo usan.

#### Controller y rutas

`app/Http/Controllers/SpecialDateController.php`:

| Ruta | Método | Endpoint |
| --- | --- | --- |
| `special-dates.store` | POST | `/settings/special-dates` |
| `special-dates.update` | PUT | `/settings/special-dates/{specialDate}` |
| `special-dates.destroy` | DELETE | `/settings/special-dates/{specialDate}` |

No hay `index`/`show` — el CRUD se orquesta desde `Settings/Schedules.vue`.

#### Form Request

`StoreSpecialDateRequest`:

| Campo | Reglas |
| --- | --- |
| `date` | required, date, unique per restaurant |
| `type` | required, in:closed,special |
| `opens_at` | nullable, date_format:H:i, required_if:type,special |
| `closes_at` | nullable, date_format:H:i, required_if:type,special |
| `label` | nullable, string, max:255 |
| `is_recurring` | boolean |

Si `type === 'closed'`, el controller nullifica `opens_at` y `closes_at` automáticamente.

#### UI — `Settings/Schedules.vue`

Sub-sección "Fechas especiales" debajo de los 7 días regulares:

- Tabla con columnas: Fecha, Tipo (Cerrado / Horario especial), Horario, Etiqueta, Recurrente, Acciones
- Modal para crear/editar (datepicker, toggle cerrado/especial, `TimePicker` reutilizable, toggle recurring)
- Botón **"Cargar festivos comunes"** — seeder one-shot que inserta 7 feriados mexicanos recurring:
  - 1 ene (Año Nuevo)
  - 5 feb (Constitución)
  - 21 mar (Benito Juárez)
  - 1 may (Día del Trabajo)
  - 16 sep (Independencia)
  - 20 nov (Revolución)
  - 25 dic (Navidad)

#### API Pública (`GET /api/restaurant`)

Expone la información de cierre en el `RestaurantResource`:

```json
{
  "is_open": false,
  "closure_reason": "holiday",          // null | "holiday" | "special_hours" | "regular_closed"
  "closure_label": "Navidad",           // solo si la fecha tiene label
  "today_schedule": {
    "source": "closed",
    "opens_at": null,
    "closes_at": null,
    "label": "Navidad"
  },
  "upcoming_closures": [
    { "date": "2026-12-25", "type": "closed", "label": "Navidad", "is_recurring": true },
    { "date": "2027-01-01", "type": "closed", "label": "Año Nuevo", "is_recurring": true },
    { "date": "2026-04-20", "type": "special", "opens_at": "12:00", "closes_at": "18:00", "label": "Sábado de Pascua" }
  ]
}
```

- **`closure_reason`** se resuelve con `Restaurant::resolveClosureReason()`
- **`upcoming_closures`** limita a las próximas 3 fechas (ordenadas cronológicamente)

#### OrderService — Validación de pedidos programados

`OrderService::store()` (líneas ~55–96) valida `scheduled_at` contra el horario resuelto:

- Si el `scheduled_at` cae en una fecha `closed` → `ValidationException` con mensaje que incluye el `label` ("El restaurante estará cerrado el 25 de diciembre (Navidad).")
- Si cae fuera de los `opens_at`/`closes_at` de un `special` → rechaza similar

#### Cliente SPA

- **`App.vue`** — consume `closure_reason` + `closure_label` para mostrar el banner superior con el mensaje correcto ("Cerrado hoy por Navidad", "Horario especial hoy: 12:00–18:00", etc.)
- **`MenuHome.vue`** — el menú se rendera en grayscale si `is_open === false`
- **Time slots** — el picker de "Programar pedido" usa `today_schedule` resuelto (no el regular) para construir los slots disponibles

#### Tests

`tests/Feature/SpecialDateTest.php` — 16 tests:

- CRUD: crear `closed` / `special`, actualizar, eliminar, auth tenant-scoped
- Priority chain: `test_holiday_overrides_regular_schedule`, `test_special_hours_overrides`, `test_recurring_match_by_month_and_day`
- API pública: `test_api_returns_closure_reason_for_holiday`, `test_upcoming_closures_limit`
- OrderService: `test_scheduled_order_on_holiday_is_rejected`, `test_scheduled_order_outside_special_hours_is_rejected`

---

### Personalización (`/settings/branding`)

Branding del SPA del cliente: colores, imagen por defecto y modo de texto.

**Campos (`UpdateBrandingRequest`):**
- `primary_color` (hex `#RRGGBB` o `#RGB`).
- `secondary_color` (hex).
- `default_product_image` (upload, JPG/PNG/GIF/WebP, ≤2 MB) + flag `remove_default_image`.
- `text_color` (`light` | `dark`) — controla legibilidad sobre el color primario.

**Migración:** `2026_03_23_150445_add_branding_columns_to_restaurants_table` añade `primary_color`, `secondary_color`, `default_product_image`, `text_color` a `restaurants`.

**Afecta:** El SPA del cliente lee estas variables CSS (`--color-primary`, `--color-secondary`, etc.) en `applyTheme()` para tematizar `MenuHome`, `CartBar`, `ProductModal` y todo el flujo de checkout.

---

### Usuarios (`/settings/users`)

CRUD de usuarios del restaurante — comparten tenant (`restaurant_id`).

**Acciones (`UserController`):** `index`, `create`, `store`, `edit`, `update`, `destroy`.

**Reglas:**
- Todos los usuarios creados quedan ligados al `restaurant_id` del usuario actual (multitenancy).
- No se puede eliminar al último usuario activo del restaurante (deja al admin sin acceso).
- La contraseña usa el cast `hashed` del modelo `User` (sin `Hash::make()` manual).

---

### `ar_19` — Mi Cuenta (`/settings/profile`)

Configuración personal del administrador del restaurante.

**Campos:**
- Nombre completo.
- Correo electrónico.
- Cambiar contraseña (campo separado: contraseña actual + nueva contraseña + confirmar).

**Afecta:** Datos del `User` autenticado. No afecta al restaurante ni al cliente.

---

### `ar_20` — Mis Límites (`/settings/limits`)

Vista de **solo lectura** de los límites configurados por el SuperAdmin y el uso actual.

| Límite | Configurado | Usado | Descripción |
|---|---|---|---|
| Pedidos del periodo | `orders_limit` | `orders del periodo actual` | Periodo definido por `orders_limit_start` y `orders_limit_end` (fechas) |
| Sucursales | `max_branches` | `branches.count()` | Sucursales creadas vs. permitidas |

- Barra de progreso visual por cada límite.
- Mensaje de alerta si se está cerca del límite (>90%).
- **No permite modificar los límites** — solo los puede cambiar el SuperAdmin.

**Afecta:** Es informativo. Los límites reales se validan en backend al crear pedidos y sucursales.

---

### Suscripción (`/settings/subscription`)

Gestión del plan SaaS (Stripe / Laravel Cashier). Disponible cuando el restaurante está en `billing_mode = subscription`.

**Endpoints (`SubscriptionController`):**
- `GET  /settings/subscription` — vista del plan actual + planes disponibles.
- `POST /settings/subscription/initiate` — inicia el flujo de suscripción.
- `POST /settings/subscription/checkout` — Stripe Checkout (rechaza si está en modo manual o ya suscrito).
- `PUT  /settings/subscription/swap` — cambio de plan (puede generar downgrade pendiente al fin del periodo).
- `POST /settings/subscription/cancel` — cancela al fin del periodo.
- `POST /settings/subscription/resume` — revierte cancelación pendiente.
- `DELETE /settings/subscription/pending` — cancela un downgrade pendiente.
- `GET  /settings/subscription/portal` — Stripe Billing Portal.

**Estados manejados:** `active`, `past_due`, `incomplete`, `grace`, `canceled`, `disabled`, `suspended`. Ver `project_billing_saas.md` y `project_stripe_hardening.md` en `memory/`.

**Afecta:** El gate operacional (`Restaurant::canOperate()`) bloquea la creación de pedidos manuales y POS cuando el status no es operacional.

---

## Modelos Involucrados

| Modelo | Tabla | Sección |
|---|---|---|
| `Restaurant` | `restaurants` | ar_14 (nombre, logo, notify), Personalización (branding cols), Suscripción (billing cols), ar_20 (límites) |
| `RestaurantSchedule` | `restaurant_schedules` | Horarios por día |
| `PaymentMethod` | `payment_methods` | ar_17 |
| `DeliveryRange` | `delivery_ranges` | ar_16 |
| `User` | `users` | ar_19 (perfil), Usuarios (CRUD multi-usuario) |
| `Plan` | `plans` | Suscripción |
| `BillingSetting` | `billing_settings` | Suscripción (ajustes globales) |
| `BillingAudit` | `billing_audits` | Suscripción (trazabilidad) |

### Modelo `DeliveryRange`

| Campo | Descripción |
|---|---|
| `restaurant_id` | Tenant |
| `min_km` | Inicio del rango (km) |
| `max_km` | Fin del rango (km) |
| `price` | Precio fijo para ese rango |
| `sort_order` | Orden (típicamente = min_km) |

### Modelo `PaymentMethod`

| Campo | Descripción |
|---|---|
| `restaurant_id` | Tenant |
| `type` | `cash`, `terminal`, `transfer` |
| `is_active` | Habilitado/deshabilitado |
| `bank_name` | Solo para `transfer` |
| `account_holder` | Solo para `transfer` |
| `clabe` | Solo para `transfer` (16 o 18 dígitos) |
| `alias` | Opcional, para `transfer` |

---

## Reglas de Negocio

- Los métodos de pago y las tarifas de envío son **por restaurante**, no por sucursal.
- Los rangos de `delivery_ranges` deben ser contiguos — validar en backend al guardar.
- No se puede activar `transfer` sin `bank_name`, `account_holder` y `clabe` completos.
- El logo del restaurante se almacena en cloud storage. La URL se guarda en `restaurant.logo_path`.
- `ar_20` es informativo — no tiene formulario de edición.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[09-delivery-service.md](./09-delivery-service.md)** | El `DeliveryService` consulta `delivery_ranges` para calcular el costo de envío y la cobertura máxima. |
| **[08-customer-flow.md](./08-customer-flow.md)** | Los métodos de entrega activos (ar_15) y los de pago (ar_17) determinan qué opciones ve el cliente. |
| **[07-superadmin.md](./07-superadmin.md)** | El SuperAdmin configura los límites que se muestran en ar_20 y que se validan al crear pedidos/sucursales. |
| **[05-branches.md](./05-branches.md)** | El límite de sucursales (ar_20) determina cuántas sucursales puede crear el admin. |
| **[10-api.md](./10-api.md)** | La API devuelve los métodos de pago activos y las tarifas de envío al SPA del cliente. |

---

## Implementación Backend

```
Routes:
  GET  /settings/general         → SettingsController@general
  PUT  /settings/general         → SettingsController@updateGeneral

  GET  /settings/delivery-methods → DeliveryMethodController@index
  PUT  /settings/delivery-methods → DeliveryMethodController@update

  GET  /settings/shipping-rates  → DeliveryRangeController@index
  POST /settings/shipping-rates  → DeliveryRangeController@store
  PUT  /settings/shipping-rates/{id} → DeliveryRangeController@update
  DELETE /settings/shipping-rates/{id} → DeliveryRangeController@destroy

  GET  /settings/payment-methods → PaymentMethodController@index
  PUT  /settings/payment-methods/{id} → PaymentMethodController@update

  GET  /settings/branding        → SettingsController@branding
  PUT  /settings/branding        → SettingsController@updateBranding

  GET  /settings/schedules       → SettingsController@schedules
  PUT  /settings/schedules       → SettingsController@updateSchedules

  GET  /settings/profile         → ProfileController@edit
  PUT  /settings/profile         → ProfileController@update

  GET  /settings/limits          → LimitsController@index (solo lectura)

  GET    /settings/users          → UserController@index
  GET    /settings/users/create   → UserController@create
  POST   /settings/users          → UserController@store
  GET    /settings/users/{user}/edit → UserController@edit
  PUT    /settings/users/{user}   → UserController@update
  DELETE /settings/users/{user}   → UserController@destroy

  GET    /settings/subscription           → SubscriptionController@index
  POST   /settings/subscription/initiate  → SubscriptionController@initiateSubscription
  POST   /settings/subscription/checkout  → SubscriptionController@checkout
  PUT    /settings/subscription/swap      → SubscriptionController@swap
  POST   /settings/subscription/cancel    → SubscriptionController@cancel
  POST   /settings/subscription/resume    → SubscriptionController@resume
  DELETE /settings/subscription/pending   → SubscriptionController@cancelPendingDowngrade
  GET    /settings/subscription/portal    → SubscriptionController@portal

Form Requests:
  UpdateGeneralSettingsRequest (name, logo, notify_new_orders)
  UpdateBrandingRequest (primary_color, secondary_color, default_product_image, text_color)
  UpdateDeliveryMethodsRequest (valida que existan DeliveryRanges para activar allows_delivery)
  UpdateDeliveryRangesRequest (valida contigüidad de rangos)
  UpdatePaymentMethodRequest (valida datos bancarios si type=transfer e is_active=true; mínimo 1 método activo)
  UpdateRestaurantScheduleRequest (valida formato H:i en opens_at/closes_at)
  UpdateProfileRequest

Servicio:
  ImageUploadService → logo del restaurante
```

---

## Notas de Diseño

### Tarifas de Envío (ar_16)
- Tabla editable inline: cada fila tiene inputs numéricos y un botón de eliminar.
- El campo "Desde (km)" de la primera fila siempre es 0 y está bloqueado.
- El campo "Desde (km)" de cada rango siguiente se autocalcula como el "Hasta" del anterior.
- Validación visual en rojo si hay huecos o solapamientos.

### Métodos de Pago (ar_17)
- Cards por método de pago con toggle switch.
- Al activar "Transferencia", se expande un accordion con los campos bancarios debajo.
- Botón guardar por sección.

### Mis Límites (ar_20)
- Diseño solo de visualización: barras de progreso coloreadas y números grandes.
- Color de barra: verde < 70%, amarillo 70–90%, rojo > 90%.
- Texto aclaratorio: "Los límites son configurados por el administrador de PideAqui."

---

_PideAqui — Módulo Configuración v1.3 — Abril 2026_
