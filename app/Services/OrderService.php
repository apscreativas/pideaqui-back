<?php

namespace App\Services;

use App\DTOs\OrderCreatedResult;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DeliveryRange;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly LimitService $limitService,
        private readonly GoogleMapsService $googleMaps,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException|\DomainException
     */
    public function store(array $validated, Restaurant $restaurant): OrderCreatedResult
    {
        // PASO 1 — Order limit check (outside transaction for fast fail, re-checked inside).
        if ($this->limitService->isOrderLimitReached($restaurant)) {
            throw new \DomainException('monthly_limit_reached');
        }

        // PASO 1b — Validate delivery type is allowed by the restaurant.
        $deliveryType = $validated['delivery_type'];
        $allowsMap = ['delivery' => 'allows_delivery', 'pickup' => 'allows_pickup', 'dine_in' => 'allows_dine_in'];
        if (! $restaurant->{$allowsMap[$deliveryType]}) {
            throw ValidationException::withMessages(['delivery_type' => ['Este restaurante no permite este tipo de entrega.']]);
        }

        // PASO 1c — Validate restaurant is currently open (or order is scheduled within operating hours).
        $restaurant->load('schedules');
        $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;

        if ($scheduledAt) {
            // Validate scheduled_at falls within the restaurant's schedule for that day.
            $schedule = $restaurant->schedules->firstWhere('day_of_week', $scheduledAt->dayOfWeek);

            if (! $schedule || $schedule->is_closed || ! $schedule->opens_at || ! $schedule->closes_at) {
                throw ValidationException::withMessages(['scheduled_at' => ['El restaurante no opera en el día y hora seleccionados.']]);
            }

            $time = $scheduledAt->format('H:i:s');

            if ($schedule->opens_at > $schedule->closes_at) {
                // Overnight schedule (e.g., 20:00–02:00).
                $withinSchedule = $time >= $schedule->opens_at || $time <= $schedule->closes_at;
            } else {
                $withinSchedule = $time >= $schedule->opens_at && $time <= $schedule->closes_at;
            }

            if (! $withinSchedule) {
                throw ValidationException::withMessages(['scheduled_at' => ['La hora programada está fuera del horario de operación.']]);
            }
        } else {
            // Immediate order — restaurant must be open right now.
            if (! $restaurant->isCurrentlyOpen()) {
                throw ValidationException::withMessages(['scheduled_at' => ['El restaurante está cerrado en este momento.']]);
            }
        }

        // PASO 1d — Validate payment method is active for this restaurant.
        $hasPaymentMethod = PaymentMethod::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('type', $validated['payment_method'])
            ->where('is_active', true)
            ->exists();

        if (! $hasPaymentMethod) {
            throw ValidationException::withMessages(['payment_method' => ['Este método de pago no está disponible.']]);
        }

        // PASO 2 — Find or create customer, always update name/phone.
        $customer = Customer::firstOrCreate(
            ['token' => $validated['customer']['token']],
            ['name' => $validated['customer']['name'], 'phone' => $validated['customer']['phone']],
        );
        $customer->update(['name' => $validated['customer']['name'], 'phone' => $validated['customer']['phone']]);

        // PASO 3 — Validate branch belongs to this restaurant AND is active.
        $branch = Branch::query()
            ->where('id', $validated['branch_id'])
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => ['La sucursal no pertenece a este restaurante.']]);
        }

        if (! $branch->is_active) {
            throw ValidationException::withMessages(['branch_id' => ['La sucursal no está activa.']]);
        }

        // PASO 4 — Load and validate all sellable entities (products + promotions).
        $productItems = collect($validated['items'])->filter(fn ($i) => ! empty($i['product_id']));
        $promotionItems = collect($validated['items'])->filter(fn ($i) => ! empty($i['promotion_id']));

        $products = collect();
        if ($productItems->isNotEmpty()) {
            $productIds = $productItems->pluck('product_id')->unique()->values();
            $products = Product::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->whereIn('id', $productIds)
                ->with('category')
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages(['items' => ['Uno o más productos no están disponibles.']]);
            }

            // Validate each product's category is active and currently available (schedule).
            foreach ($products as $product) {
                if (! $product->category || ! $product->category->is_active) {
                    throw ValidationException::withMessages(['items' => ['La categoría de "'.$product->name.'" no está disponible.']]);
                }

                if (! $product->category->isCurrentlyAvailable()) {
                    throw ValidationException::withMessages(['items' => ['"'.$product->name.'" no está disponible en este horario.']]);
                }
            }
        }

        $promotions = collect();
        if ($promotionItems->isNotEmpty()) {
            $promoIds = $promotionItems->pluck('promotion_id')->unique()->values();
            $promotions = Promotion::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->whereIn('id', $promoIds)
                ->with(['modifierGroups.options'])
                ->get()
                ->filter(fn (Promotion $p) => $p->isCurrentlyActive())
                ->keyBy('id');

            if ($promotions->count() !== $promoIds->count()) {
                throw ValidationException::withMessages(['items' => ['Una o más promociones no están disponibles.']]);
            }
        }

        // PASO 4b — Normalize all items into a unified structure for steps 5-8.
        // Each normalized item has: entity (model), entityName, ownerColumn, ownerId, itemData, productId, promotionId.
        $normalizedItems = collect();
        foreach ($productItems as $itemData) {
            $entity = $products[$itemData['product_id']];
            $normalizedItems->push([
                'entity' => $entity,
                'owner_column' => 'product_id',
                'owner_id' => $entity->id,
                'product_id' => $entity->id,
                'promotion_id' => null,
                'data' => $itemData,
            ]);
        }
        foreach ($promotionItems as $itemData) {
            $entity = $promotions[$itemData['promotion_id']];
            $normalizedItems->push([
                'entity' => $entity,
                'owner_column' => 'promotion_id',
                'owner_id' => $entity->id,
                'product_id' => null,
                'promotion_id' => $entity->id,
                'data' => $itemData,
            ]);
        }

        // PASO 5 — Validate modifier options, required groups, and single-selection groups (unified).
        $allValidOptions = collect();

        foreach ($normalizedItems as $normalized) {
            $itemData = $normalized['data'];
            $entity = $normalized['entity'];
            $itemOptionIds = collect($itemData['modifiers'] ?? [])->pluck('modifier_option_id')->filter()->unique()->values();

            if ($itemOptionIds->isNotEmpty()) {
                // 5a — Validate options belong to this entity's modifier groups.
                $itemValidOptions = ModifierOption::query()
                    ->whereIn('id', $itemOptionIds)
                    ->whereHas('modifierGroup', fn ($q) => $q->where('restaurant_id', $restaurant->id)->where($normalized['owner_column'], $normalized['owner_id']))
                    ->get()
                    ->keyBy('id');

                if ($itemValidOptions->count() !== $itemOptionIds->count()) {
                    throw ValidationException::withMessages(['items' => ['Uno o más modificadores no son válidos para "'.$entity->name.'".']]);
                }

                foreach ($itemValidOptions as $id => $opt) {
                    $allValidOptions->put($id, $opt);
                }
            }

            $sentOptionIds = collect($itemData['modifiers'] ?? [])->pluck('modifier_option_id')->toArray();

            // 5b — Validate required modifier groups have at least one selection.
            $requiredGroups = ModifierGroup::query()
                ->where($normalized['owner_column'], $normalized['owner_id'])
                ->where('restaurant_id', $restaurant->id)
                ->where('is_required', true)
                ->get();

            foreach ($requiredGroups as $group) {
                $groupOptionIds = $group->options()->pluck('id')->toArray();
                if (empty(array_intersect($sentOptionIds, $groupOptionIds))) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo de modificadores "'.$group->name.'" es obligatorio para "'.$entity->name.'".'],
                    ]);
                }
            }

            // 5c — Validate single-selection groups have at most one option.
            $singleGroups = ModifierGroup::query()
                ->where($normalized['owner_column'], $normalized['owner_id'])
                ->where('restaurant_id', $restaurant->id)
                ->where('selection_type', 'single')
                ->get();

            foreach ($singleGroups as $group) {
                $groupOptionIds = $group->options()->pluck('id')->toArray();
                if (count(array_intersect($sentOptionIds, $groupOptionIds)) > 1) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" solo permite una opción para "'.$entity->name.'".'],
                    ]);
                }
            }
        }

        // PASO 6 — Anti-tampering: validate prices match the database (unified).
        foreach ($normalizedItems as $normalized) {
            $entity = $normalized['entity'];
            $itemData = $normalized['data'];
            $unitPrice = (float) $itemData['unit_price'];

            if (abs($unitPrice - (float) $entity->price) > 0.01) {
                throw ValidationException::withMessages(['items' => ['El precio de "'.$entity->name.'" no coincide.']]);
            }

            foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                $option = $allValidOptions[$modifierData['modifier_option_id']];

                if (abs((float) $modifierData['price_adjustment'] - (float) $option->price_adjustment) > 0.01) {
                    throw ValidationException::withMessages(['items' => ['El precio del modificador "'.$option->name.'" no coincide.']]);
                }
            }
        }

        // PASO 7 — Calculate totals in backend (never trust the client).
        $subtotal = 0.0;
        foreach ($normalizedItems as $normalized) {
            $entity = $normalized['entity'];
            $itemData = $normalized['data'];
            $modifierTotal = collect($itemData['modifiers'] ?? [])->sum(fn (array $m) => (float) $allValidOptions[$m['modifier_option_id']]->price_adjustment);
            $subtotal += ((float) $entity->price + $modifierTotal) * (int) $itemData['quantity'];
        }

        // PASO 7b — Validate delivery cost against delivery ranges (distance computed server-side).
        $deliveryCost = 0.0;
        $distanceKm = null;
        if ($validated['delivery_type'] === 'delivery') {
            // Compute driving distance server-side via Google Maps — never trust client-supplied distance_km.
            $distanceKm = $this->getDrivingDistance(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                $branch,
            );

            $range = DeliveryRange::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('min_km', '<=', $distanceKm)
                ->where('max_km', '>', $distanceKm)
                ->first();

            if (! $range) {
                throw ValidationException::withMessages(['delivery_cost' => ['No hay rango de entrega configurado para esta distancia.']]);
            }

            $deliveryCost = (float) $range->price;
        }

        $total = $subtotal + $deliveryCost;

        // Validate cash_amount covers the total.
        if (($validated['payment_method'] === 'cash') && ! empty($validated['cash_amount']) && ((float) $validated['cash_amount'] < $total)) {
            throw ValidationException::withMessages([
                'cash_amount' => ['El monto pagado debe ser mayor o igual al total del pedido.'],
            ]);
        }

        // PASO 8 — Create Order inside transaction with limit re-check (prevents TOCTOU race condition).
        $order = DB::transaction(function () use ($validated, $restaurant, $branch, $customer, $normalizedItems, $allValidOptions, $subtotal, $deliveryCost, $distanceKm, $total): Order {
            // Re-check order limit with a FOR UPDATE lock on the restaurant row.
            $lockedRestaurant = Restaurant::query()->lockForUpdate()->find($restaurant->id);
            if ($this->limitService->isOrderLimitReached($lockedRestaurant)) {
                throw new \DomainException('monthly_limit_reached');
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'delivery_type' => $validated['delivery_type'],
                'status' => 'received',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'] ?? null,
                'distance_km' => $distanceKm,
                'address_street' => $validated['address_street'] ?? null,
                'address_number' => $validated['address_number'] ?? null,
                'address_colony' => $validated['address_colony'] ?? null,
                'address_references' => $validated['address_references'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            // Create order items (unified for products and promotions).
            foreach ($normalizedItems as $normalized) {
                $entity = $normalized['entity'];
                $itemData = $normalized['data'];

                $item = $order->items()->create([
                    'product_id' => $normalized['product_id'],
                    'promotion_id' => $normalized['promotion_id'],
                    'product_name' => $entity->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $entity->price,
                    'production_cost' => $entity->production_cost,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                    $option = $allValidOptions[$modifierData['modifier_option_id']];
                    $item->modifiers()->create([
                        'modifier_option_id' => $option->id,
                        'modifier_option_name' => $option->name,
                        'price_adjustment' => $option->price_adjustment,
                        'production_cost' => $option->production_cost,
                    ]);
                }
            }

            return $order;
        });

        // PASO 9 — Load relations needed for WhatsApp message.
        $order->load(['items.modifiers', 'branch', 'customer']);

        $whatsappMessage = $this->buildWhatsAppMessage($order);

        return new OrderCreatedResult(order: $order, whatsappMessage: $whatsappMessage);
    }

    /**
     * Get driving distance via Google Maps. Throws if unavailable.
     */
    private function getDrivingDistance(float $clientLat, float $clientLng, Branch $branch): float
    {
        $destinations = collect([['latitude' => (float) $branch->latitude, 'longitude' => (float) $branch->longitude]]);
        $results = $this->googleMaps->getDistances($clientLat, $clientLng, $destinations);

        if ($results[0]['distance_km'] >= PHP_FLOAT_MAX) {
            throw ValidationException::withMessages(['delivery_cost' => ['No se pudo calcular la distancia de entrega. Intenta de nuevo más tarde.']]);
        }

        return round($results[0]['distance_km'], 2);
    }

    private function buildWhatsAppMessage(Order $order): string
    {
        $deliveryLine = match ($order->delivery_type) {
            'delivery' => '🛵 *A domicilio*',
            'pickup' => '🏃 *Recoger en local*',
            'dine_in' => '🍴 *Comer aquí*',
            default => '',
        };

        $lines = [
            'Hola! Quiero hacer un pedido:',
            '',
            $deliveryLine,
            '',
            '📝 *Mi pedido:*',
        ];

        foreach ($order->items as $item) {
            $modifierNames = $item->modifiers
                ->map(fn ($m) => $m->modifier_option_name)
                ->filter()
                ->join(', ');

            $modifierTotal = $item->modifiers->sum(fn ($m) => (float) $m->price_adjustment);
            $itemTotal = ((float) $item->unit_price + $modifierTotal) * $item->quantity;

            $desc = "- {$item->quantity}x {$item->product_name}";

            if ($modifierNames) {
                $desc .= " ({$modifierNames})";
            }

            if ($item->notes) {
                $desc .= " - {$item->notes}";
            }

            $desc .= ' · $'.number_format($itemTotal, 2);
            $lines[] = $desc;
        }

        if ($order->delivery_type === 'delivery' && $order->address_street) {
            $address = "{$order->address_street} #{$order->address_number}, Col. {$order->address_colony}";
            $lines[] = '';
            $lines[] = "📍 *Dirección:* {$address}";

            if ($order->address_references) {
                $lines[] = "*Referencias:* {$order->address_references}";
            }

            if ($order->latitude && $order->longitude) {
                $lines[] = "📌 *Ubicación:* https://maps.google.com/?q={$order->latitude},{$order->longitude}";
            }
        }

        $lines[] = '';
        $lines[] = '💰 *Subtotal:* $'.number_format((float) $order->subtotal, 2);

        if ($order->delivery_type === 'delivery') {
            $lines[] = '🚚 *Envío:* $'.number_format((float) $order->delivery_cost, 2);
        }

        $lines[] = '✅ *Total:* $'.number_format((float) $order->total, 2);
        $lines[] = '';

        $paymentLabel = match ($order->payment_method) {
            'cash' => 'Efectivo',
            'terminal' => 'Terminal bancaria',
            'transfer' => 'Transferencia',
            default => $order->payment_method,
        };

        $lines[] = "💳 *Pago:* {$paymentLabel}";

        if ($order->payment_method === 'cash' && $order->cash_amount) {
            $lines[] = '💵 *Paga con:* $'.number_format((float) $order->cash_amount, 2);
            $change = (float) $order->cash_amount - (float) $order->total;
            if ($change > 0) {
                $lines[] = '🔄 *Cambio:* $'.number_format($change, 2);
            }
        }

        if ($order->payment_method === 'transfer') {
            $transferPm = PaymentMethod::query()
                ->where('restaurant_id', $order->restaurant_id)
                ->where('type', 'transfer')
                ->first();

            if ($transferPm) {
                if ($transferPm->bank_name) {
                    $lines[] = "🏦 *Banco:* {$transferPm->bank_name}";
                }
                if ($transferPm->account_holder) {
                    $lines[] = "👤 *Titular:* {$transferPm->account_holder}";
                }
                if ($transferPm->clabe) {
                    $lines[] = "📋 *CLABE:* {$transferPm->clabe}";
                }
            }
        }

        if ($order->scheduled_at) {
            $lines[] = '⏰ *Hora programada:* '.$order->scheduled_at->format('H:i');
        }

        $lines[] = '';
        $lines[] = '¡Gracias! 🙌';

        return implode("\n", $lines);
    }
}
