<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class CreateRestaurantRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'unique:restaurants,slug', 'regex:/^[a-z0-9-]+$/'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'billing_mode' => ['required', 'in:grace,manual'],
            'orders_limit' => ['required_if:billing_mode,manual', 'nullable', 'integer', 'min:1', 'max:999999'],
            'max_branches' => ['required_if:billing_mode,manual', 'nullable', 'integer', 'min:1', 'max:100'],
            'orders_limit_start' => ['required_if:billing_mode,manual', 'nullable', 'date'],
            'orders_limit_end' => ['required_if:billing_mode,manual', 'nullable', 'date', 'after:orders_limit_start'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El slug solo puede contener letras minúsculas, números y guiones.',
            'slug.unique' => 'Este slug ya está en uso.',
            'admin_email.unique' => 'Este correo ya está registrado.',
            'orders_limit.required_if' => 'El límite de pedidos es obligatorio en modo manual.',
            'max_branches.required_if' => 'El máximo de sucursales es obligatorio en modo manual.',
            'orders_limit_start.required_if' => 'La fecha de inicio es obligatoria en modo manual.',
            'orders_limit_end.required_if' => 'La fecha de fin es obligatoria en modo manual.',
            'orders_limit_end.after' => 'La fecha de fin debe ser posterior a la de inicio.',
        ];
    }
}
