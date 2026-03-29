<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('restaurant.'.$this->order->restaurant_id),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'status' => $this->order->status,
                'delivery_type' => $this->order->delivery_type,
                'payment_method' => $this->order->payment_method,
                'subtotal' => $this->order->subtotal,
                'delivery_cost' => $this->order->delivery_cost,
                'total' => $this->order->total,
                'edit_count' => $this->order->edit_count,
                'edited_at' => $this->order->edited_at?->toISOString(),
                'scheduled_at' => $this->order->scheduled_at?->toISOString(),
                'created_at' => $this->order->created_at->toISOString(),
                'customer' => $this->order->customer ? [
                    'id' => $this->order->customer->id,
                    'name' => $this->order->customer->name,
                    'phone' => $this->order->customer->phone,
                ] : null,
                'branch' => $this->order->branch ? [
                    'id' => $this->order->branch->id,
                    'name' => $this->order->branch->name,
                ] : null,
            ],
        ];
    }
}
