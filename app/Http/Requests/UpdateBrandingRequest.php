<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'primary_color' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
            'default_product_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
            'remove_default_image' => ['sometimes', 'boolean'],
            'text_color' => ['nullable', 'in:light,dark'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'El color debe ser un valor hexadecimal válido (ej. #FF5722).',
            'secondary_color.regex' => 'El color debe ser un valor hexadecimal válido (ej. #FF5722).',
            'default_product_image.image' => 'El archivo debe ser una imagen válida.',
            'default_product_image.mimes' => 'La imagen debe ser de tipo: JPG, PNG, GIF o WebP.',
            'default_product_image.max' => 'La imagen no debe pesar más de 2 MB.',
        ];
    }
}
