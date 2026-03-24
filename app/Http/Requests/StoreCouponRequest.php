<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $restaurantId = $this->user()->restaurant_id;

        return [
            'code' => [
                'required', 'string', 'max:20', 'alpha_dash',
                Rule::unique('coupons')->where('restaurant_id', $restaurantId),
            ],
            'discount_type' => ['required', 'in:fixed,percentage'],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'max_discount' => ['nullable', 'numeric', 'min:0.01', 'max:99999.99'],
            'min_purchase' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'max_total_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'El código del cupón es obligatorio.',
            'code.max' => 'El código no puede tener más de 20 caracteres.',
            'code.alpha_dash' => 'El código solo puede contener letras, números, guiones y guiones bajos.',
            'code.unique' => 'Ya existe un cupón con este código.',
            'discount_type.required' => 'El tipo de descuento es obligatorio.',
            'discount_type.in' => 'El tipo de descuento debe ser monto fijo o porcentaje.',
            'discount_value.required' => 'El valor del descuento es obligatorio.',
            'discount_value.min' => 'El valor del descuento debe ser mayor a cero.',
            'discount_value.max' => 'El valor del descuento es demasiado alto.',
            'max_discount.min' => 'El descuento máximo debe ser mayor a cero.',
            'min_purchase.min' => 'La compra mínima no puede ser negativa.',
            'ends_at.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'max_uses_per_customer.min' => 'El límite por cliente debe ser al menos 1.',
            'max_total_uses.min' => 'El límite total de usos debe ser al menos 1.',
        ];
    }
}
