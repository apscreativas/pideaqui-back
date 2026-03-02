<?php

namespace App\DTOs;

use App\Models\Order;

readonly class OrderCreatedResult
{
    public function __construct(
        public Order $order,
        public string $whatsappMessage,
    ) {}
}
