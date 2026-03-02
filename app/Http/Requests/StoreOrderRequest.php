<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
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
            'customer.phone' => ['required', 'string', 'max:30'],

            'delivery_type' => ['required', 'in:delivery,pickup,dine_in'],
            'branch_id' => ['required', 'integer', 'min:1'],

            'address' => ['nullable', 'required_if:delivery_type,delivery', 'string', 'max:500'],
            'address_references' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'between:-180,180'],
            'distance_km' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'min:0'],
            'delivery_cost' => ['nullable', 'required_if:delivery_type,delivery', 'numeric', 'min:0'],

            'scheduled_at' => ['nullable', 'date'],
            'payment_method' => ['required', 'in:cash,terminal,transfer'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.modifiers' => ['nullable', 'array'],
            'items.*.modifiers.*.modifier_option_id' => ['required', 'integer', 'min:1'],
            'items.*.modifiers.*.price_adjustment' => ['required', 'numeric'],
        ];
    }
}
