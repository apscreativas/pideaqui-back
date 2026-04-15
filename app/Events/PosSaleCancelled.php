<?php

namespace App\Events;

use App\Models\PosSale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosSaleCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PosSale $sale,
        public readonly string $previousStatus,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.'.$this->sale->restaurant_id.'.pos'),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'sale' => [
                'id' => $this->sale->id,
                'ticket_number' => $this->sale->ticket_number,
                'status' => 'cancelled',
                'total' => $this->sale->total,
                'cancellation_reason' => $this->sale->cancellation_reason,
            ],
            'previous_status' => $this->previousStatus,
        ];
    }
}
