<?php

namespace App\Http\Requests\SuperAdmin;

use App\Rules\ValidSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantSlugRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var int $restaurantId */
        $restaurantId = (int) $this->route('restaurant')?->id;

        return [
            'slug' => [
                'required',
                'string',
                new ValidSlug,
                Rule::unique('restaurants', 'slug')->ignore($restaurantId),
            ],
            'confirm' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.required' => 'El nuevo slug es obligatorio.',
            'slug.unique' => 'Ese slug ya está en uso. Elige otro.',
            'confirm.accepted' => 'Debes confirmar que entiendes las consecuencias del cambio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('slug');
        $clean = is_string($raw) ? strtolower(trim($raw)) : null;
        $this->merge(['slug' => $clean === '' ? null : $clean]);
    }
}
