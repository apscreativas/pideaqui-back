<?php

namespace App\Http\Requests;

use App\Models\ExpenseSubcategory;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseSubcategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sub = $this->route('subcategory');
        if (! $sub instanceof ExpenseSubcategory) {
            return false;
        }

        return $this->user()?->can('update', $sub->category) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => 'El nombre de la subcategoría es obligatorio.'];
    }
}
