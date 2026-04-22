# PideAqui — Arquitectura del Backend (Diagramas)

> Documentación visual generada por análisis directo del código fuente.
> Todos los diagramas están en formato **Mermaid** para visualización en GitHub, VS Code, o cualquier herramienta compatible.

---

## Índice

1. [Vista General de la Arquitectura](#1-vista-general-de-la-arquitectura)
2. [Diagrama de Entidad-Relación (Modelos Eloquent)](#2-diagrama-de-entidad-relación)
3. [Mapa de Rutas — Admin Web](#3-mapa-de-rutas--admin-web)
4. [Mapa de Rutas — API Pública](#4-mapa-de-rutas--api-pública)
5. [Mapa de Rutas — SuperAdmin](#5-mapa-de-rutas--superadmin)
6. [Ciclo de Vida de una Petición HTTP](#6-ciclo-de-vida-de-una-petición-http)
7. [Flujo: Creación de Pedido (API)](#7-flujo-creación-de-pedido-api)
8. [Flujo: Cálculo de Delivery](#8-flujo-cálculo-de-delivery)
9. [Flujo: Admin — Kanban de Pedidos](#9-flujo-admin--kanban-de-pedidos)
10. [Capa de Servicios](#10-capa-de-servicios)
11. [Sistema de Multi-Tenancy](#11-sistema-de-multi-tenancy)
12. [Middleware y Guards](#12-middleware-y-guards)
13. [Descripción de Componentes](#13-descripción-de-componentes)

---

## 1. Vista General de la Arquitectura

```mermaid
graph TB
    subgraph Clientes["Clientes"]
        SPA["Client SPA<br/>(Vue 3 + Vite)"]
        ADMIN["Admin Panel<br/>(Inertia + Vue 3)"]
        SA["SuperAdmin Panel<br/>(Inertia + Vue 3)"]
    end

    subgraph Laravel["Laravel 12 Backend"]
        subgraph Middleware["Middleware Layer"]
            HI["HandleInertiaRequests"]
            AUTH["auth (web guard)"]
            AUTHSA["auth:superadmin"]
            TENANT["EnsureTenantContext"]
            SLUG["ResolveTenantFromSlug"]
            THRT["throttle"]
        end

        subgraph Controllers["Controllers"]
            WEB_C["Admin Controllers (14)"]
            API_C["API Controllers (5)"]
            SA_C["SuperAdmin Controllers (5)"]
        end

        subgraph Validation["Validation Layer"]
            FR["Form Requests (22+)"]
            POL["Policies (7)"]
        end

        subgraph Services["Service Layer"]
            OS["OrderService"]
            DS["DeliveryService"]
            LS["LimitService"]
            SS["StatisticsService"]
            HS["HaversineService"]
            GS["GoogleMapsService"]
        end

        subgraph Models["Eloquent Models (15)"]
            MDL["Restaurant · User · SuperAdmin<br/>Branch · Category · Product<br/>ModifierGroup · ModifierOption<br/>Order · OrderItem · OrderItemModifier<br/>PaymentMethod · DeliveryRange<br/>Customer · RestaurantSchedule"]
        end

        subgraph Scopes["Multi-Tenancy"]
            TS["TenantScope"]
            BTT["BelongsToTenant Trait"]
        end
    end

    subgraph External["Servicios Externos"]
        GM["Google Maps<br/>Distance Matrix API"]
        WA["WhatsApp<br/>(link generado)"]
    end

    DB[("PostgreSQL")]

    SPA -->|"REST + Token"| TOKN
    ADMIN -->|"Inertia"| HI
    SA -->|"Inertia"| HI

    HI --> AUTH
    HI --> AUTHSA
    AUTH --> TENANT
    TOKN --> API_C
    TENANT --> WEB_C
    AUTHSA --> SA_C

    WEB_C --> FR
    API_C --> FR
    SA_C --> FR
    WEB_C --> POL

    FR --> Services
    Controllers --> Services

    Services --> Models
    DS --> GS
    DS --> HS
    OS --> HS
    OS --> LS

    Models --> TS
    TS --> DB

    OS -.->|"genera link"| WA
    GS -->|"HTTP"| GM
```

---

## 2. Diagrama de Entidad-Relación

```mermaid
erDiagram
    Restaurant ||--o{ User : "has many"
    Restaurant ||--o{ Branch : "has many"
    Restaurant ||--o{ Category : "has many"
    Restaurant ||--o{ Product : "has many"
    Restaurant ||--o{ ModifierGroup : "has many"
    Restaurant ||--o{ PaymentMethod : "has many"
    Restaurant ||--o{ DeliveryRange : "has many"
    Restaurant ||--o{ Order : "has many"
    Restaurant ||--o{ RestaurantSchedule : "has many (7)"

    Category ||--o{ Product : "has many"
    Product ||--o{ ModifierGroup : "has many"
    ModifierGroup ||--o{ ModifierOption : "has many"

    Branch ||--o{ Order : "has many"
    Customer ||--o{ Order : "has many"
    Order ||--o{ OrderItem : "has many"
    OrderItem ||--o{ OrderItemModifier : "has many"
    OrderItemModifier }o--|| ModifierOption : "belongs to"
    OrderItem }o--|| Product : "belongs to"

    Restaurant {
        bigint id PK
        string name
        string slug UK
        string logo_path
        boolean is_active
        boolean allows_delivery
        boolean allows_pickup
        boolean allows_dine_in
        integer orders_limit
        date orders_limit_start
        date orders_limit_end
        integer max_branches
        string instagram
        string facebook
        string tiktok
    }

    User {
        bigint id PK
        bigint restaurant_id FK
        string name
        string email UK
        string password
    }

    SuperAdmin {
        bigint id PK
        string name
        string email UK
        string password
    }

    Branch {
        bigint id PK
        bigint restaurant_id FK
        string name
        string address
        decimal latitude
        decimal longitude
        string whatsapp
        boolean is_active
    }

    Category {
        bigint id PK
        bigint restaurant_id FK
        string name
        string description
        string image_path
        integer sort_order
        boolean is_active
    }

    Product {
        bigint id PK
        bigint restaurant_id FK
        bigint category_id FK
        string name
        string description
        decimal price
        decimal production_cost
        string image_path
        integer sort_order
        boolean is_active
    }

    ModifierGroup {
        bigint id PK
        bigint restaurant_id FK
        bigint product_id FK
        string name
        string selection_type
        boolean is_required
        integer sort_order
    }

    ModifierOption {
        bigint id PK
        bigint modifier_group_id FK
        string name
        decimal price_adjustment
        decimal production_cost
        integer sort_order
    }

    Order {
        bigint id PK
        bigint restaurant_id FK
        bigint branch_id FK
        bigint customer_id FK
        string delivery_type
        string status
        datetime scheduled_at
        decimal subtotal
        decimal delivery_cost
        decimal total
        string payment_method
        decimal cash_amount
        decimal distance_km
        string address_street
        string address_number
        string address_colony
        string address_references
        decimal latitude
        decimal longitude
    }

    OrderItem {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        integer quantity
        decimal unit_price
        string notes
    }

    OrderItemModifier {
        bigint id PK
        bigint order_item_id FK
        bigint modifier_option_id FK
        decimal price_adjustment
    }

    PaymentMethod {
        bigint id PK
        bigint restaurant_id FK
        string type
        boolean is_active
        string bank_name
        string account_holder
        string clabe
        string alias
    }

    DeliveryRange {
        bigint id PK
        bigint restaurant_id FK
        decimal min_km
        decimal max_km
        decimal price
        integer sort_order
    }

    Customer {
        bigint id PK
        string token UK
        string name
        string phone
    }

    RestaurantSchedule {
        bigint id PK
        bigint restaurant_id FK
        integer day_of_week
        time opens_at
        time closes_at
        boolean is_closed
    }
```

---

## 3. Mapa de Rutas — Admin Web

```mermaid
graph LR
    subgraph Guest["Middleware: guest"]
        LOGIN_GET["GET /login<br/>LoginController@create"]
        LOGIN_POST["POST /login<br/>LoginController@store<br/>+ throttle:5,1"]
    end

    subgraph AuthTenant["Middleware: auth + tenant + HandleInertiaRequests"]
        subgraph Dashboard
            DASH["GET /dashboard<br/>DashboardController@index"]
        end

        subgraph Orders
            ORD_IDX["GET /orders<br/>OrderController@index"]
            ORD_NEW["GET /orders/new-count<br/>OrderController@newCount"]
            ORD_SHOW["GET /orders/{order}<br/>OrderController@show"]
            ORD_ADV["PUT /orders/{order}/status<br/>OrderController@advanceStatus"]
        end

        subgraph Menu
            MENU["GET /menu<br/>MenuController@index"]
            CAT_S["POST /menu/categories<br/>CategoryController@store"]
            CAT_U["PUT /menu/categories/{id}<br/>CategoryController@update"]
            CAT_D["DELETE /menu/categories/{id}<br/>CategoryController@destroy"]
            CAT_R["PATCH /menu/categories/reorder<br/>CategoryController@reorder"]
            PRD_C["GET /menu/products/create<br/>ProductController@create"]
            PRD_S["POST /menu/products<br/>ProductController@store"]
            PRD_E["GET /menu/products/{id}/edit<br/>ProductController@edit"]
            PRD_U["PUT /menu/products/{id}<br/>ProductController@update"]
            PRD_D["DELETE /menu/products/{id}<br/>ProductController@destroy"]
            PRD_T["PATCH /menu/products/{id}/toggle<br/>ProductController@toggle"]
            PRD_R["PATCH /menu/products/reorder<br/>ProductController@reorder"]
        end

        subgraph Settings
            SET_G["GET /settings/general<br/>SettingsController@general"]
            SET_GU["PUT /settings/general<br/>SettingsController@updateGeneral"]
            SET_DM["GET /settings/delivery-methods<br/>DeliveryMethodController@index"]
            SET_DMU["PUT /settings/delivery-methods<br/>DeliveryMethodController@update"]
            SET_SR["GET /settings/shipping-rates<br/>DeliveryRangeController@index"]
            SET_SRS["POST /settings/shipping-rates<br/>DeliveryRangeController@store"]
            SET_SRU["PUT /settings/shipping-rates/{id}<br/>DeliveryRangeController@update"]
            SET_SRD["DELETE /settings/shipping-rates/{id}<br/>DeliveryRangeController@destroy"]
            SET_PM["GET /settings/payment-methods<br/>PaymentMethodController@index"]
            SET_PMU["PUT /settings/payment-methods/{id}<br/>PaymentMethodController@update"]
            SET_P["GET /settings/profile<br/>ProfileController@edit"]
            SET_PU["PUT /settings/profile<br/>ProfileController@update"]
            SET_SCH["GET /settings/schedules<br/>SettingsController@schedules"]
            SET_SCHU["PUT /settings/schedules<br/>SettingsController@updateSchedules"]
            SET_L["GET /settings/limits<br/>LimitsController@index"]
        end

        subgraph Branches
            BR_IDX["GET /branches<br/>BranchController@index"]
            BR_C["GET /branches/create<br/>BranchController@create"]
            BR_S["POST /branches<br/>BranchController@store"]
            BR_E["GET /branches/{id}/edit<br/>BranchController@edit"]
            BR_U["PUT /branches/{id}<br/>BranchController@update"]
            BR_D["DELETE /branches/{id}<br/>BranchController@destroy"]
            BR_T["PATCH /branches/{id}/toggle<br/>BranchController@toggle"]
        end

        LOGOUT["POST /logout<br/>LoginController@destroy"]
    end
```

---

## 4. Mapa de Rutas — API Pública

```mermaid
graph TD
    CLIENT["Client SPA universal<br/>(Vue 3, un solo bundle)"] -->|"URL /api/public/{slug}/*"| MW

    subgraph MW["Middleware: ResolveTenantFromSlug (alias tenant.slug)"]
        direction TB
        EXTRACT["Extraer {slug} del path"]
        FIND["Restaurant::query()->where('slug', slug)->first()"]
        GUARDS["404 si no existe<br/>410 si !canReceiveOrders()"]
        SET["request.attributes.restaurant = restaurant"]
        EXTRACT --> FIND --> GUARDS --> SET
    end

    MW --> ROUTES

    subgraph ROUTES["6 Endpoints de API + 1 global (slug-check)"]
        R1["GET /api/public/{slug}/restaurant<br/>RestaurantController@show<br/>throttle:120,1"]
        R2["GET /api/public/{slug}/menu<br/>MenuController@index<br/>throttle:120,1"]
        R3["GET /api/public/{slug}/branches<br/>BranchController@index<br/>throttle:120,1"]
        R4["POST /api/public/{slug}/delivery/calculate<br/>DeliveryController@calculate<br/>throttle:30,1"]
        R5["POST /api/public/{slug}/coupons/validate<br/>CouponController@validate<br/>throttle:20,1"]
        R6["POST /api/public/{slug}/orders<br/>OrderController@store<br/>throttle:30,1"]
        R7["GET /api/slug-check?slug=x<br/>SlugCheckController@check<br/>throttle:120,1 (sin tenant.slug)"]
    end

    subgraph Resources["API Resources (transformación)"]
        RES1["RestaurantResource"]
        RES2["MenuCategoryResource<br/>→ MenuProductResource<br/>  → ModifierGroupResource<br/>    → ModifierOptionResource"]
        RES3["BranchResource"]
        RES4["DeliveryCalculationResource"]
        RES5["CouponValidationResponse"]
        RES6["OrderConfirmationResource"]
    end

    R1 --> RES1
    R2 --> RES2
    R3 --> RES3
    R4 --> RES4
    R5 --> RES5
    R6 --> RES6

    subgraph Hidden["Campos OCULTOS en API"]
        H1["production_cost ❌"]
        H3["bank details (solo transfer) ⚠️"]
    end
```

> **Nota (Abr 2026):** El patrón legacy por header `X-Restaurant-Token` + columna `restaurants.access_token` fue removido completo. La migración `2026_04_22_122940_drop_access_token_from_restaurants_table.php` eliminó la columna; el middleware `AuthenticateRestaurantToken` y sus rutas `/api/*` sin prefijo fueron borrados.

---

## 5. Mapa de Rutas — SuperAdmin

```mermaid
graph LR
    subgraph AuthSA["Middleware: auth:superadmin + HandleInertiaRequests"]
        SA_DASH["GET /super/dashboard<br/>DashboardController@index"]

        subgraph Restaurants
            SA_R_IDX["GET /super/restaurants<br/>RestaurantController@index"]
            SA_R_C["GET /super/restaurants/create<br/>RestaurantController@create"]
            SA_R_S["POST /super/restaurants<br/>RestaurantController@store"]
            SA_R_SH["GET /super/restaurants/{id}<br/>RestaurantController@show"]
            SA_R_LIM["PUT /super/restaurants/{id}/limits<br/>RestaurantController@updateLimits"]
            SA_R_TOG["PATCH /super/restaurants/{id}/toggle<br/>RestaurantController@toggleActive"]
            SA_R_SLUG["PATCH /super/restaurants/{id}/slug<br/>RestaurantController@renameSlug"]
            SA_R_PW["PUT /super/restaurants/{id}/reset-password<br/>RestaurantController@resetAdminPassword"]
            SA_R_VER["POST /super/restaurants/{id}/send-verification<br/>RestaurantController@sendVerification"]
        end

        subgraph Platform
            SA_PS["GET/PUT /super/platform-settings<br/>PlatformSettingsController (public_menu_base_url)"]
        end

        SA_PROF["GET /super/profile<br/>ProfileController@edit"]
        SA_PROF_U["PUT /super/profile<br/>ProfileController@update"]
        SA_STAT["GET /super/statistics<br/>StatisticsController@index"]
    end
```

---

## 6. Ciclo de Vida de una Petición HTTP

### 6a. Petición Admin (Inertia)

```mermaid
sequenceDiagram
    participant Browser as Navegador
    participant Laravel as Laravel Router
    participant HI as HandleInertiaRequests
    participant Auth as auth middleware
    participant Tenant as EnsureTenantContext
    participant FR as FormRequest
    participant Policy as Policy
    participant Ctrl as Controller
    participant Svc as Service
    participant Model as Model + TenantScope
    participant DB as PostgreSQL
    participant Inertia as Inertia Response

    Browser->>Laravel: HTTP Request
    Laravel->>HI: Pasar por middleware global
    HI->>Auth: Verificar sesión (guard: web)
    Auth-->>Browser: 302 → /login (si no autenticado)
    Auth->>Tenant: Verificar restaurant_id en User
    Tenant-->>Browser: 403 (si sin contexto)
    Tenant->>Ctrl: Despachar a controlador

    alt Tiene FormRequest
        Ctrl->>FR: Validar datos de entrada
        FR-->>Browser: 422 con errores (si falla)
    end

    alt Tiene Policy
        Ctrl->>Policy: authorize(action, model)
        Policy-->>Browser: 404 (si otro tenant)
    end

    Ctrl->>Svc: Delegar lógica de negocio
    Svc->>Model: Query con TenantScope
    Model->>DB: SQL filtrado por restaurant_id
    DB-->>Model: Resultados
    Model-->>Svc: Collection/Model
    Svc-->>Ctrl: Datos procesados
    Ctrl->>Inertia: Inertia::render('Page', props)

    HI->>HI: Inyectar shared data (auth, flash)
    Inertia-->>Browser: JSON (XHR) o HTML (primera carga)
```

### 6b. Petición API Pública

```mermaid
sequenceDiagram
    participant SPA as Client SPA universal
    participant Laravel as Laravel Router
    participant SlugMW as ResolveTenantFromSlug
    participant Throttle as throttle:30,1
    participant FR as StoreOrderRequest
    participant Ctrl as Api\OrderController
    participant OS as OrderService
    participant LS as LimitService
    participant HS as HaversineService
    participant Model as Order Model
    participant DB as PostgreSQL

    SPA->>Laravel: POST /api/public/{slug}/orders
    Laravel->>SlugMW: Extraer {slug} del path
    SlugMW->>DB: SELECT * FROM restaurants WHERE slug = ?
    DB-->>SlugMW: Restaurant (o null)

    alt Slug no existe
        SlugMW-->>SPA: 404 {code: "tenant_not_found"}
    end
    alt !canReceiveOrders() (suspended / past_due / period expired)
        SlugMW-->>SPA: 410 {code: "tenant_unavailable"}
    end

    SlugMW->>Throttle: Verificar rate limit (30/min)

    alt Límite excedido
        Throttle-->>SPA: 429 Too Many Requests
    end

    Throttle->>FR: Validar payload

    alt Validación falla
        FR-->>SPA: 422 Validation Errors
    end

    FR->>Ctrl: Datos validados
    Ctrl->>OS: store(validated, restaurant)
    OS->>LS: isOrderLimitReached(restaurant)

    alt Límite alcanzado
        OS-->>SPA: 422 monthly_limit_reached
    end

    OS->>DB: Validar delivery_type, payment, branch, products, modifiers
    OS->>HS: Calcular distancia (si delivery)
    OS->>DB: BEGIN TRANSACTION + lockForUpdate
    OS->>Model: Order::create(...)
    OS->>Model: OrderItem::create(...) × N
    OS->>Model: OrderItemModifier::create(...) × M
    OS->>DB: COMMIT
    OS-->>Ctrl: OrderCreatedResult (order + whatsappMessage)
    Ctrl-->>SPA: 201 OrderConfirmationResource
```

---

## 7. Flujo: Creación de Pedido (API)

```mermaid
flowchart TD
    START([POST /api/orders]) --> TOKEN{Token válido?}
    TOKEN -->|No| R401[401 Unauthorized]
    TOKEN -->|Sí| THROTTLE{Rate limit OK?}
    THROTTLE -->|No| R429[429 Too Many Requests]
    THROTTLE -->|Sí| VALIDATE{StoreOrderRequest<br/>válido?}
    VALIDATE -->|No| R422V[422 Validation Errors]
    VALIDATE -->|Sí| LIMIT{Límite de pedidos<br/>del periodo?}
    LIMIT -->|Alcanzado| R422L[422 Límite alcanzado]
    LIMIT -->|OK| DTYPE{delivery_type<br/>permitido?}
    DTYPE -->|No| R422D[422 Tipo no permitido]
    DTYPE -->|Sí| OPEN{Restaurante<br/>abierto?}
    OPEN -->|No| R422O[422 Restaurante cerrado]
    OPEN -->|Sí| PAY{Método de pago<br/>activo?}
    PAY -->|No| R422P[422 Método inactivo]
    PAY -->|Sí| CUSTOMER[Buscar/crear Customer]
    CUSTOMER --> BRANCH{Branch activa<br/>y del restaurante?}
    BRANCH -->|No| R422B[422 Sucursal inválida]
    BRANCH -->|Sí| PRODUCTS{Productos activos<br/>y existen?}
    PRODUCTS -->|No| R422PR[422 Producto inválido]
    PRODUCTS -->|Sí| MODS{Modifiers válidos<br/>por producto?}
    MODS -->|No| R422M[422 Modifier inválido]
    MODS -->|Sí| REQUIRED{Required modifiers<br/>presentes?}
    REQUIRED -->|No| R422R[422 Modifier requerido falta]
    REQUIRED -->|Sí| PRICES{Precios coinciden<br/>con DB ±$0.01?}
    PRICES -->|No| R422T[422 Precio manipulado]
    PRICES -->|Sí| CALC[Calcular totales server-side]
    CALC --> DELCOST{Si delivery:<br/>costo por DeliveryRange}
    DELCOST --> CASH{Si cash:<br/>monto cubre total?}
    CASH -->|No| R422C[422 Monto insuficiente]
    CASH -->|Sí| TX["BEGIN TRANSACTION<br/>+ lockForUpdate()"]
    TX --> RECHECK{Re-verificar<br/>límite con lock}
    RECHECK -->|Excedido| ROLLBACK[ROLLBACK + 422]
    RECHECK -->|OK| CREATE["Crear Order<br/>+ OrderItems<br/>+ OrderItemModifiers"]
    CREATE --> COMMIT[COMMIT]
    COMMIT --> WHATSAPP[Generar mensaje WhatsApp]
    WHATSAPP --> R201([201 OrderConfirmationResource])

    style R401 fill:#f66,color:#fff
    style R429 fill:#f66,color:#fff
    style R422V fill:#f96,color:#fff
    style R422L fill:#f96,color:#fff
    style R422D fill:#f96,color:#fff
    style R422O fill:#f96,color:#fff
    style R422P fill:#f96,color:#fff
    style R422B fill:#f96,color:#fff
    style R422PR fill:#f96,color:#fff
    style R422M fill:#f96,color:#fff
    style R422R fill:#f96,color:#fff
    style R422T fill:#f96,color:#fff
    style R422C fill:#f96,color:#fff
    style ROLLBACK fill:#f96,color:#fff
    style R201 fill:#6c6,color:#fff
```

---

## 8. Flujo: Cálculo de Delivery

```mermaid
flowchart TD
    START([POST /api/delivery/calculate]) --> AUTH{Token válido?}
    AUTH -->|No| R401[401]
    AUTH -->|Sí| VALID{lat/lng válidos?}
    VALID -->|No| R422[422]
    VALID -->|Sí| ALLOWS{allows_delivery?}
    ALLOWS -->|No| R422D[422 Delivery no permitido]
    ALLOWS -->|Sí| LOAD[Cargar sucursales activas]
    LOAD --> COUNT{¿Cuántas sucursales?}

    COUNT -->|0| R422N[422 Sin sucursales]

    COUNT -->|1| SINGLE["1 sucursal<br/>1 llamada Google Maps<br/>(distancia real driving)"]

    COUNT -->|2+| HAVERSINE["Haversine pre-filtro<br/>Ordenar por distancia"]
    HAVERSINE --> TOP1["Tomar TOP 1 candidata"]
    TOP1 --> GOOGLE["Google Distance Matrix<br/>(1 request, 1 destino)"]
    GOOGLE --> SELECT["Sin fallback a Haversine<br/>DomainException si falla"]

    SINGLE --> RANGE{Buscar DeliveryRange<br/>para distancia}
    SELECT --> RANGE

    RANGE -->|Sin cobertura| R422R[422 Fuera de cobertura]
    RANGE -->|Encontrado| SCHEDULE["Verificar horario<br/>(soporta overnight)"]
    SCHEDULE --> RESULT([200 DeliveryCalculationResource<br/>branch + cost + time + is_open])

    style R401 fill:#f66,color:#fff
    style R422 fill:#f96,color:#fff
    style R422D fill:#f96,color:#fff
    style R422N fill:#f96,color:#fff
    style R422R fill:#f96,color:#fff
    style RESULT fill:#6c6,color:#fff
```

---

## 9. Flujo: Admin — Kanban de Pedidos

```mermaid
flowchart LR
    subgraph Status["Estado de Pedidos (solo avanza + cancelar)"]
        direction LR
        REC["received<br/>🟡 Nuevos"]
        PREP["preparing<br/>🔵 En preparación"]
        READY["ready<br/>🟠 Listos"]
        DEL["delivered<br/>🟢 Entregados"]
        CANC["cancelled<br/>🔴 Cancelados"]
        REC -->|advanceStatus| PREP
        PREP -->|advanceStatus| READY
        READY -->|advanceStatus| DEL
        REC -->|cancel| CANC
        PREP -->|cancel| CANC
        READY -->|cancel| CANC
    end
```

```mermaid
sequenceDiagram
    participant Admin as Admin (Vue)
    participant Ctrl as OrderController
    participant FR as AdvanceOrderStatusRequest
    participant Policy as OrderPolicy
    participant DB as PostgreSQL

    Admin->>Admin: Drag & Drop en Kanban
    Admin->>Ctrl: PUT /orders/{id}/status
    Ctrl->>Policy: authorize('update', $order)
    Policy-->>Ctrl: ✓ restaurant_id coincide
    Ctrl->>Ctrl: Determinar siguiente status

    Note over Ctrl: received → preparing<br/>preparing → ready<br/>ready → delivered<br/>delivered → ✗ (ya es final)

    alt Status es 'delivered'
        Ctrl-->>Admin: 400 No se puede avanzar
    end

    Ctrl->>DB: UPDATE orders SET status = ? WHERE id = ?
    Ctrl-->>Admin: Redirect con flash success

    loop Polling cada 15s
        Admin->>Ctrl: GET /orders/new-count
        Ctrl->>DB: COUNT orders WHERE status = 'received'
        Ctrl-->>Admin: JSON { count: N }
    end
```

---

## 10. Capa de Servicios

```mermaid
graph TB
    subgraph OrderService["OrderService"]
        OS_STORE["store(validated, restaurant)"]
        OS_WA["buildWhatsAppMessage(order)"]
    end

    subgraph DeliveryService["DeliveryService"]
        DS_CALC["calculate(lat, lng, restaurant)"]
        DS_BUILD["buildResult(restaurant, branch, km, min)"]
        DS_SCHED["checkSchedule(restaurant)"]
    end

    subgraph LimitService["LimitService"]
        LS_REACH["isOrderLimitReached(restaurant)"]
        LS_COUNT["orderCountInPeriod(restaurant)"]
    end

    subgraph StatisticsService["StatisticsService"]
        SS_DASH["getDashboardData(restaurant, from, to)"]
        SS_ORD["ordersCount / preparingCount"]
        SS_REV["revenue / netProfit"]
        SS_BR["ordersByBranch"]
        SS_REC["recentOrders"]
    end

    subgraph HaversineService["HaversineService"]
        HS_DIST["distance(lat1, lon1, lat2, lon2)"]
    end

    subgraph GoogleMapsService["GoogleMapsService"]
        GS_DIST["getDistances(lat, lng, destinations)"]
    end

    OS_STORE -->|"verifica límite"| LS_REACH
    OS_STORE -->|"calcula distancia"| HS_DIST

    DS_CALC -->|"pre-filtra"| HS_DIST
    DS_CALC -->|"distancia real"| GS_DIST
    DS_CALC --> DS_BUILD
    DS_CALC --> DS_SCHED

    SS_DASH --> SS_ORD
    SS_DASH --> SS_REV
    SS_DASH --> SS_BR
    SS_DASH --> SS_REC
    SS_DASH -->|"cuenta periodo"| LS_COUNT

    GS_DIST -->|"HTTP"| GAPI["Google Maps API"]
```

---

## 11. Sistema de Multi-Tenancy

```mermaid
flowchart TD
    subgraph Request["Petición entrante"]
        REQ["HTTP Request"]
    end

    subgraph WebFlow["Admin Web"]
        AUTH["auth middleware<br/>→ User autenticado"]
        TENANT_MW["EnsureTenantContext<br/>→ User.restaurant_id existe"]
        SCOPE["TenantScope::apply()<br/>WHERE restaurant_id = User.restaurant_id"]
    end

    subgraph ApiFlow["API Pública (universal slug-based)"]
        SLUG["ResolveTenantFromSlug<br/>→ Restaurant por slug del path"]
        ATTR["request.attributes.restaurant"]
    end

    subgraph SAFlow["SuperAdmin"]
        SA_AUTH["auth:superadmin"]
        NO_SCOPE["withoutGlobalScope(TenantScope)<br/>→ Acceso global a todos los restaurantes"]
    end

    REQ --> AUTH --> TENANT_MW --> SCOPE
    REQ --> SLUG --> ATTR
    REQ --> SA_AUTH --> NO_SCOPE

    subgraph TenantModels["Modelos con BelongsToTenant"]
        M1["Branch"]
        M2["Category"]
        M3["Product"]
        M4["ModifierGroup"]
        M5["Order"]
        M6["PaymentMethod"]
        M7["DeliveryRange"]
    end

    SCOPE --> TenantModels

    subgraph GlobalModels["Modelos Globales (sin tenant)"]
        G1["Customer"]
        G2["SuperAdmin"]
    end

    subgraph Protection["Protecciones"]
        P1["IDOR → 404 (no 403)"]
        P2["Policy: restaurant_id match"]
        P3["FormRequest: category_id scoped"]
    end
```

---

## 12. Middleware y Guards

```mermaid
flowchart TD
    subgraph Guards["Authentication Guards"]
        WEB["web (default)<br/>Session-based<br/>Model: User"]
        SUPERADMIN["superadmin<br/>Session-based<br/>Model: SuperAdmin"]
        API_SLUG["Public API<br/>(no guard, tenant resuelto por URL)<br/>Path: /api/public/{slug}/*"]
    end

    subgraph Middleware["Middleware Stack"]
        direction TB
        HI["HandleInertiaRequests<br/>(global en web)<br/>Comparte: auth.user, flash, billing, menu_base_url"]
        AUTH_MW["auth<br/>Verifica sesión web"]
        VERIFIED_MW["verified<br/>Requiere email verificado (self-signup)"]
        GUEST_MW["guest<br/>Redirige si ya autenticado"]
        AUTH_SA["auth:superadmin<br/>Verifica sesión superadmin"]
        GUEST_SA["guest:superadmin<br/>Redirige si ya autenticado"]
        TENANT_MW["tenant (EnsureTenantContext)<br/>403 si User sin restaurant_id"]
        SLUG_MW["tenant.slug<br/>(ResolveTenantFromSlug)<br/>404 si slug inexistente, 410 si !canReceiveOrders()"]
        THRT5["throttle:5,1<br/>Login: 5 req/min"]
        THRT30["throttle:30,1<br/>Orders API: 30 req/min"]
    end

    subgraph Routes["Aplicación por Grupo de Rutas"]
        R_ADMIN["Admin Web<br/>HI → auth → tenant"]
        R_GUEST["Login/Register Pages<br/>HI → guest"]
        R_API["API Pública universal<br/>tenant.slug + throttle"]
        R_SA["SuperAdmin<br/>HI → auth:superadmin"]
        R_SA_G["SuperAdmin Login<br/>HI → guest:superadmin"]
    end

    HI --> R_ADMIN
    AUTH_MW --> R_ADMIN
    VERIFIED_MW --> R_ADMIN
    TENANT_MW --> R_ADMIN

    HI --> R_GUEST
    GUEST_MW --> R_GUEST

    SLUG_MW --> R_API
    THRT30 --> R_API

    HI --> R_SA
    AUTH_SA --> R_SA

    HI --> R_SA_G
    GUEST_SA --> R_SA_G
```

---

## 13. Descripción de Componentes

### Controllers

| Controlador | Responsabilidad | Modelos | Servicios |
|---|---|---|---|
| **LoginController** | Autenticación admin (login/logout) | User (vía Auth) | — |
| **DashboardController** | Métricas y KPIs del restaurante | Restaurant, Order | StatisticsService |
| **OrderController** (Web) | Kanban de pedidos, detalle, avance de status | Order, Branch | LimitService |
| **MenuController** | Listado de menú con categorías y productos | Category, Product | — |
| **CategoryController** | CRUD de categorías con imágenes y reorden | Category | — |
| **ProductController** | CRUD de productos con modifiers inline, imágenes, toggle | Product, Category, ModifierGroup, ModifierOption | — |
| **BranchController** | CRUD de sucursales con validación de última activa | Branch | — |
| **SettingsController** | Configuración general (nombre, logo, redes) y horarios | Restaurant, RestaurantSchedule | — |
| **DeliveryMethodController** | Activar/desactivar tipos de entrega | Restaurant, DeliveryRange | — |
| **DeliveryRangeController** | CRUD de tarifas de envío por rango de km | DeliveryRange | — |
| **PaymentMethodController** | Configuración de métodos de pago | PaymentMethod | — |
| **ProfileController** | Edición de perfil del admin | User | — |
| **LimitsController** | Vista de límites de pedidos del periodo | Restaurant, Branch | LimitService |
| **CancellationController** | KPIs y desglose de cancelaciones con filtros | Order, Branch | CancellationService |
| **MapController** | Mapa operativo con markers interactivos por pedido | Order, Branch | — |

### Controllers API

| Controlador | Responsabilidad | Modelos | Servicios | Resource |
|---|---|---|---|---|
| **Api\RestaurantController** | Info del restaurante (horarios, pagos, estado) | Restaurant | LimitService (vía Resource) | RestaurantResource |
| **Api\MenuController** | Menú público (sin production_cost) | Category, Product | — | MenuCategoryResource |
| **Api\BranchController** | Sucursales activas | Branch | — | BranchResource |
| **Api\DeliveryController** | Cálculo de costo/tiempo de envío | Restaurant, Branch | DeliveryService | DeliveryCalculationResource |
| **Api\OrderController** | Creación de pedidos | Order, Customer | OrderService | OrderConfirmationResource |

### Controllers SuperAdmin

| Controlador | Responsabilidad | Modelos | Servicios |
|---|---|---|---|
| **SuperAdmin\DashboardController** | Métricas globales cross-restaurant | Restaurant, Order | — |
| **SuperAdmin\RestaurantController** | Gestión completa de restaurantes, límites, tokens, passwords | Restaurant, User, Branch, PaymentMethod | LimitService |
| **SuperAdmin\ProfileController** | Perfil del SuperAdmin | SuperAdmin | — |
| **SuperAdmin\StatisticsController** | Estadísticas globales (30 días, top 10) | Order, Restaurant | — |

### Services

| Servicio | Responsabilidad | Dependencias |
|---|---|---|
| **OrderService** | Pipeline de 15 pasos para crear pedidos: validación, anti-tampering, transacción con lock, mensaje WhatsApp | LimitService, HaversineService |
| **DeliveryService** | Selección inteligente de sucursal y cálculo de costo. Optimiza llamadas a Google (0 para 1 sucursal, 1 para 2+) | HaversineService, GoogleMapsService |
| **LimitService** | Conteo de pedidos en periodo configurable por restaurante | — |
| **StatisticsService** | Agregaciones para dashboard: revenue, profit neto (incluye modifiers), pedidos por sucursal | LimitService |
| **HaversineService** | Distancia en km entre dos coordenadas (fórmula del gran círculo) | — |
| **GoogleMapsService** | Wrapper para Google Distance Matrix API (modo driving) | HTTP Client |
| **CancellationService** | KPIs de cancelaciones: tasa, motivo top, desglose por razón/sucursal/día | — |

### Policies (7)

Todas siguen el mismo patrón: verificar que `User.restaurant_id === Model.restaurant_id`.

- **OrderPolicy** — viewAny, view, update
- **BranchPolicy** — viewAny, view, create, update, delete
- **CategoryPolicy** — viewAny, view, create, update, delete
- **ProductPolicy** — viewAny, view, create, update, delete
- **ModifierGroupPolicy** — viewAny, view, create, update, delete
- **ModifierOptionPolicy** — viewAny, create, update (vía modifierGroup.restaurant_id), delete
- **DeliveryRangePolicy** — viewAny, create, update, delete
- **PaymentMethodPolicy** — viewAny, view, update

### Form Requests (22+)

| Área | Requests | Validaciones destacadas |
|---|---|---|
| **Pedidos API** | StoreOrderRequest | items max:50, qty max:100, modifier_option_id distinct, precios max:99999.99 |
| **Cancelación** | CancelOrderRequest | cancellation_reason required string |
| **Categorías** | Store/UpdateCategoryRequest | imagen mimes bloqueando SVG |
| **Productos** | Store/UpdateProductRequest | category_id scoped a restaurant (anti-IDOR), modifier groups inline |
| **Sucursales** | Store/UpdateBranchRequest | WhatsApp regex 10 dígitos |
| **Delivery** | StoreDeliveryRangeRequest, UpdateDeliveryRangeRequest | Validación de overlap entre rangos |
| **Delivery Methods** | UpdateDeliveryMethodsRequest | Requiere DeliveryRanges para activar delivery |
| **Pagos** | UpdatePaymentMethodRequest | Mínimo 1 activo, CLABE 16/18 dígitos, datos bancarios required para transfer |
| **General** | UpdateGeneralSettingsRequest | Logo mimes bloqueando SVG |
| **Horarios** | UpdateRestaurantScheduleRequest | Exactamente 7 días, formato HH:MM |
| **Perfil** | UpdateProfileRequest, UpdateSuperAdminProfileRequest | Validación de contraseña actual |
| **SuperAdmin** | CreateRestaurantRequest, UpdateRestaurantLimitsRequest, ResetAdminPasswordRequest | Slug regex, email unique, límite no menor a count actual |

### API Resources (10)

| Resource | Campos clave | Oculta |
|---|---|---|
| **RestaurantResource** | name, slug, is_open, delivery_methods, orders_limit_reached, branding colors, logo_url, today_schedule, closure_reason | — |
| **MenuCategoryResource** | name, image_url, products (nested) | — |
| **MenuProductResource** | name, price, image_url, modifier_groups (nested) | **production_cost** |
| **ModifierGroupResource** | name, selection_type, is_required, options (nested) | — |
| **ModifierOptionResource** | name, price_adjustment | **production_cost** |
| **BranchResource** | name, address, lat/lng, whatsapp | — |
| **DeliveryCalculationResource** | branch info, distance_km, duration_minutes, delivery_cost, is_open | — |
| **OrderConfirmationResource** | order_id, order_number (#NNNN), branch_whatsapp, whatsapp_message | — |
| **PaymentMethodResource** | type, label; bank details solo si type=transfer | bank details (otros tipos) |
| **RestaurantScheduleResource** | day_of_week, opens_at (HH:MM), closes_at (HH:MM), is_closed | — |

---

## Estadísticas del Backend

| Métrica | Valor |
|---|---|
| **Rutas totales** | 63 |
| **Controladores** | 24 |
| **Modelos Eloquent** | 15 |
| **Servicios** | 7 |
| **Form Requests** | 22+ |
| **Policies** | 7 (8 clases) |
| **API Resources** | 10 |
| **Middleware custom** | 3 |
| **Guards** | 2 (web, superadmin) + token custom |
| **DTOs** | 2 (OrderCreatedResult, DeliveryResult) |
| **Tests** | 149 (PHPUnit) |
