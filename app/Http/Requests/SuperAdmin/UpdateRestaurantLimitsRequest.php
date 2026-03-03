<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Order;
use App\Models\Scopes\TenantScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateRestaurantLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'orders_limit' => ['required', 'integer', 'min:1'],
            'orders_limit_start' => ['required', 'date'],
            'orders_limit_end' => ['required', 'date', 'after_or_equal:orders_limit_start'],
            'max_branches' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $restaurant = $this->route('restaurant');

                if (! $restaurant) {
                    return;
                }

                $start = Carbon::parse($this->input('orders_limit_start'));
                $end = Carbon::parse($this->input('orders_limit_end'));

                $currentOrders = Order::query()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('restaurant_id', $restaurant->id)
                    ->whereBetween('created_at', [
                        $start->startOfDay(),
                        $end->endOfDay(),
                    ])
                    ->count();

                $newLimit = (int) $this->input('orders_limit');

                if ($newLimit < $currentOrders) {
                    $validator->errors()->add(
                        'orders_limit',
                        "El límite no puede ser menor al número de pedidos del periodo ({$currentOrders}).",
                    );
                }
            },
        ];
    }
}
