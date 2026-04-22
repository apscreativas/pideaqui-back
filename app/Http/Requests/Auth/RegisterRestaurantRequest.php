<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restaurant_name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                new ValidSlug,
                Rule::unique('restaurants', 'slug'),
            ],
            'admin_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'restaurant_name.required' => 'El nombre del restaurante es obligatorio.',
            'slug.unique' => 'Ese slug ya está en uso. Prueba con otro.',
            'admin_name.required' => 'Tu nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.letters' => 'La contraseña debe incluir letras.',
            'password.mixed' => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('slug');
        $clean = is_string($raw) ? strtolower(trim($raw)) : null;

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'slug' => $clean === '' ? null : $clean,
        ]);
    }
}
