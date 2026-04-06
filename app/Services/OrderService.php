<?php

namespace App\Services;

use App\DTOs\OrderCreatedResult;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\Customer;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTemplate;
use App\Models\Order;
use App\Models\OrderEvent;
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
        // Uses getResolvedScheduleForDate() which checks special dates > regular schedule.
        $restaurant->load('schedules');
        $scheduledAt = isset($validated['scheduled_at'])
            ? Carbon::parse($validated['scheduled_at'], config('app.timezone'))
            : null;

        if ($scheduledAt) {
            // Resolve the effective schedule for the scheduled date (special date > regular).
            $resolved = $restaurant->getResolvedScheduleForDate($scheduledAt);

            if ($resolved['source'] === 'closed') {
                $msg = 'El restaurante estará cerrado ese día';
                if ($resolved['label']) {
                    $msg .= ': '.$resolved['label'];
                }
                throw ValidationException::withMessages(['scheduled_at' => [$msg.'.']]);
            }

            if (! $resolved['opens_at'] || ! $resolved['closes_at']) {
                throw ValidationException::withMessages(['scheduled_at' => ['El restaurante no opera en el día y hora seleccionados.']]);
            }

            $time = $scheduledAt->format('H:i:s');
            $opens = $resolved['opens_at'].':00';
            $closes = $resolved['closes_at'].':00';

            if ($opens > $closes) {
                $withinSchedule = $time >= $opens || $time <= $closes;
            } else {
                $withinSchedule = $time >= $opens && $time <= $closes;
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
        $customer = Customer::updateOrCreate(
            ['token' => $validated['customer']['token']],
            ['name' => $validated['customer']['name'], 'phone' => $validated['customer']['phone']],
        );

        // PASO 3 — Determine branch.
        // Delivery: backend calculates optimal branch (ignore client-supplied branch_id).
        // Pickup/dine_in: client selects branch, validate it belongs to restaurant.
        $deliveryResult = null;

        if ($validated['delivery_type'] === 'delivery') {
            $deliveryResult = app(DeliveryService::class)->calculate(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                $restaurant,
            );
            $branch = $deliveryResult->branch;

            if (! $deliveryResult->isInCoverage) {
                throw ValidationException::withMessages(['delivery_cost' => ['No hay cobertura de entrega para esta ubicación.']]);
            }
        } else {
            $branch = Branch::query()
                ->where('id', $validated['branch_id'])
                ->where('restaurant_id', $restaurant->id)
                ->first();

            if (! $branch) {
                throw ValidationException::withMessages(['branch_id' => ['La sucursal no pertenece a este restaurante.']]);
            }
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
                ->with(['modifierGroups.options', 'modifierGroupTemplates.options'])
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

        // PASO 5 — Validate modifier options, required groups, single-selection, max_selections (both sources).
        // $allValidOptions keyed by "inline_{id}" or "catalog_{id}" to avoid ID collisions.
        $allValidOptions = collect();

        foreach ($normalizedItems as $normalized) {
            $itemData = $normalized['data'];
            $entity = $normalized['entity'];
            $modifiers = collect($itemData['modifiers'] ?? []);

            // Split modifiers by source.
            $inlineModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_id']));
            $catalogModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_template_id']));

            // 5a — Validate inline modifier options belong to this entity's modifier groups.
            $inlineOptionIds = $inlineModifiers->pluck('modifier_option_id')->unique()->values();
            if ($inlineOptionIds->isNotEmpty()) {
                $validInline = ModifierOption::query()
                    ->whereIn('id', $inlineOptionIds)
                    ->where('is_active', true)
                    ->whereHas('modifierGroup', fn ($q) => $q
                        ->where('restaurant_id', $restaurant->id)
                        ->where('is_active', true)
                        ->where($normalized['owner_column'], $normalized['owner_id']))
                    ->get()
                    ->keyBy('id');

                if ($validInline->count() !== $inlineOptionIds->count()) {
                    throw ValidationException::withMessages(['items' => ['Uno o más modificadores no son válidos para "'.$entity->name.'".']]);
                }

                foreach ($validInline as $id => $opt) {
                    $allValidOptions->put('inline_'.$id, $opt);
                }
            }

            // 5a-catalog — Validate catalog modifier options belong to templates linked to the entity.
            $catalogOptionIds = $catalogModifiers->pluck('modifier_option_template_id')->unique()->values();
            if ($catalogOptionIds->isNotEmpty()) {
                $linkedTemplateIds = $entity->modifierGroupTemplates()
                    ->where('is_active', true)
                    ->pluck('modifier_group_templates.id');

                $validCatalog = ModifierOptionTemplate::query()
                    ->whereIn('id', $catalogOptionIds)
                    ->where('is_active', true)
                    ->whereIn('modifier_group_template_id', $linkedTemplateIds)
                    ->get()
                    ->keyBy('id');

                if ($validCatalog->count() !== $catalogOptionIds->count()) {
                    throw ValidationException::withMessages(['items' => ['Uno o más modificadores de catálogo no son válidos para "'.$entity->name.'".']]);
                }

                foreach ($validCatalog as $id => $opt) {
                    $allValidOptions->put('catalog_'.$id, $opt);
                }
            }

            // Build combined sent option arrays per-group for validation.
            // 5b — Required groups (inline).
            $sentInlineIds = $inlineModifiers->pluck('modifier_option_id')->toArray();

            $requiredInlineGroups = ModifierGroup::query()
                ->where($normalized['owner_column'], $normalized['owner_id'])
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('is_required', true)
                ->get();

            foreach ($requiredInlineGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (empty(array_intersect($sentInlineIds, $groupOptionIds))) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo de modificadores "'.$group->name.'" es obligatorio para "'.$entity->name.'".'],
                    ]);
                }
            }

            // 5b-catalog — Required catalog groups linked to this entity (product or promotion).
            $requiredCatalogGroups = $entity->modifierGroupTemplates()
                ->where('is_active', true)
                ->where('is_required', true)
                ->get();

            $sentCatalogIds = $catalogModifiers->pluck('modifier_option_template_id')->toArray();

            foreach ($requiredCatalogGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (empty(array_intersect($sentCatalogIds, $groupOptionIds))) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo de modificadores "'.$group->name.'" es obligatorio para "'.$entity->name.'".'],
                    ]);
                }
            }

            // 5c — Single-selection validation (inline).
            $singleInlineGroups = ModifierGroup::query()
                ->where($normalized['owner_column'], $normalized['owner_id'])
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('selection_type', 'single')
                ->get();

            foreach ($singleInlineGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (count(array_intersect($sentInlineIds, $groupOptionIds)) > 1) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" solo permite una opción para "'.$entity->name.'".'],
                    ]);
                }
            }

            // 5c-catalog — Single-selection and max_selections validation (catalog).

            $catalogGroups = $entity->modifierGroupTemplates()->where('is_active', true)->get();

            foreach ($catalogGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                $selectedCount = count(array_intersect($sentCatalogIds, $groupOptionIds));

                if ($group->selection_type === 'single' && $selectedCount > 1) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" solo permite una opción para "'.$entity->name.'".'],
                    ]);
                }

                if ($group->max_selections && $selectedCount > $group->max_selections) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" permite máximo '.$group->max_selections.' opciones para "'.$entity->name.'".'],
                    ]);
                }
            }

            // 5d — max_selections validation for inline groups.
            foreach ($singleInlineGroups->merge(ModifierGroup::query()
                ->where($normalized['owner_column'], $normalized['owner_id'])
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('selection_type', 'multiple')
                ->whereNotNull('max_selections')
                ->get()) as $group) {
                if ($group->selection_type === 'multiple' && $group->max_selections) {
                    $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                    $selectedCount = count(array_intersect($sentInlineIds, $groupOptionIds));
                    if ($selectedCount > $group->max_selections) {
                        throw ValidationException::withMessages([
                            'items' => ['El grupo "'.$group->name.'" permite máximo '.$group->max_selections.' opciones para "'.$entity->name.'".'],
                        ]);
                    }
                }
            }
        }

        // PASO 6 — Anti-tampering: validate prices match the database (both sources).
        foreach ($normalizedItems as $normalized) {
            $entity = $normalized['entity'];
            $itemData = $normalized['data'];
            $unitPrice = (float) $itemData['unit_price'];

            if (abs($unitPrice - (float) $entity->price) > 0.01) {
                throw ValidationException::withMessages(['items' => ['El precio de "'.$entity->name.'" no coincide.']]);
            }

            foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                if (! empty($modifierData['modifier_option_id'])) {
                    $option = $allValidOptions['inline_'.$modifierData['modifier_option_id']];
                } else {
                    $option = $allValidOptions['catalog_'.$modifierData['modifier_option_template_id']];
                }

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
            $modifierTotal = collect($itemData['modifiers'] ?? [])->sum(function (array $m) use ($allValidOptions) {
                $key = ! empty($m['modifier_option_id']) ? 'inline_'.$m['modifier_option_id'] : 'catalog_'.$m['modifier_option_template_id'];

                return (float) $allValidOptions[$key]->price_adjustment;
            });
            $subtotal += ((float) $entity->price + $modifierTotal) * (int) $itemData['quantity'];
        }

        // PASO 7b — Delivery cost from DeliveryService result (already calculated in PASO 3).
        $deliveryCost = 0.0;
        $distanceKm = null;
        if ($validated['delivery_type'] === 'delivery' && $deliveryResult) {
            $distanceKm = $deliveryResult->distanceKm;
            $deliveryCost = $deliveryResult->deliveryCost;
        }

        // PASO 7c — Coupon validation and discount calculation (server-side, anti-tampering).
        $coupon = null;
        $discountAmount = 0.0;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::query()
                ->withoutGlobalScopes()
                ->where('restaurant_id', $restaurant->id)
                ->whereRaw('UPPER(code) = ?', [strtoupper($validated['coupon_code'])])
                ->first();

            if (! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => ['Cupón no encontrado.']]);
            }

            $couponCheck = $coupon->isValidForOrder($subtotal, $validated['customer']['phone']);
            if (! $couponCheck['valid']) {
                throw ValidationException::withMessages(['coupon_code' => [$couponCheck['reason']]]);
            }

            $discountAmount = $coupon->calculateDiscount($subtotal);
        }

        $total = $subtotal - $discountAmount + $deliveryCost;

        // Validate cash_amount covers the total.
        if (($validated['payment_method'] === 'cash') && ! empty($validated['cash_amount']) && ((float) $validated['cash_amount'] < $total)) {
            throw ValidationException::withMessages([
                'cash_amount' => ['El monto pagado debe ser mayor o igual al total del pedido.'],
            ]);
        }

        // PASO 8 — Create Order inside transaction with limit re-check (prevents TOCTOU race condition).
        $order = DB::transaction(function () use ($validated, $restaurant, $branch, $customer, $normalizedItems, $allValidOptions, $subtotal, $deliveryCost, $discountAmount, $distanceKm, $total, $coupon): Order {
            // Re-check order limit with a FOR UPDATE lock on the restaurant row.
            $lockedRestaurant = Restaurant::query()->lockForUpdate()->find($restaurant->id);
            if ($this->limitService->isOrderLimitReached($lockedRestaurant)) {
                throw new \DomainException('monthly_limit_reached');
            }

            // Re-validate branch is still active (TOCTOU guard).
            $freshBranch = Branch::query()->where('id', $branch->id)->where('is_active', true)->first();
            if (! $freshBranch) {
                throw ValidationException::withMessages(['branch_id' => ['La sucursal ya no está disponible.']]);
            }

            // Re-validate payment method is still active (TOCTOU guard).
            $paymentStillActive = PaymentMethod::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('type', $validated['payment_method'])
                ->where('is_active', true)
                ->exists();
            if (! $paymentStillActive) {
                throw ValidationException::withMessages(['payment_method' => ['El método de pago ya no está disponible.']]);
            }

            // Re-validate all products/promotions are still active (TOCTOU guard).
            foreach ($normalizedItems as $normalized) {
                $entity = $normalized['entity'];
                $ownerColumn = $normalized['owner_column'];
                $ownerId = $normalized['owner_id'];

                if ($ownerColumn === 'product_id') {
                    $stillActive = Product::query()->where('id', $ownerId)->where('is_active', true)->exists();
                } else {
                    $stillActive = Promotion::query()->where('id', $ownerId)->where('is_active', true)->exists();
                }

                if (! $stillActive) {
                    throw ValidationException::withMessages(['items' => ["\"{$entity->name}\" ya no está disponible."]]);
                }
            }

            $order = Order::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'delivery_type' => $validated['delivery_type'],
                'status' => 'received',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'cash_amount' => $validated['cash_amount'] ?? null,
                'requires_invoice' => $validated['requires_invoice'] ?? false,
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
                    if (! empty($modifierData['modifier_option_id'])) {
                        $option = $allValidOptions['inline_'.$modifierData['modifier_option_id']];
                        $modifierOptionId = $option->id;
                    } else {
                        $option = $allValidOptions['catalog_'.$modifierData['modifier_option_template_id']];
                        $modifierOptionId = null; // Catalog options live in a different table.
                    }
                    $item->modifiers()->create([
                        'modifier_option_id' => $modifierOptionId,
                        'modifier_option_name' => $option->name,
                        'price_adjustment' => $option->price_adjustment,
                        'production_cost' => $option->production_cost,
                    ]);
                }
            }

            // Audit trail: order created (no user_id — created by client via API).
            OrderEvent::create([
                'order_id' => $order->id,
                'user_id' => null,
                'action' => 'created',
                'from_status' => null,
                'to_status' => 'received',
            ]);

            // Record coupon usage with locked re-validation (prevents concurrent double-use).
            if ($coupon) {
                $lockedCoupon = Coupon::query()->lockForUpdate()->find($coupon->id);

                if ($lockedCoupon) {
                    $recheck = $lockedCoupon->isValidForOrder($subtotal, $validated['customer']['phone']);
                    if (! $recheck['valid']) {
                        throw ValidationException::withMessages(['coupon_code' => [$recheck['reason']]]);
                    }

                    CouponUse::create([
                        'coupon_id' => $lockedCoupon->id,
                        'order_id' => $order->id,
                        'customer_phone' => $validated['customer']['phone'],
                        'created_at' => now(),
                    ]);
                }
            }

            return $order;
        });

        // PASO 9 — Load relations needed for WhatsApp message.
        $order->load(['items.modifiers', 'branch', 'customer', 'restaurant']);

        $whatsappMessage = $this->buildWhatsAppMessage($order);

        return new OrderCreatedResult(order: $order, whatsappMessage: $whatsappMessage);
    }

    private function buildWhatsAppMessage(Order $order): string
    {
        $fmt = fn ($v) => '$'.number_format((float) $v, 2);
        $orderNumber = '#'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT);
        $restaurantName = $order->restaurant->name ?? '';

        $lines = [
            "Pedido {$orderNumber} — {$restaurantName}",
            '',
            "👤 Cliente: {$order->customer->name} | {$order->customer->phone}",
            '',
            '🛒 Pedido:',
        ];

        foreach ($order->items as $item) {
            $modifierTotal = $item->modifiers->sum(fn ($m) => (float) $m->price_adjustment);
            $itemTotal = ((float) $item->unit_price + $modifierTotal) * $item->quantity;

            $lines[] = "• {$item->quantity}x {$item->product_name} - {$fmt($itemTotal)}";

            foreach ($item->modifiers as $mod) {
                $adj = (float) $mod->price_adjustment > 0 ? " (+{$fmt($mod->price_adjustment)})" : '';
                $lines[] = "  ↳ {$mod->modifier_option_name}{$adj}";
            }

            if ($item->notes) {
                $lines[] = "  📝 {$item->notes}";
            }
        }

        $lines[] = '';

        // Delivery type section
        if ($order->delivery_type === 'delivery') {
            $lines[] = '🚗 Tipo: A domicilio';
            if ($order->address_street) {
                $address = "{$order->address_street} {$order->address_number}";
                if ($order->address_references) {
                    $address .= " — {$order->address_references}";
                }
                $lines[] = "📍 Dirección: {$address}";
            }
            if ($order->branch) {
                $lines[] = "🏪 Sucursal: {$order->branch->name}";
            }
            if ($order->distance_km) {
                $lines[] = "📏 Distancia: {$order->distance_km} km";
            }
            if ($order->latitude && $order->longitude) {
                $lines[] = "📌 Ubicación: https://maps.google.com/?q={$order->latitude},{$order->longitude}";
            }
        } elseif ($order->delivery_type === 'pickup') {
            $lines[] = '🏪 Tipo: Recoger en sucursal';
            if ($order->branch) {
                $lines[] = "🏪 Sucursal: {$order->branch->name}";
            }
        } elseif ($order->delivery_type === 'dine_in') {
            $lines[] = '🍽 Tipo: Comer en restaurante';
        }

        $lines[] = '';

        if ($order->scheduled_at) {
            $lines[] = '🕐 Programado para: '.$order->scheduled_at->format('d/m/Y, h:i a');
        }

        $paymentLabel = match ($order->payment_method) {
            'cash' => 'Efectivo',
            'terminal' => 'Terminal bancaria',
            'transfer' => 'Transferencia',
            default => $order->payment_method,
        };

        $lines[] = "💳 Pago: {$paymentLabel}";
        if ($order->payment_method === 'cash' && $order->cash_amount) {
            $lines[] = "💵 Paga con: {$fmt($order->cash_amount)}";
        }

        $lines[] = '';
        $lines[] = "Subtotal: {$fmt($order->subtotal)}";

        if ($order->delivery_type === 'delivery') {
            $lines[] = "Envío: {$fmt($order->delivery_cost)}";
        }

        if ((float) $order->discount_amount > 0) {
            $lines[] = "🏷 Cupón ({$order->coupon_code}): -{$fmt($order->discount_amount)}";
        }

        $lines[] = "Total: {$fmt($order->total)}";

        if ($order->requires_invoice) {
            $lines[] = '📋 Requiere factura';
        }

        return implode("\n", $lines);
    }
}
