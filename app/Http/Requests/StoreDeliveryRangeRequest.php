<?php

namespace App\Http\Requests;

use App\Models\DeliveryRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'min_km' => ['required', 'numeric', 'min:0'],
            'max_km' => ['required', 'numeric', 'gt:min_km'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $minKm = (float) $this->input('min_km');
            $maxKm = (float) $this->input('max_km');
            $restaurantId = $this->user()->restaurant_id;

            $overlaps = DeliveryRange::where('restaurant_id', $restaurantId)
                ->where('min_km', '<', $maxKm)
                ->where('max_km', '>', $minKm)
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('min_km', 'Este rango se solapa con uno existente.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'min_km.required' => 'El km mínimo es obligatorio.',
            'min_km.numeric' => 'El km mínimo debe ser un número.',
            'min_km.min' => 'El km mínimo no puede ser negativo.',
            'max_km.required' => 'El km máximo es obligatorio.',
            'max_km.numeric' => 'El km máximo debe ser un número.',
            'max_km.gt' => 'El km máximo debe ser mayor al km mínimo.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
        ];
    }
}
