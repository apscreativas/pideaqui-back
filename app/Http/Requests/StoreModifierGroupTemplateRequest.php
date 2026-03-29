<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'selection_type' => ['required', 'in:single,multiple'],
            'is_required' => ['boolean'],
            'max_selections' => ['nullable', 'integer', 'min:2'],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:1'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.price_adjustment' => ['numeric', 'min:0'],
            'options.*.production_cost' => ['nullable', 'numeric', 'min:0'],
            'options.*.is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del grupo es obligatorio.',
            'selection_type.required' => 'El tipo de selección es obligatorio.',
            'options.required' => 'Debe tener al menos una opción.',
            'options.min' => 'Debe tener al menos una opción.',
            'options.*.name.required' => 'El nombre de la opción es obligatorio.',
            'max_selections.min' => 'El máximo de selecciones debe ser al menos 2.',
        ];
    }
}
