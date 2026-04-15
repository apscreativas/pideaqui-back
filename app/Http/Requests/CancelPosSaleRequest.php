<?php

namespace App\Http\Requests;

use App\Models\PosSale;
use Illuminate\Foundation\Http\FormRequest;

class CancelPosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof PosSale && $this->user()?->can('cancel', $sale);
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Captura el motivo de cancelación.',
        ];
    }
}
