<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\ExpenseSubcategory;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense && $this->user()?->can('update', $expense);
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
                Rule::exists('expense_categories', 'id')->where('restaurant_id', $restaurantId),
            ],
            'expense_subcategory_id' => [
                'required',
                'integer',
                Rule::exists('expense_subcategories', 'id'),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ];
    }

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
        return (new StoreExpenseRequest)->messages();
    }
}
