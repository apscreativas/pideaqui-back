# GuisoGo — Panel de Administración

Panel de administración del restaurante y SuperAdmin para la plataforma SaaS **GuisoGo** — menú digital y gestión de pedidos multi-restaurante para México.

> PRD v2.2 — MVP — Febrero 2026

---

## Descripción General

GuisoGo es una plataforma SaaS multi-restaurante que permite a los negocios de comida digitalizar su menú y recibir pedidos sin comisiones por venta. Este repositorio contiene el **backend y los paneles de administración**:

- **Panel del Administrador del Restaurante** — gestión de menú, sucursales, pedidos y configuración.
- **Panel del SuperAdmin** — creación de restaurantes, configuración de límites y monitoreo global.

El frontend del cliente es un proyecto independiente que se comunica con este backend mediante API.

---

## Stack Tecnológico

| Tecnología | Versión |
|---|---|
| PHP | 8.5 |
| Laravel | v12 |
| PostgreSQL | — |
| Laravel Sail | v1 (Docker) |
| Laravel Boost | v2 |
| Inertia.js | v2 |
| Vue 3 | v3 |
| @vitejs/plugin-vue | — |
| Tailwind CSS | v4 |

---

## Requisitos Previos

- Docker Desktop
- Git

---

## Instalación y Configuración

### 1. Clonar el repositorio

```bash
git clone <repo-url> admin
cd admin
```

### 2. Copiar variables de entorno

```bash
cp .env.example .env
```

### 3. Instalar dependencias

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php85-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Levantar los contenedores

```bash
./vendor/bin/sail up -d
```

### 5. Generar clave de aplicación y migrar

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### 6. Compilar assets

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

> Los assets incluyen Vue 3 compilado via Inertia. Las páginas Vue se ubican en `resources/js/Pages/` y los componentes compartidos en `resources/js/Components/`. Los controladores retornan `Inertia::render('PageName', $data)` en lugar de `view()`.

---

## Comandos Frecuentes

```bash
# Levantar entorno
./vendor/bin/sail up -d

# Detener entorno
./vendor/bin/sail stop

# Ejecutar migraciones
./vendor/bin/sail artisan migrate

# Ejecutar tests
./vendor/bin/sail artisan test --compact

# Formatear código (Pint)
./vendor/bin/sail bin pint --dirty --format agent

# Abrir la app en el navegador
./vendor/bin/sail open
```

---

## Arquitectura del Sistema

### Las Tres Interfaces

```
┌─────────────────────────────────────────────────────────┐
│                      GuisoGo SaaS                       │
├─────────────────┬───────────────────┬───────────────────┤
│  Frontend       │  Panel Admin      │  Panel SuperAdmin  │
│  Cliente Final  │  Restaurante      │  (SaaS)           │
│  (repo externo) │  (este repo)      │  (este repo)      │
└─────────────────┴───────────────────┴───────────────────┘
         ↕ API                  ↕ Web                ↕ Web
                     Backend Laravel (este repo)
```

### Multitenancy

- Cada restaurante es un tenant independiente.
- El frontend del cliente se identifica por un **token de acceso** generado al crear el restaurante.
- Ningún restaurante puede ver ni modificar datos de otro. Regla absoluta.

---

## Módulos del MVP

### Panel del Administrador del Restaurante

| Módulo | Descripción |
|---|---|
| **Autenticación** | Login, recuperar y restablecer contraseña. |
| **Dashboard** | Pedidos del día, pedidos del mes vs. límite, ganancia neta del período. |
| **Gestión de Pedidos** | Tablero Kanban con estatus: Recibido → En preparación → En camino → Entregado. Filtros por sucursal y fecha. Alertas visuales y sonoras. |
| **Menú Digital** | Categorías, productos, modificadores reutilizables, notas libres, costo de producción, QR y link del menú. |
| **Sucursales** | Crear y gestionar sucursales (hasta el límite configurado). Horarios por día, WhatsApp propio, coordenadas en mapa. |
| **Métodos de Pago** | Activar/desactivar: efectivo, terminal física, transferencia bancaria (con datos CLABE). |
| **Tarifas de Envío** | Rangos de distancia con precio fijo (ej. 0-2 km gratis, 2-5 km $30). El último rango define la cobertura máxima. |
| **Configuración** | Nombre, logo, redes sociales, métodos de entrega habilitados, QR, cuenta y límites. |

### Panel SuperAdmin

| Módulo | Descripción |
|---|---|
| **Dashboard** | KPIs globales: restaurantes activos, pedidos del mes, nuevos registros. |
| **Gestión de Restaurantes** | Crear restaurantes (nombre, slug, logo, token de acceso). Activar/desactivar. |
| **Configuración de Límites** | Configurar manualmente por restaurante: pedidos mensuales máximos y sucursales máximas. |
| **Monitoreo** | Uso del mes por restaurante: pedidos y sucursales vs. límites. |
| **Estadísticas Globales** | Gráficas de pedidos por día, nuevos registros por mes, top restaurantes. |

---

## Flujo de Pedido del Cliente (3 Pasos)

El flujo completo ocurre en el frontend independiente del cliente. Este backend lo registra y gestiona:

1. **Paso 1** — El cliente selecciona productos, modificadores y cantidades del menú.
2. **Paso 2** — Elige tipo de entrega (domicilio / recoger / comer aquí). Para domicilio: GPS + mapa + detección de sucursal más cercana + cálculo de envío por rangos.
3. **Paso 3** — Ingresa nombre, teléfono y método de pago. Confirma y se abre WhatsApp con la comanda dirigida al número de la sucursal asignada.

> El cliente **no necesita cuenta ni registro**. Sus datos se persisten en cookies del navegador (90 días).

---

## Servicios Externos

| Servicio | Uso |
|---|---|
| **Google Maps JavaScript API** | Mapa interactivo con pin arrastrable para coordenadas. |
| **Google Distance Matrix API** | Distancia real por calles y tiempo estimado. Pre-filtro Haversine para minimizar costos. |
| **WhatsApp (wa.me link)** | Mensaje preestructurado con el pedido completo enviado por el cliente. |
| **Almacenamiento en la nube** | Imágenes de productos y logos en producción. |

---

## Reglas de Negocio Clave

- El menú es **global por restaurante** — compartido entre todas las sucursales.
- Cada sucursal tiene su **propio WhatsApp** y horarios de operación.
- Las tarifas de envío se configuran a **nivel de restaurante**, no por sucursal.
- Si el restaurante alcanza su **límite mensual de pedidos**, se bloquean nuevos pedidos automáticamente. El conteo se reinicia el día 1 de cada mes.
- La dirección del cliente se **ingresa manualmente**; el pin del mapa solo obtiene coordenadas (sin geocoding inverso).
- Los **modificadores** afectan el precio. Las **notas libres** no afectan el precio.
- El **costo de producción** de cada producto es visible solo para el administrador y se usa para calcular la ganancia neta.
- El restaurante puede cambiar el estatus del pedido **solo hacia adelante** (no se puede revertir).
