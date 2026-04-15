<?php

namespace App\Events;

use App\Models\PosSale;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PosSaleStatusChanged implements ShouldBroadcastNow
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
                'status' => $this->sale->status,
                'total' => $this->sale->total,
                'created_at' => $this->sale->created_at->toISOString(),
                'cashier' => $this->sale->cashier ? ['id' => $this->sale->cashier->id, 'name' => $this->sale->cashier->name] : null,
                'branch' => $this->sale->branch ? ['id' => $this->sale->branch->id, 'name' => $this->sale->branch->name] : null,
            ],
            'previous_status' => $this->previousStatus,
        ];
    }
}
