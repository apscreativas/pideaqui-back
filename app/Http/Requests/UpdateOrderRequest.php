<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesItemModifiers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    use ValidatesItemModifiers;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'expected_updated_at' => ['required', 'date'],

            // Items (optional — only sent when products change)
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.promotion_id'],
            'items.*.promotion_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.modifiers' => ['nullable', 'array', 'max:20'],
            'items.*.modifiers.*.modifier_option_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.modifiers.*.modifier_option_template_id'],
            'items.*.modifiers.*.modifier_option_template_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.modifiers.*.modifier_option_id'],

            // Address fields (optional — only sent when address changes)
            'address_street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address_colony' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_references' => ['sometimes', 'nullable', 'string', 'max:500'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],

            // Payment method (optional — only sent when payment changes)
            'payment_method' => ['sometimes', 'in:cash,terminal,transfer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000', 'prohibited_unless:payment_method,cash'],

            // Audit reason
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expected_updated_at.required' => 'Falta la referencia temporal del pedido.',
            'items.min' => 'El pedido debe tener al menos un producto.',
            'items.max' => 'El pedido no puede tener más de 50 productos.',
            'items.*.quantity.min' => 'La cantidad mínima es 1.',
            'items.*.quantity.max' => 'La cantidad máxima es 100.',
            'items.*.product_id.required_without' => 'Cada item debe tener un producto o una promoción.',
            'items.*.promotion_id.required_without' => 'Cada item debe tener un producto o una promoción.',
            'cash_amount.max' => 'El monto máximo de pago en efectivo es $100,000.',
            'cash_amount.min' => 'El monto debe ser mayor a cero.',
            'reason.max' => 'El motivo no debe exceder 500 caracteres.',
        ];
    }
}
