<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'clabe' => ['nullable', 'string', 'size:18'],
            'alias' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $paymentMethod = $this->route('paymentMethod');

                if ($paymentMethod && $paymentMethod->type === 'transfer' && $this->boolean('is_active')) {
                    if (empty($this->input('bank_name'))) {
                        $validator->errors()->add('bank_name', 'El banco es obligatorio para activar transferencia.');
                    }
                    if (empty($this->input('account_holder'))) {
                        $validator->errors()->add('account_holder', 'El titular es obligatorio para activar transferencia.');
                    }
                    if (empty($this->input('clabe'))) {
                        $validator->errors()->add('clabe', 'La CLABE es obligatoria para activar transferencia.');
                    }
                }

                // Prevent deactivating the last active payment method
                if ($paymentMethod && ! $this->boolean('is_active')) {
                    $otherActiveCount = PaymentMethod::query()
                        ->where('restaurant_id', $paymentMethod->restaurant_id)
                        ->where('id', '!=', $paymentMethod->id)
                        ->where('is_active', true)
                        ->count();

                    if ($otherActiveCount === 0) {
                        $validator->errors()->add('is_active', 'Debe haber al menos un metodo de pago activo.');
                    }
                }
            },
        ];
    }
}
