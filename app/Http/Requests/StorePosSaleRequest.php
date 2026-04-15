<?php

namespace App\Http\Requests;

use App\Models\PosSale;
use Illuminate\Foundation\Http\FormRequest;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PosSale::class) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
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
            'branch_id.required' => 'Selecciona la sucursal.',
            'items.required' => 'La venta debe tener al menos un producto.',
            'items.min' => 'La venta debe tener al menos un producto.',
            'items.max' => 'No se permiten más de 50 productos por venta.',
            'items.*.product_id.required' => 'Cada item debe tener un producto.',
            'items.*.quantity.max' => 'Cantidad máxima por item: 100.',
        ];
    }
}
