# Módulo 09 — Servicio de Delivery

> Servicio técnico interno — sin pantalla propia.
> Invocado desde: `POST /api/delivery/calculate` (API pública)

---

## Descripción General

Servicio que encapsula toda la lógica de:
1. Detectar qué sucursal es la más cercana al cliente.
2. Calcular la distancia real por calles entre el cliente y esa sucursal.
3. Determinar el costo de envío según los rangos configurados.
4. Validar si el cliente está dentro del radio de cobertura.
5. Validar si la sucursal asignada está abierta en el momento del pedido.

Este es el módulo más costoso en términos de uso de APIs externas. Está diseñado para **minimizar las llamadas a Google Distance Matrix** mediante un pre-filtro Haversine.

---

## Flujo Completo de Cálculo

```
Input:
  - client_latitude, client_longitude
  - restaurant_id (inyectado por middleware `ResolveTenantFromSlug` desde `/api/public/{slug}/*`)

PASO 1: Cargar sucursales activas del restaurante
  ↓
PASO 2: ¿Una sola sucursal?
  SI → Google Maps directo (1 llamada, driving distance). Ir a PASO 5.
  NO → Continuar con PASO 3.
  ↓
PASO 3: Pre-filtro Haversine (SIN costo de API)
  - Calcular distancia en línea recta entre cliente y cada sucursal activa.
  - Ordenar sucursales por distancia Haversine (ascendente).
  - Conservar solo la TOP 1 sucursal más cercana como candidata.
  ↓
PASO 4: Google Distance Matrix API (solo para la candidata)
  - Enviar 1 request con: origin = client_location, destination = candidata.
  - Obtener: distancia_real_km y duration_seconds.
  - Esta es la sucursal_asignada.
  ↓
PASO 5: Calcular costo de envío por rangos
  - Cargar delivery_ranges del restaurante (ordenados por sort_order).
  - Buscar el rango donde: range.min_km <= distancia_real_km < range.max_km
  - El precio de ese rango es el costo_envio.
  ↓
PASO 6: Validar cobertura
  - Si distancia_real_km > max_km del último rango → FUERA DE COBERTURA.
  ↓
PASO 7: Validar horario del restaurante
  - Obtener RestaurantSchedule del día de la semana actual.
  - Si is_closed = true → FUERA DE HORARIO.
  - Si NOW < opens_at OR NOW > closes_at → FUERA DE HORARIO.
  - Si no hay horario configurado → CERRADO (no se asume abierto).
  ↓
Output:
  {
    branch_id, branch_name, branch_address, branch_whatsapp,
    distance_km, duration_minutes, delivery_cost,
    is_in_coverage (boolean),
    is_open (boolean),
    schedule (horarios del día para mostrar al cliente si está cerrado)
  }
```

---

## Fórmula Haversine

Calcula la distancia en línea recta entre dos coordenadas geográficas. No tiene costo de API.

```php
// Implementación PHP
function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $R = 6371; // Radio de la Tierra en km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c; // Distancia en km
}
```

**Umbral del pre-filtro:** Conservar siempre la TOP 1 sucursal con menor distancia Haversine. Esto garantiza una sola llamada a Google Maps independientemente del número de sucursales.

---

## Google Distance Matrix API

**Request format:**
```
GET https://maps.googleapis.com/maps/api/distancematrix/json
  ?origins={client_lat},{client_lng}
  &destinations={branch1_lat},{branch1_lng}|{branch2_lat},{branch2_lng}
  &mode=driving
  &key={GOOGLE_MAPS_API_KEY}
```

**Response relevante:**
```json
{
  "rows": [{
    "elements": [
      { "distance": { "value": 3200 }, "duration": { "value": 900 } },
      { "distance": { "value": 5100 }, "duration": { "value": 1200 } }
    ]
  }]
}
```
- `distance.value` → distancia en metros → dividir entre 1000 para km.
- `duration.value` → duración en segundos → dividir entre 60 para minutos.

**Costo:** Google cobra por "elemento" consultado. Cada (origin × destination) = 1 elemento.
Con el pre-filtro Haversine, se consulta siempre 1 solo destino = 1 elemento por request.

---

## Cálculo de Costo de Envío

```php
// delivery_ranges ordenados por sort_order (min_km ascendente)
// distance_km = distancia real retornada por Google Distance Matrix

foreach ($deliveryRanges as $range) {
    if ($distanceKm >= $range->min_km && $distanceKm < $range->max_km) {
        return $range->price; // Precio fijo del rango
    }
}

// Si superó el max_km del último rango:
return null; // FUERA DE COBERTURA
```

**Ejemplo con rangos:**
```
0-2 km   → $0   (gratis)
2-5 km   → $30
5-10 km  → $60
10-15 km → $90
> 15 km  → FUERA DE COBERTURA
```

---

## Validación de Horario

Los horarios se validan a **nivel restaurante** (no por sucursal). Se usa el modelo `RestaurantSchedule`.

```php
// Restaurant::isCurrentlyOpen()
$now = Carbon::now();
$schedule = $this->schedules->firstWhere('day_of_week', $now->dayOfWeek);

if (!$schedule || $schedule->is_closed) return false;
if (!$schedule->opens_at || !$schedule->closes_at) return false;

$currentTime = $now->format('H:i:s');
return $currentTime >= $schedule->opens_at && $currentTime <= $schedule->closes_at;
```

---

## Modelos Involucrados

| Modelo | Tabla | Uso |
|---|---|---|
| `Branch` | `branches` | Coordenadas y WhatsApp de sucursales activas |
| `RestaurantSchedule` | `restaurant_schedules` | Horarios del restaurante por día |
| `DeliveryRange` | `delivery_ranges` | Rangos de distancia y precios |
| `Restaurant` | `restaurants` | Para obtener los rangos del tenant |

---

## Clases / Servicios

```
app/Services/
├── DeliveryService.php       ← Orquesta todo el flujo
├── HaversineService.php      ← Cálculo de distancia en línea recta
└── GoogleMapsService.php     ← Wrapper de Google Distance Matrix API
```

**`DeliveryService`:**
```php
class DeliveryService
{
    public function calculate(
        float $clientLat,
        float $clientLng,
        Restaurant $restaurant
    ): DeliveryResult { ... }
}
```

**`DeliveryResult` (DTO):**
```php
class DeliveryResult
{
    public Branch $branch;
    public float $distanceKm;
    public int $durationMinutes;
    public float $deliveryCost;
    public bool $isInCoverage;
    public bool $isOpen;
    public ?RestaurantSchedule $schedule;
}
```

---

## Consideraciones de Costo y Optimización

| Escenario | Llamadas a Google Distance Matrix |
|---|---|
| Restaurante con 1 sucursal activa | **1 request, 1 elemento** (Google Maps directo, driving distance) |
| Restaurante con 2+ sucursales | **1 request, 1 elemento** (Haversine pre-filtra TOP 1 → Google Maps para esa candidata) |

**Máximo 1 llamada garantizada:** Siempre se hace exactamente 1 request a Google Maps con 1 solo destino. No hay fallback a Haversine — si Google Maps falla, se lanza `DomainException`.

---

## Módulos Relacionados

| Módulo | Relación |
|---|---|
| **[08-customer-flow.md](./08-customer-flow.md)** | El Paso 2 del flujo del cliente llama a este servicio a través de la API. |
| **[10-api.md](./10-api.md)** | El endpoint `POST /api/delivery/calculate` es el punto de entrada de este servicio. |
| **[05-branches.md](./05-branches.md)** | Las coordenadas de las sucursales son el input de este servicio. |
| **[06-settings.md](./06-settings.md)** | Los `delivery_ranges` configurados en ar_16 son usados por este servicio para calcular el costo y la cobertura. |

---

_PideAqui — Módulo Delivery Service v1.2 — Marzo 2026_
