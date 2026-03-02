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
            'max_monthly_orders' => ['required', 'integer', 'min:1'],
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

                $now = Carbon::now();

                $currentMonthlyOrders = Order::query()
                    ->withoutGlobalScope(TenantScope::class)
                    ->where('restaurant_id', $restaurant->id)
                    ->whereYear('created_at', $now->year)
                    ->whereMonth('created_at', $now->month)
                    ->count();

                $newLimit = (int) $this->input('max_monthly_orders');

                if ($newLimit < $currentMonthlyOrders) {
                    $validator->errors()->add(
                        'max_monthly_orders',
                        "El límite no puede ser menor al número de pedidos del mes actual ({$currentMonthlyOrders}).",
                    );
                }
            },
        ];
    }
}
