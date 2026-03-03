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
}
