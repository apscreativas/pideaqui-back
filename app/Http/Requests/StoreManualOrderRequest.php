<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
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

            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'payment_method' => ['required', 'in:cash,terminal,transfer'],
            'cash_amount' => ['nullable', 'numeric', 'min:0.01', 'max:100000', 'prohibited_unless:payment_method,cash'],
            'requires_invoice' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.promotion_id'],
            'items.*.promotion_id' => ['nullable', 'integer', 'min:1', 'required_without:items.*.product_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.modifiers' => ['nullable', 'array', 'max:20'],
            'items.*.modifiers.*.modifier_option_id' => ['nullable', 'integer', 'min:1', 'distinct', 'required_without:items.*.modifiers.*.modifier_option_template_id'],
            'items.*.modifiers.*.modifier_option_template_id' => ['nullable', 'integer', 'min:1', 'distinct', 'required_without:items.*.modifiers.*.modifier_option_id'],
            'items.*.modifiers.*.price_adjustment' => ['required', 'numeric', 'min:0', 'max:99999.99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'customer.name.required' => 'El nombre del cliente es obligatorio.',
            'customer.phone.required' => 'El teléfono del cliente es obligatorio.',
            'customer.phone.regex' => 'El teléfono debe ser de exactamente 10 dígitos numéricos.',
            'delivery_type.required' => 'Selecciona un tipo de entrega.',
            'branch_id.required' => 'Selecciona la sucursal que tomará el pedido.',
            'payment_method.required' => 'Selecciona el método de pago.',
            'address_street.required_if' => 'La calle es obligatoria para entregas a domicilio.',
            'address_number.required_if' => 'El número exterior es obligatorio para entregas a domicilio.',
            'address_colony.required_if' => 'La colonia es obligatoria para entregas a domicilio.',
            'latitude.required_if' => 'Selecciona la ubicación en el mapa.',
            'longitude.required_if' => 'Selecciona la ubicación en el mapa.',
            'cash_amount.max' => 'El monto máximo de pago en efectivo es $100,000.',
            'cash_amount.min' => 'El monto debe ser mayor a cero.',
            'items.required' => 'El pedido debe tener al menos un producto.',
            'items.min' => 'El pedido debe tener al menos un producto.',
            'items.*.product_id.required_without' => 'Cada item debe tener un producto o una promoción.',
            'items.*.promotion_id.required_without' => 'Cada item debe tener un producto o una promoción.',
        ];
    }
}
