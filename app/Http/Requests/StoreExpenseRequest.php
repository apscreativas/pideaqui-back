<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseSubcategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        $restaurantId = $this->user()->restaurant_id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('restaurant_id', $restaurantId),
            ],
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('restaurant_id', $restaurantId)
                    ->where('is_active', true),
            ],
            'expense_subcategory_id' => [
                'required',
                'integer',
                Rule::exists('expense_subcategories', 'id')->where('is_active', true),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];
    }

    /** Cross-validate that the subcategory belongs to the selected category. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $sub = ExpenseSubcategory::find($this->input('expense_subcategory_id'));
            if (! $sub || $sub->expense_category_id !== (int) $this->input('expense_category_id')) {
                $v->errors()->add('expense_subcategory_id', 'La subcategoría no pertenece a la categoría seleccionada.');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'expense_date.before_or_equal' => 'La fecha no puede ser futura.',
            'branch_id.required' => 'Selecciona la sucursal.',
            'branch_id.exists' => 'La sucursal no pertenece a este restaurante.',
            'expense_category_id.required' => 'Selecciona una categoría.',
            'expense_category_id.exists' => 'La categoría no es válida.',
            'expense_subcategory_id.required' => 'Selecciona una subcategoría.',
            'expense_subcategory_id.exists' => 'La subcategoría no es válida.',
            'attachments.max' => 'Máximo 10 archivos adjuntos.',
            'attachments.*.mimes' => 'Los archivos deben ser imágenes o PDF.',
            'attachments.*.max' => 'Cada archivo no debe exceder 5 MB.',
        ];
    }
}
