# PideAqui — Panel de Administracion y Backend

Panel de administracion del restaurante y SuperAdmin para la plataforma SaaS **PideAqui** — menu digital y gestion de pedidos multi-restaurante para Mexico.

Tambien sirve como backend API para la [SPA del cliente](../client/).

---

## Stack Tecnologico

| Tecnologia     | Version           |
| -------------- | ----------------- |
| PHP            | 8.5               |
| Laravel        | v12               |
| PostgreSQL     | 18                |
| Laravel Sail   | v1 (Docker)       |
| Laravel Reverb | v1.8 (WebSockets) |
| Inertia.js     | v2                |
| Vue 3          | v3                |
| Tailwind CSS   | v4                |
| PHPUnit        | v11               |
| Laravel Pint   | v1                |

---

## Requisitos Previos

- **Docker Desktop** (incluye Docker Compose)
- **Git**

> Todos los comandos se ejecutan a traves de [Laravel Sail](https://laravel.com/docs/sail). No necesitas PHP, Composer ni Node instalados en tu maquina.

---

## Instalacion

### 1. Clonar el repositorio

```bash
git clone https://github.com/apscreativas/pideaqui-back.git
cd pideaqui-back
```

> El backend (este repo) vive independiente de la SPA del cliente (`pideaqui-front`) y de la landing (`landing-pideaqui`). Ver el `README.md` del meta-proyecto si clonaste los tres juntos.

### 2. Copiar variables de entorno

```bash
cp .env.example .env
```

Edita `.env` con los valores correctos (ver seccion [Variables de Entorno](#variables-de-entorno)).

### 3. Instalar dependencias PHP (bootstrap sin Sail)

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

### 5. Generar clave y migrar base de datos

```bash
./vendor/bin/sail artisan key:generate --no-interaction
./vendor/bin/sail artisan migrate --no-interaction
```

### 6. (Opcional) Sembrar datos iniciales

```bash
./vendor/bin/sail artisan db:seed --no-interaction
```

### 7. Compilar assets del admin

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

El panel de administracion esta disponible en **http://localhost**.

---

## Variables de Entorno

Edita `.env` antes de iniciar. Las variables criticas:

```dotenv
# --- Base de datos (Sail los configura automaticamente) ---
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# --- Aplicacion ---
APP_NAME=PideAqui
APP_TIMEZONE=America/Mexico_City
APP_URL=http://localhost          # Cambiar en produccion

# --- Google Maps ---
# Se necesitan DOS variables con la misma API key (o keys distintas en produccion):
# Frontend: mapas interactivos en el panel admin (sucursales, detalle pedido, mapa operativo)
VITE_GOOGLE_MAPS_KEY=tu_api_key_de_google_maps
# Backend: calculo de distancia real por calles (Distance Matrix API en DeliveryService)
GOOGLE_MAPS_API_KEY=tu_api_key_de_google_maps

# --- Almacenamiento de imagenes ---
# 'public' para desarrollo local, 's3' para produccion
MEDIA_DISK=public

# --- WebSockets (Laravel Reverb) ---
# Necesario para el tablero Kanban de pedidos en tiempo real.
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=localhost              # Cambiar en produccion
REVERB_PORT=8080
REVERB_SCHEME=http                 # 'https' en produccion

# --- Email (notificaciones de nuevos pedidos) ---
# En desarrollo usar MAIL_MAILER=log (no envia emails reales).
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@tu-dominio.com"
MAIL_FROM_NAME="${APP_NAME}"

# --- AWS S3 (solo si MEDIA_DISK=s3) ---
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

### Google Maps API — Que habilitar

La API key de Google Maps necesita tener habilitadas estas APIs en la [Google Cloud Console](https://console.cloud.google.com/apis):

| API                     | Usado por                                                           | Requerida |
| ----------------------- | ------------------------------------------------------------------- | --------- |
| **Maps JavaScript API** | Mapas en panel admin (sucursales, pedidos, mapa operativo)          | Si        |
| **Distance Matrix API** | Calculo de distancia real por calles (DeliveryService backend)      | Si        |
| **Geocoding API**       | Geocodificacion inversa (opcional, mejora precision de direcciones) | Opcional  |

> En desarrollo puedes usar una sola key sin restricciones. En produccion, se recomienda crear dos keys separadas: una restringida por **HTTP referrer** (para `VITE_GOOGLE_MAPS_KEY`) y otra restringida por **IP del servidor** (para `GOOGLE_MAPS_API_KEY`).

---

## Desarrollo

### Levantar todo el entorno de desarrollo

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer run dev
```

Esto inicia concurrentemente:

- Servidor Laravel
- Queue worker
- Log viewer (Pail)
- Vite dev server con HMR

### WebSockets en desarrollo

Para activar el tablero Kanban en tiempo real durante desarrollo:

```bash
./vendor/bin/sail artisan reverb:start
```

Esto inicia el servidor WebSocket en `localhost:8080`. Los eventos de pedidos (`OrderCreated`, `OrderStatusChanged`, `OrderCancelled`) se transmitiran automaticamente al tablero.

> Si no necesitas WebSockets durante desarrollo, la app funciona igual — los cambios simplemente no se reflejan en tiempo real (se requiere recargar la pagina).

### Comandos frecuentes

```bash
# Levantar / detener contenedores
./vendor/bin/sail up -d
./vendor/bin/sail stop

# Migraciones
./vendor/bin/sail artisan migrate --no-interaction
./vendor/bin/sail artisan migrate:rollback --no-interaction

# Crear archivos (siempre usar artisan make:)
./vendor/bin/sail artisan make:model NombreModelo -mf --no-interaction
./vendor/bin/sail artisan make:controller NombreController --no-interaction
./vendor/bin/sail artisan make:test NombreTest --no-interaction

# Formatear codigo PHP
./vendor/bin/sail bin pint --dirty --format agent

# Compilar assets para produccion
./vendor/bin/sail npm run build

# Abrir la app en el navegador
./vendor/bin/sail open
```

---

## Testing

Se usa **PHPUnit** (no Pest). Los tests son principalmente feature tests.

```bash
# Ejecutar toda la suite
./vendor/bin/sail artisan test --compact

# Ejecutar un archivo de tests especifico
./vendor/bin/sail artisan test --compact tests/Feature/OrderApiTest.php

# Ejecutar un test especifico por nombre
./vendor/bin/sail artisan test --compact --filter=test_create_delivery_order
```

---

## Estructura del Proyecto

```
admin/
├── app/
│   ├── DTOs/                  # Data Transfer Objects
│   ├── Events/                # Eventos broadcast (OrderCreated, etc.)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/           # API publica (autenticacion por token)
│   │   │   ├── SuperAdmin/    # Controladores del SuperAdmin
│   │   │   └── ...            # Controladores del admin restaurante
│   │   ├── Middleware/         # Tenant scope, autenticacion token
│   │   └── Requests/          # Form Requests (validacion)
│   ├── Models/                # Modelos Eloquent
│   ├── Notifications/         # NewOrderNotification (email)
│   └── Services/              # Logica de negocio
│       ├── OrderService.php   # Creacion de pedidos, anti-tampering
│       ├── DeliveryService.php # Calculo de delivery (Haversine + Google)
│       ├── LimitService.php   # Limites de pedidos por periodo
│       └── HaversineService.php # Calculo de distancia por coordenadas
├── bootstrap/app.php          # Registro de middleware y rutas
├── config/
│   ├── broadcasting.php       # Config de Reverb/WebSockets
│   ├── reverb.php             # Config del servidor Reverb
│   └── services.php           # API keys (Google Maps, etc.)
├── database/
│   ├── factories/             # Factories para testing
│   ├── migrations/            # Esquema de BD
│   └── seeders/               # Seeders
├── resources/js/
│   ├── Pages/                 # Paginas Vue 3 (Inertia)
│   │   ├── Dashboard/
│   │   ├── Orders/            # Kanban de pedidos (tiempo real)
│   │   ├── Products/
│   │   ├── Branches/
│   │   ├── Map/               # Mapa operativo
│   │   ├── Cancellations/
│   │   ├── Settings/
│   │   └── SuperAdmin/
│   ├── Components/            # Componentes reutilizables
│   └── Layouts/               # Layouts (Admin, SuperAdmin)
├── routes/
│   ├── api.php                # Rutas API publica
│   ├── channels.php           # Autorizacion de canales WebSocket
│   └── web.php                # Rutas admin y SuperAdmin
└── tests/Feature/             # Tests PHPUnit
```

---

## API Publica

Todas las rutas requieren un token `Bearer` en el header `Authorization`.
El token es el `access_token` del restaurante.

| Metodo | Endpoint                  | Descripcion                                            | Rate Limit |
| ------ | ------------------------- | ------------------------------------------------------ | ---------- |
| `GET`  | `/api/restaurant`         | Info del restaurante, horarios, metodos de pago        | —          |
| `GET`  | `/api/menu`               | Menu completo con categorias, productos, modificadores | —          |
| `GET`  | `/api/branches`           | Lista de sucursales activas                            | —          |
| `POST` | `/api/delivery/calculate` | Calcular costo de envio y sucursal optima              | —          |
| `POST` | `/api/orders`             | Crear un nuevo pedido                                  | 30/min     |

### Autenticacion

```bash
curl -H "Authorization: Bearer <access_token>" \
     -H "Accept: application/json" \
     http://localhost/api/restaurant
```

El `access_token` se genera automaticamente al crear un restaurante en el SuperAdmin. Puedes verlo y regenerarlo en la pagina de detalle del restaurante en el panel SuperAdmin.

---

## Arquitectura

```
┌──────────────────────────────────────────────────────────┐
│                       PideAqui SaaS                       │
├──────────────────┬──────────────────┬────────────────────┤
│  SPA Cliente     │  Panel Admin     │  Panel SuperAdmin  │
│  (repo externo)  │  Restaurante     │  (SaaS)            │
│  Vue 3 + Pinia   │  Inertia + Vue 3 │  Inertia + Vue 3  │
└────────┬─────────┴────────┬─────────┴────────┬───────────┘
         │ API REST          │ Web (Inertia)     │ Web (Inertia)
         └──────────────────┴──────────────────┘
                     Backend Laravel
                     PostgreSQL 18
                  Laravel Reverb (WebSockets)
```

### Multitenancy

- Cada restaurante es un tenant independiente (row-level filtering).
- La SPA del cliente se identifica por un **token de acceso** unico.
- Guards separados: `web` (admin restaurante) y `superadmin` (SuperAdmin).
- Ningun restaurante puede ver ni modificar datos de otro.

### WebSockets (Tiempo Real)

El tablero Kanban de pedidos se actualiza en tiempo real via Laravel Reverb:

- **Canal privado:** `restaurant.{restaurantId}` (autenticado, cada admin solo escucha su restaurante)
- **Eventos:** `OrderCreated`, `OrderStatusChanged`, `OrderCancelled`
- Los eventos se despachan con `ShouldBroadcastNow` (sincrono, sin cola)
- Si Reverb no esta disponible, la app funciona normalmente — los cambios requieren recargar la pagina

---

## Reglas de Negocio Clave

- El menu es **global por restaurante** (compartido entre sucursales).
- Cada sucursal tiene su **propio WhatsApp** y horarios.
- Las tarifas de envio se configuran a **nivel de restaurante**, no por sucursal.
- El limite de pedidos bloquea automaticamente si se alcanza. Se valida con `lockForUpdate()` para evitar race conditions.
- El **costo de produccion** nunca se expone en la API publica.
- El estatus del pedido solo avanza hacia adelante (no se puede revertir).
- El cliente **no necesita cuenta** — sus datos se persisten en cookies (90 dias).
- `distance_km` se calcula server-side con Haversine (nunca se confia en el valor del cliente).
- `scheduled_at` se valida contra los horarios del restaurante.
- Los pedidos se rechazan si el restaurante esta cerrado.

---

## Billing SaaS (Stripe)

El sistema soporta un **modelo hibrido** de planes:

| Modo            | Descripcion                              | Fuente de limites                                              |
| --------------- | ---------------------------------------- | -------------------------------------------------------------- |
| **Manual**      | SuperAdmin define limites directamente   | `orders_limit`, `max_branches`, `orders_limit_start/end` en BD |
| **Suscripcion** | Restaurante elige plan y paga via Stripe | Plan asignado + periodo de Stripe                              |

### Configurar Stripe

#### 1. Crear cuenta de Stripe

Registrate en [stripe.com](https://stripe.com) y activa tu cuenta.

#### 2. Obtener las API keys

En [Stripe Dashboard > Developers > API keys](https://dashboard.stripe.com/apikeys):

- **Publishable key** (`pk_live_...` o `pk_test_...`) → `STRIPE_KEY`
- **Secret key** (`sk_live_...` o `sk_test_...`) → `STRIPE_SECRET`

```dotenv
STRIPE_KEY=pk_live_xxxxxxxxxxxx
STRIPE_SECRET=sk_live_xxxxxxxxxxxx
CASHIER_CURRENCY=mxn
```

> En desarrollo usa las keys de test (`pk_test_...`, `sk_test_...`). En produccion usa las keys live.

#### 3. Sincronizar planes con Stripe

Despues de configurar las keys y tener planes creados en el SuperAdmin:

```bash
# Crea Products y Prices en Stripe para cada plan local
php artisan billing:sync-stripe
```

Esto crea automaticamente los productos y precios en tu cuenta de Stripe y vincula los IDs localmente. Si cambias precios, el comando archiva el precio viejo y crea uno nuevo.

#### 4. Configurar el Webhook

En [Stripe Dashboard > Developers > Webhooks](https://dashboard.stripe.com/webhooks):

1. Click **"Add endpoint"**
2. **URL**: `https://tu-dominio.com/stripe/webhook`
3. **Eventos a escuchar** (seleccionar estos 6):
    - `checkout.session.completed`
    - `customer.subscription.created`
    - `customer.subscription.updated`
    - `customer.subscription.deleted`
    - `invoice.paid`
    - `invoice.payment_failed`
4. Click **"Add endpoint"**
5. En la pagina del endpoint, click **"Reveal"** en el signing secret
6. Copia el `whsec_...` y ponlo en tu `.env`:

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx
```

> **Importante**: En desarrollo local, usa el [Stripe CLI](https://stripe.com/docs/stripe-cli) para reenviar webhooks:
>
> ```bash
> stripe listen --forward-to http://localhost/stripe/webhook
> ```
>
> El CLI te da un `whsec_...` temporal que debes poner en `.env`.

#### 5. Seeders de billing

```bash
# Crear configuracion minima de billing:
# - plan de gracia
# - billing settings globales
php artisan db:seed --class=BillingSeeder --force

# Crear planes comerciales desde SuperAdmin > Planes
# (Basico, Pro, Enterprise, etc.) antes de habilitar checkout en produccion.

# (Opcional) Asignar planes a restaurantes existentes sin plan
# Requiere que ya existan planes comerciales activos.
php artisan billing:backfill-plans --dry-run   # Ver que haria
php artisan billing:backfill-plans             # Ejecutar
```

### Flujos de billing

| Flujo                          | Que pasa                                                                                            |
| ------------------------------ | --------------------------------------------------------------------------------------------------- |
| **Nuevo restaurante (manual)** | SuperAdmin crea con limites manuales. Sin Stripe.                                                   |
| **Nuevo restaurante (gracia)** | SuperAdmin crea con periodo de gracia (14 dias). Restaurante elige plan y paga via Stripe Checkout. |
| **Upgrade**                    | Cambio inmediato. Stripe cobra diferencia prorrateada con `swapAndInvoice()`.                       |
| **Downgrade**                  | Programado para el siguiente ciclo. Restaurante mantiene beneficios actuales hasta fin de periodo.  |
| **Pago fallido**               | Stripe reintenta. Restaurante entra en `grace_period` (7 dias). Si no paga, se suspende.            |
| **Cancelacion**                | Restaurante sigue activo hasta fin del periodo pagado. Despues se suspende.                         |
| **Manual → Suscripcion**       | Restaurante o SuperAdmin inicia gracia. Restaurante elige plan.                                     |
| **Suscripcion → Manual**       | SuperAdmin cambia a manual. Stripe se cancela. Limites manuales aplican.                            |

### Cron jobs de billing

Estos comandos corren automaticamente via el scheduler de Laravel:

| Comando                            | Frecuencia   | Que hace                                               |
| ---------------------------------- | ------------ | ------------------------------------------------------ |
| `billing:check-grace`              | Diario 06:00 | Suspende restaurantes con gracia expirada              |
| `billing:check-canceled`           | Diario 06:05 | Suspende suscripciones canceladas cuyo periodo termino |
| `billing:send-reminders`           | Diario 08:00 | Envia recordatorio antes de que expire la gracia       |
| `billing:reconcile`                | Diario 03:00 | Sincroniza estado local con Stripe                     |
| `billing:apply-pending-downgrades` | Cada hora    | Aplica downgrades programados cuya fecha ya paso       |

> En Laravel Cloud, habilita el toggle **"Scheduler"** en el compute cluster. En servidor propio, configura el cron: `* * * * * php artisan schedule:run >> /dev/null 2>&1`

---

## Servicios Externos

| Servicio                       | Uso                                    | Variable de entorno                                    |
| ------------------------------ | -------------------------------------- | ------------------------------------------------------ |
| **Stripe**                     | Pagos y suscripciones SaaS             | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| **Google Maps JavaScript API** | Mapas interactivos en panel admin      | `VITE_GOOGLE_MAPS_KEY`                                 |
| **Google Distance Matrix API** | Distancia real por calles (backend)    | `GOOGLE_MAPS_API_KEY`                                  |
| **WhatsApp (wa.me)**           | Mensaje preestructurado con el pedido  | —                                                      |
| **AWS S3** (produccion)        | Imagenes de productos y logos          | `AWS_*`                                                |
| **SMTP** (produccion)          | Notificacion email de nuevos pedidos   | `MAIL_*`                                               |
| **Laravel Reverb**             | WebSockets para tablero en tiempo real | `REVERB_*`                                             |

---

## Despliegue a Produccion

### Opcion A: Laravel Cloud (recomendado)

[Laravel Cloud](https://cloud.laravel.com) es la forma mas sencilla de desplegar. Gestiona automaticamente la infraestructura, SSL, WebSockets y escalado.

#### 1. Crear la aplicacion

1. Conecta tu repositorio de GitHub (`apscreativas/pideaqui-back`) en Laravel Cloud.
2. Deja la raíz del repositorio como root del proyecto (este repo ya es el backend completo).
3. Configura el environment (staging o production).

#### 2. Agregar base de datos

1. En Resources > Databases, crea un cluster **Laravel MySQL** o **PostgreSQL**.
2. Conectalo al environment. Cloud inyecta automaticamente `DB_*`.

#### 3. Variables de entorno

Cloud inyecta las variables de base de datos, Object Storage y WebSockets automaticamente. Solo necesitas agregar manualmente:

```dotenv
APP_NAME=PideAqui
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Mexico_City

# Stripe (ver seccion "Billing SaaS" arriba para obtener las keys)
STRIPE_KEY=pk_live_xxxxxxxxxxxx
STRIPE_SECRET=sk_live_xxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxx
CASHIER_CURRENCY=mxn

# Google Maps (dos keys, o la misma si usas una sola)
VITE_GOOGLE_MAPS_KEY=tu_api_key_frontend
GOOGLE_MAPS_API_KEY=tu_api_key_backend

# Almacenamiento (Cloud inyecta AWS_* al adjuntar Object Storage)
MEDIA_DISK=s3

# Email (notificaciones de nuevos pedidos)
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=465
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_contraseña
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS="pedidos@tu-dominio.com"
MAIL_FROM_NAME="PideAqui"
```

#### 4. WebSockets (Reverb gestionado)

1. En Resources > **WebSockets**, click **"+ New WebSocket cluster"**.
2. Selecciona la region (misma que tu app) y el maximo de conexiones concurrentes.
3. En el canvas de tu aplicacion, click **"Add resource" > "WebSockets"**.
4. Cloud inyecta automaticamente todas las variables `REVERB_*` y `VITE_REVERB_*`.
5. **Redeploy** tu aplicacion para que los cambios tomen efecto.

> No necesitas correr `artisan reverb:start`. Cloud gestiona el cluster de WebSockets automaticamente.

#### 5. Build y deploy commands

En el dashboard de Laravel Cloud, configura:

**Build Commands:**

```
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

**Deploy Commands:**

```
php artisan migrate --force
```

#### 6. Post-deploy (primera vez)

Despues del primer deploy, ejecuta estos comandos desde el tab **Commands** del environment:

```bash
# Crear configuracion inicial de billing (plan de gracia + settings)
php artisan db:seed --class=BillingSeeder --force

# Luego entra a SuperAdmin > Planes y crea los planes comerciales.
# Cuando existan, sincronizalos con Stripe (crea Products y Prices)
php artisan billing:sync-stripe
```

#### 7. Configurar webhook de Stripe

Una vez que tengas el dominio en produccion:

1. Ve a [Stripe Dashboard > Webhooks](https://dashboard.stripe.com/webhooks)
2. Agrega endpoint: `https://tu-dominio.com/stripe/webhook`
3. Selecciona los 6 eventos listados en la seccion de Billing arriba
4. Copia el signing secret → agregalo como `STRIPE_WEBHOOK_SECRET` en las variables del environment
5. Redeploy

#### 8. Habilitar scheduler y queue

En el dashboard de Laravel Cloud, en tu **App compute cluster**:

1. Habilita el toggle **"Scheduler"** (corre `schedule:run` cada minuto)
2. Habilita el toggle **"Queue"** (procesa queue:work)
3. Guarda y redeploy

#### 9. Desplegar

Haz push a tu rama principal. Cloud despliega automaticamente.

---

### Opcion B: Docker Compose (VPS / servidor propio)

Para desplegar en un VPS con Docker Compose sin depender de Laravel Cloud.

#### 1. Preparar el servidor

Requisitos del servidor:

- Docker Engine 24+
- Docker Compose v2+
- Dominio con DNS apuntando al servidor
- Certificado SSL (Let's Encrypt recomendado)

#### 2. Clonar y configurar

```bash
git clone https://github.com/apscreativas/pideaqui-back.git
cd pideaqui-back
cp .env.example .env
# Editar .env con valores de produccion (ver abajo)
```

#### 3. Crear `docker-compose.prod.yml`

```yaml
services:
    app:
        build:
            context: .
            dockerfile: Dockerfile.prod
        restart: unless-stopped
        ports:
            - "80:80"
            - "443:443"
        environment:
            - CONTAINER_ROLE=app
        volumes:
            - storage:/var/www/html/storage/app
        depends_on:
            pgsql:
                condition: service_healthy
        env_file: .env
        networks:
            - pideaqui

    queue:
        build:
            context: .
            dockerfile: Dockerfile.prod
        restart: unless-stopped
        command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        depends_on:
            pgsql:
                condition: service_healthy
        env_file: .env
        networks:
            - pideaqui

    reverb:
        build:
            context: .
            dockerfile: Dockerfile.prod
        restart: unless-stopped
        ports:
            - "${REVERB_PORT:-8080}:${REVERB_PORT:-8080}"
        command: php artisan reverb:start --host=0.0.0.0 --port=${REVERB_PORT:-8080}
        depends_on:
            pgsql:
                condition: service_healthy
        env_file: .env
        networks:
            - pideaqui

    scheduler:
        build:
            context: .
            dockerfile: Dockerfile.prod
        restart: unless-stopped
        command: >
            sh -c "while true; do php artisan schedule:run --no-interaction; sleep 60; done"
        depends_on:
            pgsql:
                condition: service_healthy
        env_file: .env
        networks:
            - pideaqui

    pgsql:
        image: postgres:18-alpine
        restart: unless-stopped
        environment:
            POSTGRES_DB: ${DB_DATABASE}
            POSTGRES_USER: ${DB_USERNAME}
            POSTGRES_PASSWORD: ${DB_PASSWORD}
        volumes:
            - pgsql-data:/var/lib/postgresql/data
        networks:
            - pideaqui
        healthcheck:
            test:
                [
                    "CMD",
                    "pg_isready",
                    "-q",
                    "-d",
                    "${DB_DATABASE}",
                    "-U",
                    "${DB_USERNAME}",
                ]
            retries: 3
            timeout: 5s

networks:
    pideaqui:
        driver: bridge

volumes:
    storage:
    pgsql-data:
```

#### 4. Crear `Dockerfile.prod`

```dockerfile
FROM php:8.5-fpm-alpine

# Extensiones requeridas
RUN apk add --no-cache \
    postgresql-dev libpng-dev libjpeg-turbo-dev libwebp-dev \
    nginx supervisor && \
    docker-php-ext-configure gd --with-jpeg --with-webp && \
    docker-php-ext-install pdo_pgsql pgsql gd bcmath opcache

# Node.js para compilar assets
RUN apk add --no-cache nodejs npm

WORKDIR /var/www/html
COPY . .

# Dependencias PHP (sin dev)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Compilar assets
RUN npm ci && npm run build && rm -rf node_modules

# Permisos
RUN chown -R www-data:www-data storage bootstrap/cache

# Config Nginx y Supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

> Este Dockerfile es un ejemplo de referencia. Ajusta segun las necesidades de tu infraestructura.

#### 5. Variables de entorno de produccion

```dotenv
APP_NAME=PideAqui
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=pideaqui
DB_USERNAME=pideaqui_user
DB_PASSWORD=contraseña_segura

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Google Maps
VITE_GOOGLE_MAPS_KEY=tu_api_key_frontend
GOOGLE_MAPS_API_KEY=tu_api_key_backend

# Almacenamiento
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket

# WebSockets
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=tu-dominio.com        # Dominio publico donde corre Reverb
REVERB_PORT=8080                   # Puerto expuesto (o 443 si usas proxy TLS)
REVERB_SCHEME=https                # 'https' en produccion

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=465
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_contraseña
MAIL_SCHEME=smtps
MAIL_FROM_ADDRESS="pedidos@tu-dominio.com"
MAIL_FROM_NAME="PideAqui"
```

#### 6. Desplegar

```bash
# Primera vez
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate --no-interaction
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force --no-interaction
docker compose -f docker-compose.prod.yml exec app php artisan storage:link

# Actualizaciones posteriores
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force --no-interaction
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

---

### Opcion C: Servidor tradicional (Nginx + PHP-FPM)

Para desplegar directamente en un servidor sin Docker.

#### Requisitos del servidor

- PHP 8.5 con extensiones: `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`
- PostgreSQL 16+
- Nginx o Apache
- Node.js 20+ (solo para compilar assets)
- Supervisor o systemd (para queue worker y Reverb)
- SSL/TLS (HTTPS obligatorio)

#### Pasos

```bash
# 1. Clonar y configurar entorno
git clone https://github.com/apscreativas/pideaqui-back.git
cd pideaqui-back
cp .env.example .env
# Editar .env con valores de produccion

# 2. Instalar dependencias (sin dev)
composer install --no-dev --optimize-autoloader

# 3. Generar clave
php artisan key:generate --no-interaction

# 4. Migrar base de datos
php artisan migrate --force --no-interaction

# 5. Cachear configuracion y rutas
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Compilar assets
npm ci
npm run build

# 7. Crear enlace simbolico para storage
php artisan storage:link
```

#### Configuracion de Nginx

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.com;
    root /var/www/pideaqui/admin/public;

    ssl_certificate     /etc/ssl/certs/tu-dominio.crt;
    ssl_certificate_key /etc/ssl/private/tu-dominio.key;

    index index.php;
    charset utf-8;

    # Tamaño maximo para subida de imagenes
    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Bloquear acceso a archivos ocultos
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Proxy Nginx para WebSockets (Reverb)

Si Reverb corre en el mismo servidor, agrega este bloque para exponer WebSockets via HTTPS:

```nginx
server {
    listen 443 ssl http2;
    server_name ws.tu-dominio.com;

    ssl_certificate     /etc/ssl/certs/tu-dominio.crt;
    ssl_certificate_key /etc/ssl/private/tu-dominio.key;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
    }
}
```

Con este proxy, configura las variables de entorno asi:

```dotenv
REVERB_HOST=ws.tu-dominio.com
REVERB_PORT=443
REVERB_SCHEME=https
```

#### Queue Worker (systemd)

Crear `/etc/systemd/system/pideaqui-worker.service`:

```ini
[Unit]
Description=PideAqui Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/pideaqui/admin
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

#### Reverb WebSocket Server (systemd)

Crear `/etc/systemd/system/pideaqui-reverb.service`:

```ini
[Unit]
Description=PideAqui Reverb WebSocket Server
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/pideaqui/admin
ExecStart=/usr/bin/php artisan reverb:start --host=0.0.0.0 --port=8080

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable pideaqui-worker pideaqui-reverb
sudo systemctl start pideaqui-worker pideaqui-reverb
```

---

### Checklist de produccion

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] HTTPS configurado con certificado valido
- [ ] Base de datos PostgreSQL con backups automaticos
- [ ] `VITE_GOOGLE_MAPS_KEY` con API key restringida por **HTTP referrer** (dominio del admin)
- [ ] `GOOGLE_MAPS_API_KEY` con API key restringida por **IP del servidor**
- [ ] `MEDIA_DISK=s3` con bucket S3 configurado
- [ ] `BROADCAST_CONNECTION=reverb` con Reverb corriendo (o WebSockets managed en Laravel Cloud)
- [ ] `MAIL_MAILER=smtp` con proveedor real configurado (si `notify_new_orders` esta activo)
- [ ] Queue worker corriendo como servicio
- [ ] Reverb corriendo como servicio (o gestionado por Laravel Cloud)
- [ ] Rate limiting activo en login (`throttle:5,1`) y ordenes (`throttle:30,1`)
- [ ] Cron job para scheduler: `* * * * * cd /var/www/pideaqui/admin && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Log rotation configurado
- [ ] Permisos de directorio: `storage/` y `bootstrap/cache/` escribibles por `www-data`

---

## Detener el Entorno Local

```bash
./vendor/bin/sail stop       # Detener contenedores (conserva datos)
./vendor/bin/sail down        # Detener y eliminar contenedores
./vendor/bin/sail down -v     # Detener, eliminar contenedores y volumenes (resetea BD)
```
