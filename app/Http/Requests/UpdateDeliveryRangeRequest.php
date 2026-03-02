<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'min_km' => ['required', 'numeric', 'min:0'],
            'max_km' => ['required', 'numeric', 'gt:min_km'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
