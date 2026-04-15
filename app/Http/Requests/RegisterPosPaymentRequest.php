<?php

namespace App\Http\Requests;

use App\Models\PosSale;
use Illuminate\Foundation\Http\FormRequest;

class RegisterPosPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof PosSale && $this->user()?->can('update', $sale);
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'payment_method_type' => ['required', 'in:cash,terminal,transfer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'cash_received' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99', 'prohibited_unless:payment_method_type,cash'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'payment_method_type.required' => 'Selecciona el método de pago.',
            'amount.required' => 'Captura el monto.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'cash_received.prohibited_unless' => 'El campo "efectivo recibido" solo aplica para pagos en efectivo.',
        ];
    }
}
