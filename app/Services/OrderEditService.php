<?php

namespace App\Services;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTemplate;
use App\Models\Order;
use App\Models\OrderAudit;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderEditService
{
    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException|HttpException
     */
    public function update(Order $order, array $validated, User $user, ?string $ipAddress = null): Order
    {
        // 1 — Status check
        if (! $order->isEditable()) {
            throw ValidationException::withMessages(['order' => ['Este pedido ya no puede ser editado.']]);
        }

        // 2 — Optimistic lock
        $expectedUpdatedAt = Carbon::parse($validated['expected_updated_at']);
        if ($order->updated_at->ne($expectedUpdatedAt)) {
            throw new HttpException(409, 'Este pedido fue modificado por otro usuario. Recarga para ver los cambios.');
        }

        $restaurant = $order->restaurant;
        $oldTotal = (float) $order->total;
        $reason = $validated['reason'] ?? null;

        return DB::transaction(function () use ($order, $validated, $user, $restaurant, $oldTotal, $reason, $ipAddress): Order {
            // 3 — Lock order row and re-check status
            $order = Order::query()->lockForUpdate()->find($order->id);

            if (! $order->isEditable()) {
                throw ValidationException::withMessages(['order' => ['Este pedido ya no puede ser editado.']]);
            }

            $auditEntries = [];

            // 4 — Process item changes
            if (isset($validated['items'])) {
                $auditEntries[] = $this->processItems($order, $validated['items'], $restaurant);
            }

            // 5 — Process address changes
            $addressAudit = $this->processAddress($order, $validated);
            if ($addressAudit) {
                $auditEntries[] = $addressAudit;
            }

            // 6 — Process payment method change
            $paymentAudit = $this->processPaymentMethod($order, $validated, $restaurant);
            if ($paymentAudit) {
                $auditEntries[] = $paymentAudit;
            }

            // 7 — Update order metadata
            $order->edited_at = now();
            $order->edit_count = $order->edit_count + 1;
            $order->save();

            // 8 — Create audit entries
            $newTotal = (float) $order->total;
            foreach ($auditEntries as $entry) {
                OrderAudit::create([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'action' => $entry['action'],
                    'changes' => $entry['changes'],
                    'reason' => $reason,
                    'old_total' => $oldTotal,
                    'new_total' => $newTotal,
                    'ip_address' => $ipAddress,
                ]);
            }

            return $order;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{action: string, changes: array<string, mixed>}
     */
    private function processItems(Order $order, array $items, $restaurant): array
    {
        $order->load('items.modifiers');

        // Separate product and promotion items
        $productItems = collect($items)->filter(fn ($i) => ! empty($i['product_id']));
        $promotionItems = collect($items)->filter(fn ($i) => ! empty($i['promotion_id']));

        // Load and validate products
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

            foreach ($products as $product) {
                if (! $product->category || ! $product->category->is_active) {
                    throw ValidationException::withMessages(['items' => ['La categoría de "'.$product->name.'" no está disponible.']]);
                }
            }
        }

        // Load and validate promotions
        $promotions = collect();
        if ($promotionItems->isNotEmpty()) {
            $promoIds = $promotionItems->pluck('promotion_id')->unique()->values();
            $promotions = Promotion::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->whereIn('id', $promoIds)
                ->get()
                ->keyBy('id');

            if ($promotions->count() !== $promoIds->count()) {
                throw ValidationException::withMessages(['items' => ['Una o más promociones no están disponibles.']]);
            }
        }

        // Normalize items
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

        // Validate modifiers (same logic as OrderService)
        $allValidOptions = collect();
        foreach ($normalizedItems as $normalized) {
            $this->validateModifiers($normalized, $restaurant, $allValidOptions);
        }

        // Build audit diff
        $diff = $this->buildItemsDiff($order, $normalizedItems);

        // Calculate new subtotal server-side
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

        // Re-validate coupon discount if order had one
        $discountAmount = 0.0;
        $couponRemoved = false;
        $removedCouponCode = null;
        $removedDiscountAmount = 0.0;
        if ($order->coupon_id) {
            $coupon = \App\Models\Coupon::find($order->coupon_id);
            if ($coupon && $coupon->min_purchase !== null && $subtotal < (float) $coupon->min_purchase) {
                // Subtotal no longer meets min_purchase — remove discount and track for audit
                $couponRemoved = true;
                $removedCouponCode = $order->coupon_code;
                $removedDiscountAmount = (float) $order->discount_amount;
                $discountAmount = 0.0;

                // Release coupon use so the customer can reuse the coupon
                \App\Models\CouponUse::where('order_id', $order->id)->delete();

                $order->discount_amount = 0;
                $order->coupon_id = null;
                $order->coupon_code = null;
            } elseif ($coupon) {
                // Recalculate on the NEW subtotal so percentage coupons scale
                // with edits (fixed coupons return the same amount).
                $discountAmount = $coupon->calculateDiscount($subtotal);
                $order->discount_amount = $discountAmount;
            } else {
                // Coupon row was deleted from DB — keep the original snapshot.
                $discountAmount = (float) $order->discount_amount;
            }
        }

        $total = $subtotal - $discountAmount + (float) $order->delivery_cost;

        if ($total <= 0) {
            throw ValidationException::withMessages(['items' => ['El total del pedido debe ser mayor a cero.']]);
        }

        // Validate cash_amount covers the total
        $paymentMethod = $order->payment_method;
        $cashAmount = $order->cash_amount;
        if ($paymentMethod === 'cash' && $cashAmount && ((float) $cashAmount < $total)) {
            throw ValidationException::withMessages([
                'cash_amount' => ['El monto en efectivo no cubre el nuevo total del pedido.'],
            ]);
        }

        // Delete old items and modifiers
        $order->items()->each(function ($item) {
            $item->modifiers()->delete();
        });
        $order->items()->delete();

        // Create new items with fresh snapshots
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
                    $modifierOptionId = null;
                }

                $item->modifiers()->create([
                    'modifier_option_id' => $modifierOptionId,
                    'modifier_option_name' => $option->name,
                    'price_adjustment' => $option->price_adjustment,
                    'production_cost' => $option->production_cost,
                ]);
            }
        }

        // Update order totals
        $order->subtotal = $subtotal;
        $order->total = $total;

        // Include coupon removal in audit if applicable
        if ($couponRemoved) {
            $diff['coupon_removed'] = [
                'code' => $removedCouponCode,
                'old_discount' => $removedDiscountAmount,
                'reason' => 'subtotal_below_minimum',
                'new_subtotal' => $subtotal,
                'min_purchase' => (float) ($coupon->min_purchase ?? 0),
            ];
        }

        return ['action' => 'items_modified', 'changes' => $diff];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function validateModifiers(array $normalized, $restaurant, $allValidOptions): void
    {
        $itemData = $normalized['data'];
        $entity = $normalized['entity'];
        $modifiers = collect($itemData['modifiers'] ?? []);

        $inlineModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_id']));
        $catalogModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_template_id']));

        // Validate inline modifier options
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

        // Validate catalog modifier options
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

        // Required groups (inline)
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

        // Required catalog groups
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

        // Single-selection validation (inline)
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

        // Catalog groups: single-selection and max_selections
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

        // max_selections for inline groups
        $multiInlineGroups = ModifierGroup::query()
            ->where($normalized['owner_column'], $normalized['owner_id'])
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->where('selection_type', 'multiple')
            ->whereNotNull('max_selections')
            ->get();

        foreach ($singleInlineGroups->merge($multiInlineGroups) as $group) {
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

    /**
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>, modified: list<array<string, mixed>>}
     */
    private function buildItemsDiff(Order $order, $normalizedItems): array
    {
        $oldItems = $order->items->map(fn ($item) => [
            'product_name' => $item->product_name,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'product_id' => $item->product_id,
            'promotion_id' => $item->promotion_id,
        ])->keyBy(fn ($item) => ($item['product_id'] ? 'p'.$item['product_id'] : 'promo'.$item['promotion_id']));

        $newItems = $normalizedItems->mapWithKeys(fn ($n) => [
            ($n['product_id'] ? 'p'.$n['product_id'] : 'promo'.$n['promotion_id']) => [
                'product_name' => $n['entity']->name,
                'quantity' => (int) $n['data']['quantity'],
                'unit_price' => (float) $n['entity']->price,
            ],
        ]);

        $added = [];
        $removed = [];
        $modified = [];

        foreach ($newItems as $key => $new) {
            if (! $oldItems->has($key)) {
                $added[] = $new;
            } elseif ($oldItems[$key]['quantity'] !== $new['quantity']) {
                $modified[] = [
                    'product_name' => $new['product_name'],
                    'field' => 'quantity',
                    'old' => $oldItems[$key]['quantity'],
                    'new' => $new['quantity'],
                ];
            }
        }

        foreach ($oldItems as $key => $old) {
            if (! $newItems->has($key)) {
                $removed[] = ['product_name' => $old['product_name'], 'quantity' => $old['quantity'], 'unit_price' => $old['unit_price']];
            }
        }

        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{action: string, changes: array<string, mixed>}|null
     */
    private function processAddress(Order $order, array $validated): ?array
    {
        $addressFields = ['address_street', 'address_number', 'address_colony', 'address_references'];
        $gpsFields = ['latitude', 'longitude'];
        $allFields = array_merge($addressFields, $gpsFields);

        $hasAddressChange = false;
        $hasLocationChange = false;
        $changes = [];

        foreach ($addressFields as $field) {
            if (array_key_exists($field, $validated) && (string) $validated[$field] !== (string) $order->{$field}) {
                $changes[$field] = ['old' => $order->{$field}, 'new' => $validated[$field]];
                $order->{$field} = $validated[$field];
                $hasAddressChange = true;
            }
        }

        foreach ($gpsFields as $field) {
            if (array_key_exists($field, $validated) && (float) ($validated[$field] ?? 0) !== (float) ($order->{$field} ?? 0)) {
                $changes[$field] = ['old' => $order->{$field}, 'new' => $validated[$field]];
                $order->{$field} = $validated[$field];
                $hasLocationChange = true;
            }
        }

        if ($hasLocationChange && ! $hasAddressChange) {
            return ['action' => 'location_modified', 'changes' => $changes];
        }

        if ($hasAddressChange || $hasLocationChange) {
            return ['action' => 'address_modified', 'changes' => $changes];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{action: string, changes: array<string, mixed>}|null
     */
    private function processPaymentMethod(Order $order, array $validated, $restaurant): ?array
    {
        if (! array_key_exists('payment_method', $validated)) {
            return null;
        }

        $newMethod = $validated['payment_method'];
        if ($newMethod === $order->payment_method && ! array_key_exists('cash_amount', $validated)) {
            return null;
        }

        // Validate payment method is active
        $isActive = PaymentMethod::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('type', $newMethod)
            ->where('is_active', true)
            ->exists();

        if (! $isActive) {
            throw ValidationException::withMessages(['payment_method' => ['Este método de pago no está disponible.']]);
        }

        // Transfer needs bank details
        if ($newMethod === 'transfer') {
            $transferPm = PaymentMethod::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('type', 'transfer')
                ->first();

            if (! $transferPm || ! $transferPm->clabe) {
                throw ValidationException::withMessages(['payment_method' => ['El restaurante no tiene datos bancarios configurados para transferencia.']]);
            }
        }

        $changes = [];
        $oldMethod = $order->payment_method;

        if ($newMethod !== $oldMethod) {
            $changes['payment_method'] = ['old' => $oldMethod, 'new' => $newMethod];
        }

        // Handle cash_amount
        if ($newMethod === 'cash') {
            $newCashAmount = $validated['cash_amount'] ?? null;
            if ($newCashAmount !== null && ((float) $newCashAmount < (float) $order->total)) {
                throw ValidationException::withMessages([
                    'cash_amount' => ['El monto pagado debe ser mayor o igual al total del pedido.'],
                ]);
            }
            if ((float) ($order->cash_amount ?? 0) !== (float) ($newCashAmount ?? 0)) {
                $changes['cash_amount'] = ['old' => $order->cash_amount, 'new' => $newCashAmount];
            }
            $order->cash_amount = $newCashAmount;
        } else {
            // Switching away from cash — clear cash_amount
            if ($order->cash_amount !== null) {
                $changes['cash_amount'] = ['old' => $order->cash_amount, 'new' => null];
            }
            $order->cash_amount = null;
        }

        $order->payment_method = $newMethod;

        if (empty($changes)) {
            return null;
        }

        return ['action' => 'payment_method_changed', 'changes' => $changes];
    }
}
