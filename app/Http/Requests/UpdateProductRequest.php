<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'price' => ['required', 'numeric', 'min:0'],
            'production_cost' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('restaurant_id', $this->user()->restaurant_id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
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
            'name.required' => 'El nombre del producto es obligatorio.',
            'price.required' => 'El precio es obligatorio.',
            'category_id.required' => 'Selecciona una categoría.',
            'image.image' => 'El archivo debe ser una imagen válida.',
            'image.mimes' => 'La imagen debe ser de tipo: JPG, PNG, GIF o WebP.',
            'image.max' => 'La imagen no debe pesar más de 2 MB.',
            'modifier_groups.*.name.required' => 'El nombre del grupo de modificadores es obligatorio.',
            'modifier_groups.*.selection_type.required' => 'El tipo de selección es obligatorio.',
            'modifier_groups.*.options.required' => 'Cada grupo debe tener al menos una opción.',
            'modifier_groups.*.options.min' => 'Cada grupo debe tener al menos una opción.',
            'modifier_groups.*.options.*.name.required' => 'El nombre de la opción es obligatorio.',
        ];
    }
}
