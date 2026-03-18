<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'production_cost' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'is_active' => ['boolean'],
            'active_days' => ['required', 'array', 'min:1'],
            'active_days.*' => ['integer', 'between:0,6'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'modifier_groups' => ['nullable', 'array'],
            'modifier_groups.*.id' => ['nullable', 'integer'],
            'modifier_groups.*.name' => ['required', 'string', 'max:255'],
            'modifier_groups.*.selection_type' => ['required', 'in:single,multiple'],
            'modifier_groups.*.is_required' => ['boolean'],
            'modifier_groups.*.options' => ['required', 'array', 'min:1'],
            'modifier_groups.*.options.*.id' => ['nullable', 'integer'],
            'modifier_groups.*.options.*.name' => ['required', 'string', 'max:255'],
            'modifier_groups.*.options.*.price_adjustment' => ['numeric', 'min:0'],
            'modifier_groups.*.options.*.production_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'price.required' => 'El precio es obligatorio.',
            'price.min' => 'El precio debe ser mayor a 0.',
            'price.max' => 'El precio no puede ser mayor a $99,999.99.',
            'production_cost.max' => 'El costo no puede ser mayor a $99,999.99.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser de tipo: JPG, PNG, GIF o WebP.',
            'image.max' => 'La imagen no debe pesar más de 5 MB.',
            'active_days.required' => 'Selecciona al menos un día de la semana.',
            'active_days.min' => 'Selecciona al menos un día de la semana.',
            'active_days.*.between' => 'Día de la semana inválido.',
            'starts_at.date_format' => 'El formato de hora de inicio debe ser HH:MM.',
            'ends_at.date_format' => 'El formato de hora de fin debe ser HH:MM.',
            'modifier_groups.*.name.required' => 'El nombre del grupo de modificadores es obligatorio.',
            'modifier_groups.*.selection_type.required' => 'El tipo de selección es obligatorio.',
            'modifier_groups.*.options.required' => 'Cada grupo debe tener al menos una opción.',
            'modifier_groups.*.options.min' => 'Cada grupo debe tener al menos una opción.',
            'modifier_groups.*.options.*.name.required' => 'El nombre de la opción es obligatorio.',
        ];
    }
}
