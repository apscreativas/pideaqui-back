<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpecialDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $restaurantId = $this->user()->restaurant_id;

        return [
            'date' => [
                'required',
                'date',
                Rule::unique('restaurant_special_dates')->where('restaurant_id', $restaurantId),
            ],
            'type' => ['required', 'in:closed,special'],
            'opens_at' => ['nullable', 'required_if:type,special', 'date_format:H:i'],
            'closes_at' => ['nullable', 'required_if:type,special', 'date_format:H:i'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_recurring' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'La fecha es obligatoria.',
            'date.unique' => 'Ya existe una configuración para esta fecha.',
            'type.required' => 'El tipo es obligatorio.',
            'opens_at.required_if' => 'La hora de apertura es obligatoria para horario especial.',
            'closes_at.required_if' => 'La hora de cierre es obligatoria para horario especial.',
        ];
    }
}
