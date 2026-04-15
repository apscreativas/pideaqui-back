<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\ModifierOptionTemplate;
use App\Models\PaymentMethod;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    public function __construct(
        private readonly PosTicketNumberService $ticketNumbers,
        private readonly LimitService $limitService,
    ) {}

    /**
     * Create a POS sale in `preparing` status.
     *
     * @param  array<string, mixed>  $validated
     */
    public function store(array $validated, Restaurant $restaurant, User $cashier): PosSale
    {
        // Operational gate: block creation when billing period/status is not
        // current. Existing sales (pay, cancel, close) are NOT gated — the
        // cashier must be able to close work in progress regardless.
        if (! $restaurant->canOperate($this->limitService)) {
            throw new \DomainException('restaurant_not_operational:'.$restaurant->operationalBlockReason($this->limitService));
        }

        // Validate branch belongs to restaurant and is active.
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

        // Operator branch authorization (admins bypass).
        $allowed = $cashier->allowedBranchIds();
        if ($allowed !== null && ! in_array($branch->id, $allowed, true)) {
            throw ValidationException::withMessages(['branch_id' => ['No tienes permiso para vender en esta sucursal.']]);
        }

        // Load and validate products (active, this restaurant, category active).
        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();
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

        // Validate modifiers (inline + catalog), required groups, single/max selection.
        $allValidOptions = $this->validateModifiers($validated['items'], $products, $restaurant);

        // Anti-tampering: prices must match DB ±0.01.
        foreach ($validated['items'] as $itemData) {
            $product = $products[$itemData['product_id']];
            if (abs((float) $itemData['unit_price'] - (float) $product->price) > 0.01) {
                throw ValidationException::withMessages(['items' => ['El precio de "'.$product->name.'" no coincide.']]);
            }

            foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                $key = ! empty($modifierData['modifier_option_id'])
                    ? 'inline_'.$modifierData['modifier_option_id']
                    : 'catalog_'.$modifierData['modifier_option_template_id'];
                $option = $allValidOptions[$key];
                if (abs((float) $modifierData['price_adjustment'] - (float) $option->price_adjustment) > 0.01) {
                    throw ValidationException::withMessages(['items' => ['El precio del modificador "'.$option->name.'" no coincide.']]);
                }
            }
        }

        // Calculate subtotal in backend.
        $subtotal = 0.0;
        foreach ($validated['items'] as $itemData) {
            $product = $products[$itemData['product_id']];
            $modifierTotal = collect($itemData['modifiers'] ?? [])->sum(function (array $m) use ($allValidOptions): float {
                $key = ! empty($m['modifier_option_id'])
                    ? 'inline_'.$m['modifier_option_id']
                    : 'catalog_'.$m['modifier_option_template_id'];

                return (float) $allValidOptions[$key]->price_adjustment;
            });
            $subtotal += ((float) $product->price + $modifierTotal) * (int) $itemData['quantity'];
        }

        return DB::transaction(function () use ($validated, $restaurant, $branch, $cashier, $products, $allValidOptions, $subtotal): PosSale {
            $ticketNumber = $this->ticketNumbers->next($restaurant->id);

            $sale = PosSale::create([
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
                'cashier_user_id' => $cashier->id,
                'ticket_number' => $ticketNumber,
                'status' => 'preparing',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $product = $products[$itemData['product_id']];

                $item = $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $product->price,
                    'production_cost' => $product->production_cost,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                foreach ($itemData['modifiers'] ?? [] as $modifierData) {
                    if (! empty($modifierData['modifier_option_id'])) {
                        $option = $allValidOptions['inline_'.$modifierData['modifier_option_id']];
                        $item->modifiers()->create([
                            'modifier_option_id' => $option->id,
                            'modifier_option_template_id' => null,
                            'modifier_option_name' => $option->name,
                            'price_adjustment' => $option->price_adjustment,
                            'production_cost' => $option->production_cost,
                        ]);
                    } else {
                        $option = $allValidOptions['catalog_'.$modifierData['modifier_option_template_id']];
                        $item->modifiers()->create([
                            'modifier_option_id' => null,
                            'modifier_option_template_id' => $option->id,
                            'modifier_option_name' => $option->name,
                            'price_adjustment' => $option->price_adjustment,
                            'production_cost' => $option->production_cost,
                        ]);
                    }
                }
            }

            return $sale->fresh(['items.modifiers', 'branch', 'cashier']);
        });
    }

    public function markReady(PosSale $sale): PosSale
    {
        return DB::transaction(function () use ($sale): PosSale {
            $locked = PosSale::query()->lockForUpdate()->find($sale->id);

            if ($locked->status !== 'preparing') {
                throw ValidationException::withMessages(['status' => ['Solo se puede marcar como lista una venta en preparación.']]);
            }

            $locked->update(['status' => 'ready', 'prepared_at' => now()]);

            return $locked->fresh(['items.modifiers', 'branch', 'cashier', 'payments']);
        });
    }

    public function cancel(PosSale $sale, string $reason, ?User $user = null): PosSale
    {
        return DB::transaction(function () use ($sale, $reason, $user): PosSale {
            $locked = PosSale::query()->lockForUpdate()->find($sale->id);

            if (! $locked->isCancellable()) {
                throw ValidationException::withMessages(['status' => ['Esta venta ya no puede ser cancelada.']]);
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by' => $user?->id,
            ]);

            return $locked->fresh(['items.modifiers', 'branch', 'cashier', 'payments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function registerPayment(PosSale $sale, array $data, User $user): PosPayment
    {
        return DB::transaction(function () use ($sale, $data, $user): PosPayment {
            $locked = PosSale::query()->lockForUpdate()->find($sale->id);

            if (! in_array($locked->status, ['preparing', 'ready'], true)) {
                throw ValidationException::withMessages(['status' => ['No se pueden registrar pagos en este estado.']]);
            }

            // Validate payment method type is active for this restaurant.
            $methodActive = PaymentMethod::query()
                ->where('restaurant_id', $locked->restaurant_id)
                ->where('type', $data['payment_method_type'])
                ->where('is_active', true)
                ->exists();

            if (! $methodActive) {
                throw ValidationException::withMessages(['payment_method_type' => ['Este método de pago no está disponible.']]);
            }

            $amount = round((float) $data['amount'], 2);
            $pending = $locked->pendingAmount();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => ['El monto debe ser mayor a cero.']]);
            }

            if ($amount > $pending + 0.01) {
                throw ValidationException::withMessages(['amount' => ['El monto excede el saldo pendiente ($'.number_format($pending, 2).').']]);
            }

            $cashReceived = null;
            $changeGiven = null;
            if ($data['payment_method_type'] === 'cash' && isset($data['cash_received'])) {
                $cashReceived = round((float) $data['cash_received'], 2);
                if ($cashReceived < $amount - 0.01) {
                    throw ValidationException::withMessages(['cash_received' => ['El efectivo recibido es menor al monto.']]);
                }
                $changeGiven = round($cashReceived - $amount, 2);
            }

            $payment = PosPayment::create([
                'pos_sale_id' => $locked->id,
                'payment_method_type' => $data['payment_method_type'],
                'amount' => $amount,
                'cash_received' => $cashReceived,
                'change_given' => $changeGiven,
                'registered_by_user_id' => $user->id,
                'created_at' => now(),
            ]);

            // Auto-close sale when fully paid — no manual "Cerrar venta" needed.
            $totalPaid = (float) $locked->payments()->sum('amount');
            if ($totalPaid + 0.01 >= (float) $locked->total && $locked->status !== 'paid') {
                $locked->update(['status' => 'paid', 'paid_at' => now()]);
            }

            return $payment;
        });
    }

    public function removePayment(PosPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $sale = PosSale::query()->lockForUpdate()->find($payment->pos_sale_id);

            if ($sale->status === 'paid') {
                throw ValidationException::withMessages(['status' => ['No se pueden eliminar pagos de una venta cobrada.']]);
            }

            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages(['status' => ['No se pueden modificar pagos de una venta cancelada.']]);
            }

            $payment->delete();
        });
    }

    public function closePay(PosSale $sale): PosSale
    {
        return DB::transaction(function () use ($sale): PosSale {
            $locked = PosSale::query()->lockForUpdate()->find($sale->id);

            // Allow paying any open sale (preparing or legacy ready).
            if (! in_array($locked->status, ['preparing', 'ready'], true)) {
                throw ValidationException::withMessages(['status' => ['No se puede cobrar esta venta.']]);
            }

            $paid = $locked->paidAmount();
            if ($paid + 0.01 < (float) $locked->total) {
                throw ValidationException::withMessages(['payments' => ['El cobro está incompleto. Pendiente: $'.number_format((float) $locked->total - $paid, 2).'.']]);
            }

            $locked->update(['status' => 'paid', 'paid_at' => now()]);

            return $locked->fresh(['items.modifiers', 'branch', 'cashier', 'payments']);
        });
    }

    /**
     * Validate modifiers (inline + catalog), required groups, single-selection, max_selections.
     * Returns map of validated options keyed by inline_{id} or catalog_{id}.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @return \Illuminate\Support\Collection<string, ModifierOption|ModifierOptionTemplate>
     */
    private function validateModifiers(array $items, $products, Restaurant $restaurant)
    {
        $allValidOptions = collect();

        foreach ($items as $itemData) {
            $product = $products[$itemData['product_id']];
            $modifiers = collect($itemData['modifiers'] ?? []);

            $inlineModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_id']));
            $catalogModifiers = $modifiers->filter(fn ($m) => ! empty($m['modifier_option_template_id']));

            // Validate inline options belong to this product's modifier groups.
            $inlineIds = $inlineModifiers->pluck('modifier_option_id')->unique()->values();
            if ($inlineIds->isNotEmpty()) {
                $valid = ModifierOption::query()
                    ->whereIn('id', $inlineIds)
                    ->where('is_active', true)
                    ->whereHas('modifierGroup', fn ($q) => $q
                        ->where('restaurant_id', $restaurant->id)
                        ->where('is_active', true)
                        ->where('product_id', $product->id))
                    ->get()
                    ->keyBy('id');

                if ($valid->count() !== $inlineIds->count()) {
                    throw ValidationException::withMessages(['items' => ['Uno o más modificadores no son válidos para "'.$product->name.'".']]);
                }

                foreach ($valid as $id => $opt) {
                    $allValidOptions->put('inline_'.$id, $opt);
                }
            }

            // Validate catalog template options linked to this product.
            $catalogIds = $catalogModifiers->pluck('modifier_option_template_id')->unique()->values();
            if ($catalogIds->isNotEmpty()) {
                $linkedTemplateIds = $product->modifierGroupTemplates()
                    ->where('is_active', true)
                    ->pluck('modifier_group_templates.id');

                $valid = ModifierOptionTemplate::query()
                    ->whereIn('id', $catalogIds)
                    ->where('is_active', true)
                    ->whereIn('modifier_group_template_id', $linkedTemplateIds)
                    ->get()
                    ->keyBy('id');

                if ($valid->count() !== $catalogIds->count()) {
                    throw ValidationException::withMessages(['items' => ['Uno o más modificadores de catálogo no son válidos para "'.$product->name.'".']]);
                }

                foreach ($valid as $id => $opt) {
                    $allValidOptions->put('catalog_'.$id, $opt);
                }
            }

            $sentInlineIds = $inlineModifiers->pluck('modifier_option_id')->toArray();
            $sentCatalogIds = $catalogModifiers->pluck('modifier_option_template_id')->toArray();

            // Required inline groups must have at least one selection.
            $requiredInlineGroups = ModifierGroup::query()
                ->where('product_id', $product->id)
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('is_required', true)
                ->get();

            foreach ($requiredInlineGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (empty(array_intersect($sentInlineIds, $groupOptionIds))) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo de modificadores "'.$group->name.'" es obligatorio para "'.$product->name.'".'],
                    ]);
                }
            }

            // Required catalog groups linked to product.
            $requiredCatalogGroups = $product->modifierGroupTemplates()
                ->where('is_active', true)
                ->where('is_required', true)
                ->get();

            foreach ($requiredCatalogGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (empty(array_intersect($sentCatalogIds, $groupOptionIds))) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo de modificadores "'.$group->name.'" es obligatorio para "'.$product->name.'".'],
                    ]);
                }
            }

            // Single-selection inline.
            $singleInline = ModifierGroup::query()
                ->where('product_id', $product->id)
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('selection_type', 'single')
                ->get();

            foreach ($singleInline as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                if (count(array_intersect($sentInlineIds, $groupOptionIds)) > 1) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" solo permite una opción para "'.$product->name.'".'],
                    ]);
                }
            }

            // Single + max_selections catalog.
            $catalogGroups = $product->modifierGroupTemplates()->where('is_active', true)->get();
            foreach ($catalogGroups as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                $count = count(array_intersect($sentCatalogIds, $groupOptionIds));
                if ($group->selection_type === 'single' && $count > 1) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" solo permite una opción para "'.$product->name.'".'],
                    ]);
                }
                if ($group->max_selections && $count > $group->max_selections) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" permite máximo '.$group->max_selections.' opciones para "'.$product->name.'".'],
                    ]);
                }
            }

            // max_selections inline (multiple groups).
            $multipleInline = ModifierGroup::query()
                ->where('product_id', $product->id)
                ->where('restaurant_id', $restaurant->id)
                ->where('is_active', true)
                ->where('selection_type', 'multiple')
                ->whereNotNull('max_selections')
                ->get();

            foreach ($multipleInline as $group) {
                $groupOptionIds = $group->options()->where('is_active', true)->pluck('id')->toArray();
                $count = count(array_intersect($sentInlineIds, $groupOptionIds));
                if ($count > $group->max_selections) {
                    throw ValidationException::withMessages([
                        'items' => ['El grupo "'.$group->name.'" permite máximo '.$group->max_selections.' opciones para "'.$product->name.'".'],
                    ]);
                }
            }
        }

        return $allValidOptions;
    }
}
