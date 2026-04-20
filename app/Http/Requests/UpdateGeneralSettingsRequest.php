<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'notify_new_orders' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.image' => 'El archivo debe ser una imagen válida.',
            'logo.mimes' => 'El logo debe ser de tipo: JPG, PNG, GIF o WebP.',
            'logo.max' => 'El logo no debe pesar más de 2 MB.',
        ];
    }
}
