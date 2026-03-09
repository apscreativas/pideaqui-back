# GuisoGo — Panel de Administracion y Backend

Panel de administracion del restaurante y SuperAdmin para la plataforma SaaS **GuisoGo** — menu digital y gestion de pedidos multi-restaurante para Mexico.

Tambien sirve como backend API para la [SPA del cliente](../client/).

---

## Stack Tecnologico

| Tecnologia | Version |
|-----------|---------|
| PHP | 8.5 |
| Laravel | v12 |
| PostgreSQL | 18 |
| Laravel Sail | v1 (Docker) |
| Inertia.js | v2 |
| Vue 3 | v3 |
| Tailwind CSS | v4 |
| PHPUnit | v11 |
| Laravel Pint | v1 |

---

## Requisitos Previos

- **Docker Desktop** (incluye Docker Compose)
- **Git**

> Todos los comandos se ejecutan a traves de [Laravel Sail](https://laravel.com/docs/sail). No necesitas PHP, Composer ni Node instalados en tu maquina.

---

## Instalacion

### 1. Clonar el repositorio

```bash
git clone https://github.com/Dayikeynes16/GuisoGo.git
cd GuisoGo/admin
```

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
APP_NAME=GuisoGo
APP_TIMEZONE=America/Mexico_City
APP_URL=http://localhost          # Cambiar en produccion

# --- Almacenamiento de imagenes ---
# 'public' para desarrollo local, 's3' para produccion
MEDIA_DISK=public

# --- Google Maps (requerido para calculo de delivery) ---
VITE_GOOGLE_MAPS_KEY=tu_api_key_de_google_maps

# --- AWS S3 (solo si MEDIA_DISK=s3) ---
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

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
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/           # API publica (autenticacion por token)
│   │   │   ├── SuperAdmin/    # Controladores del SuperAdmin
│   │   │   └── ...            # Controladores del admin restaurante
│   │   ├── Middleware/         # Tenant scope, autenticacion token
│   │   └── Requests/          # Form Requests (validacion)
│   ├── Models/                # Modelos Eloquent
│   └── Services/              # Logica de negocio
│       ├── OrderService.php   # Creacion de pedidos, anti-tampering
│       ├── DeliveryService.php # Calculo de delivery (Haversine + Google)
│       ├── LimitService.php   # Limites de pedidos por periodo
│       └── HaversineService.php # Calculo de distancia por coordenadas
├── bootstrap/app.php          # Registro de middleware y rutas
├── database/
│   ├── factories/             # Factories para testing
│   ├── migrations/            # Esquema de BD
│   └── seeders/               # Seeders
├── resources/js/
│   ├── Pages/                 # Paginas Vue 3 (Inertia)
│   │   ├── Dashboard/
│   │   ├── Orders/            # Kanban de pedidos
│   │   ├── Products/
│   │   ├── Branches/
│   │   ├── Settings/
│   │   └── SuperAdmin/
│   ├── Components/            # Componentes reutilizables
│   └── Layouts/               # Layouts (Admin, SuperAdmin)
├── routes/
│   ├── api.php                # Rutas API publica
│   └── web.php                # Rutas admin y SuperAdmin
└── tests/Feature/             # Tests PHPUnit
```

---

## API Publica

Todas las rutas requieren un token `Bearer` en el header `Authorization`.
El token es el `access_token` del restaurante.

| Metodo | Endpoint | Descripcion | Rate Limit |
|--------|----------|-------------|------------|
| `GET` | `/api/restaurant` | Info del restaurante, horarios, metodos de pago | — |
| `GET` | `/api/menu` | Menu completo con categorias, productos, modificadores | — |
| `GET` | `/api/branches` | Lista de sucursales activas | — |
| `POST` | `/api/delivery/calculate` | Calcular costo de envio y sucursal optima | — |
| `POST` | `/api/orders` | Crear un nuevo pedido | 30/min |

### Autenticacion

```bash
curl -H "Authorization: Bearer <access_token>" \
     -H "Accept: application/json" \
     http://localhost/api/restaurant
```

---

## Arquitectura

```
┌──────────────────────────────────────────────────────────┐
│                       GuisoGo SaaS                       │
├──────────────────┬──────────────────┬────────────────────┤
│  SPA Cliente     │  Panel Admin     │  Panel SuperAdmin  │
│  (repo externo)  │  Restaurante     │  (SaaS)            │
│  Vue 3 + Pinia   │  Inertia + Vue 3 │  Inertia + Vue 3  │
└────────┬─────────┴────────┬─────────┴────────┬───────────┘
         │ API REST          │ Web (Inertia)     │ Web (Inertia)
         └──────────────────┴──────────────────┘
                     Backend Laravel
                     PostgreSQL 18
```

### Multitenancy

- Cada restaurante es un tenant independiente (row-level filtering).
- La SPA del cliente se identifica por un **token de acceso** unico.
- Guards separados: `web` (admin restaurante) y `superadmin` (SuperAdmin).
- Ningun restaurante puede ver ni modificar datos de otro.

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

## Servicios Externos

| Servicio | Uso |
|----------|-----|
| **Google Distance Matrix API** | Distancia real por calles. Pre-filtro Haversine minimiza llamadas. |
| **WhatsApp (wa.me)** | Mensaje preestructurado con el pedido completo. |
| **AWS S3** (produccion) | Imagenes de productos y logos. |

---

## Despliegue a Produccion

### Requisitos del servidor

- PHP 8.5 con extensiones: `pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`
- PostgreSQL 16+
- Nginx o Apache
- Node.js 20+ (solo para compilar assets)
- Supervisor o systemd (para queue worker)
- SSL/TLS (HTTPS obligatorio)

### Pasos

```bash
# 1. Clonar y configurar entorno
git clone https://github.com/Dayikeynes16/GuisoGo.git
cd GuisoGo/admin
cp .env.example .env
# Editar .env con valores de produccion (ver abajo)

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

### Variables de entorno de produccion

```dotenv
APP_NAME=GuisoGo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=tu-host-postgres
DB_PORT=5432
DB_DATABASE=guisogo
DB_USERNAME=guisogo_user
DB_PASSWORD=contraseña_segura

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=tu_access_key
AWS_SECRET_ACCESS_KEY=tu_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket

VITE_GOOGLE_MAPS_KEY=tu_api_key
```

### Configuracion de Nginx

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.com;
    root /var/www/guisogo/admin/public;

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

### Queue Worker (systemd)

Crear `/etc/systemd/system/guisogo-worker.service`:

```ini
[Unit]
Description=GuisoGo Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/guisogo/admin
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable guisogo-worker
sudo systemctl start guisogo-worker
```

### Checklist de produccion

- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] HTTPS configurado con certificado valido
- [ ] Base de datos PostgreSQL con backups automaticos
- [ ] `MEDIA_DISK=s3` con bucket S3 configurado
- [ ] `VITE_GOOGLE_MAPS_KEY` con API key de produccion (restringida por dominio)
- [ ] Queue worker corriendo como servicio
- [ ] Rate limiting activo en login (`throttle:5,1`) y ordenes (`throttle:30,1`)
- [ ] Cron job para scheduler: `* * * * * cd /var/www/guisogo/admin && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Log rotation configurado
- [ ] Permisos de directorio: `storage/` y `bootstrap/cache/` escribibles por `www-data`

---

## Detener el Entorno Local

```bash
./vendor/bin/sail stop       # Detener contenedores (conserva datos)
./vendor/bin/sail down        # Detener y eliminar contenedores
./vendor/bin/sail down -v     # Detener, eliminar contenedores y volumenes (resetea BD)
```
