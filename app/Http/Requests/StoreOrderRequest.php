<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesItemModifiers;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'customer' => ['required', 'array'],
            'customer.token' => ['required', 'string', 'max:255'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'regex:/^\d{10}$/'],

            'delivery_type' => ['required', 'in:delivery,pickup,dine_in'],
            'branch_id' => ['required', 'integer', 'min:1'],

            'address_street' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'max:255'],
            'address_number' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'max:50'],
            'address_colony' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'max:255'],
            'address_references' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'between:-180,180'],
            'distance_km' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'min:0'],
            'delivery_cost' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'min:0'],

            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'payment_method' => ['required', 'in:cash,terminal,transfer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000', 'prohibited_unless:payment_method,cash'],
            'requires_invoice' => ['nullable', 'boolean'],

            'coupon_code' => ['nullable', 'string', 'max:20'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.promotion_id'],
            'items.*.promotion_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.modifiers' => ['nullable', 'array', 'max:20'],
            'items.*.modifiers.*.modifier_option_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.modifiers.*.modifier_option_template_id'],
            'items.*.modifiers.*.modifier_option_template_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.modifiers.*.modifier_option_id'],
            'items.*.modifiers.*.price_adjustment' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer.phone.regex' => 'El telefono debe ser de exactamente 10 digitos numericos.',
            'cash_amount.max' => 'El monto maximo de pago en efectivo es $100,000.',
            'cash_amount.min' => 'El monto debe ser mayor a cero.',
            'items.*.product_id.required_without' => 'Cada item debe tener un producto o una promocion.',
            'items.*.promotion_id.required_without' => 'Cada item debe tener un producto o una promocion.',
        ];
    }
}
