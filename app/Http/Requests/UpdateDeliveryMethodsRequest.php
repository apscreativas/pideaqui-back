<?php

namespace App\Http\Requests;

use App\Models\DeliveryRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'allows_delivery' => ['required', 'boolean'],
            'allows_pickup' => ['required', 'boolean'],
            'allows_dine_in' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'allows_delivery.required' => 'El campo entrega a domicilio es obligatorio.',
            'allows_delivery.boolean' => 'El campo entrega a domicilio debe ser verdadero o falso.',
            'allows_pickup.required' => 'El campo recoger en sucursal es obligatorio.',
            'allows_pickup.boolean' => 'El campo recoger en sucursal debe ser verdadero o falso.',
            'allows_dine_in.required' => 'El campo comer en el lugar es obligatorio.',
            'allows_dine_in.boolean' => 'El campo comer en el lugar debe ser verdadero o falso.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $data = $this->validated();
                $anyActive = ($data['allows_delivery'] ?? false)
                    || ($data['allows_pickup'] ?? false)
                    || ($data['allows_dine_in'] ?? false);

                if (! $anyActive) {
                    $validator->errors()->add('allows_delivery', 'Al menos un método de entrega debe estar activo.');
                }

                // Require at least one delivery range to activate delivery
                if ($data['allows_delivery'] ?? false) {
                    $restaurantId = $this->user()->restaurant_id;
                    $hasRanges = DeliveryRange::where('restaurant_id', $restaurantId)->exists();

                    if (! $hasRanges) {
                        $validator->errors()->add('allows_delivery', 'Debes configurar al menos una tarifa de envío antes de activar la entrega a domicilio.');
                    }
                }
            },
        ];
    }
}
