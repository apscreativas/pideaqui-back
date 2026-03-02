<?php

namespace App\Http\Resources;

use App\DTOs\OrderCreatedResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderCreatedResult */
class OrderConfirmationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => sprintf('#%04d', $this->order->id),
            'branch_whatsapp' => $this->order->branch->whatsapp,
            'whatsapp_message' => $this->whatsappMessage,
        ];
    }
}
