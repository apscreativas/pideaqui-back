<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    /** @var array<string,string> */
    private const LABELS = [
        'cash' => 'Efectivo',
        'terminal' => 'Terminal bancaria',
        'transfer' => 'Transferencia',
    ];

    public function toArray(Request $request): array
    {
        $data = [
            'type' => $this->type,
            'label' => self::LABELS[$this->type] ?? $this->type,
        ];

        if ($this->type === 'transfer') {
            $data['bank_name'] = $this->bank_name;
            $data['account_holder'] = $this->account_holder;
            $data['clabe'] = $this->clabe;
            $data['alias'] = $this->alias;
        }

        return $data;
    }
}
