<?php

namespace App\Services;

use App\DTOs\OrderCreatedResult;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly LimitService $limitService) {}

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException|\DomainException
     */
    public function store(array $validated, Restaurant $restaurant): OrderCreatedResult
    {
        // PASO 1 — Monthly limit check.
        if ($this->limitService->isMonthlyLimitReached($restaurant)) {
            throw new \DomainException('monthly_limit_reached');
        }

        // PASO 2 — Find or create customer, always update name/phone.
        $customer = Customer::firstOrCreate(
            ['token' => $validated['customer']['token']],
            ['name' => $validated['customer']['name'], 'phone' => $validated['customer']['phone']],
        );
        $customer->update(['name' => $validated['customer']['name'], 'phone' => $validated['customer']['phone']]);

        // PASO 3 — Validate branch belongs to this restaurant.
        $branch = Branch::query()
            ->where('id', $validated['branch_id'])
            ->where('restaurant_id', $restaurant->id)
            ->first();

        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => ['La sucursal no pertenece a este restaurante.']]);
        }

        // PASO 4 — Load and validate all requested products.
        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();

        $products = Product::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages(['items' => ['Uno o más productos no están disponibles.']]);
        }

        // PASO 5 — Validate modifier options belong to products of this restaurant.
        $optionIds = collect($validated['items'])
            ->flatMap(fn (array $item) => collect($item['modifiers'] ?? [])->pluck('modifier_option_id'))
            ->filter()
            ->unique()
            ->values();

        $validOptions = collect();
        if ($optionIds->isNotEmpty()) {
            $validOptions = ModifierOption::query()
                ->whereIn('id', $optionIds)
                ->whereHas('modifierGroup', fn ($q) => $q->where('restaurant_id', $restaurant->id))
                ->get()
                ->keyBy('id');

            if ($validOptions->count() !== $optionIds->count()) {
                throw ValidationException::withMessages(['items' => ['Uno o más modificadores no son válidos.']]);
            }
        }

        // PASO 6 — Anti-tampering: validate prices match the database.
        foreach ($validated['items'] as $itemData) {
            $product = $products[$itemData['product_id']];

            if (abs((float) $itemData['unit_price'] - (float) $product->price) > 0.01) {
                throw ValidationException::withMessages(['items' => ['El precio de "'.$product->name.'" no coincide.']]);
            }

            foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                $option = $validOptions[$modifierData['modifier_option_id']];

                if (abs((float) $modifierData['price_adjustment'] - (float) $option->price_adjustment) > 0.01) {
                    throw ValidationException::withMessages(['items' => ['El precio del modificador "'.$option->name.'" no coincide.']]);
                }
            }
        }

        // PASO 7 — Calculate totals in backend (never trust the client).
        $subtotal = 0.0;
        foreach ($validated['items'] as $itemData) {
            $modifierTotal = collect($itemData['modifiers'] ?? [])->sum(fn (array $m) => (float) $validOptions[$m['modifier_option_id']]->price_adjustment);
            $subtotal += ((float) $products[$itemData['product_id']]->price + $modifierTotal) * (int) $itemData['quantity'];
        }

        $deliveryCost = $validated['delivery_type'] === 'delivery' ? (float) ($validated['delivery_cost'] ?? 0) : 0.0;
        $total = $subtotal + $deliveryCost;

        // PASO 8 — Create Order, OrderItems and OrderItemModifiers in a transaction.
        $order = DB::transaction(function () use ($validated, $restaurant, $branch, $customer, $products, $validOptions, $subtotal, $deliveryCost, $total): Order {
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
                'distance_km' => $validated['distance_km'] ?? null,
                'address' => $validated['address'] ?? null,
                'address_references' => $validated['address_references'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $item = $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $products[$itemData['product_id']]->price,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                    $item->modifiers()->create([
                        'modifier_option_id' => $modifierData['modifier_option_id'],
                        'price_adjustment' => $validOptions[$modifierData['modifier_option_id']]->price_adjustment,
                    ]);
                }
            }

            return $order;
        });

        // PASO 9 — Load relations needed for WhatsApp message.
        $order->load(['items.product', 'items.modifiers.modifierOption', 'branch', 'customer']);

        $whatsappMessage = $this->buildWhatsAppMessage($order);

        return new OrderCreatedResult(order: $order, whatsappMessage: $whatsappMessage);
    }

    private function buildWhatsAppMessage(Order $order): string
    {
        $deliveryLine = match ($order->delivery_type) {
            'delivery' => '🛵 *A domicilio*',
            'pickup' => '🏃 *Recoger en local*',
            'dine_in' => '🍽️ *Comer aquí*',
            default => '',
        };

        $lines = [
            'Hola! Quiero hacer un pedido:',
            '',
            $deliveryLine,
            '',
            '🍽️ *Mi pedido:*',
        ];

        foreach ($order->items as $item) {
            $modifierNames = $item->modifiers
                ->map(fn ($m) => $m->modifierOption->name)
                ->filter()
                ->join(', ');

            $modifierTotal = $item->modifiers->sum(fn ($m) => (float) $m->price_adjustment);
            $itemTotal = ((float) $item->unit_price + $modifierTotal) * $item->quantity;

            $desc = "- {$item->quantity}x {$item->product->name}";

            if ($modifierNames) {
                $desc .= " ({$modifierNames})";
            }

            if ($item->notes) {
                $desc .= " - {$item->notes}";
            }

            $desc .= ' · $'.number_format($itemTotal, 2);
            $lines[] = $desc;
        }

        if ($order->delivery_type === 'delivery' && $order->address) {
            $lines[] = '';
            $lines[] = "📍 *Dirección:* {$order->address}";

            if ($order->address_references) {
                $lines[] = "*Referencias:* {$order->address_references}";
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

        if ($order->scheduled_at) {
            $lines[] = '⏰ *Hora programada:* '.$order->scheduled_at->format('H:i');
        }

        $lines[] = '';
        $lines[] = '¡Gracias! 🙌';

        return implode("\n", $lines);
    }
}
