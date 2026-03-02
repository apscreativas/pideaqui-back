<?php

namespace App\Http\Requests;

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
            },
        ];
    }
}
