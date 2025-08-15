<?php

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class OrderShipped
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
        public ?\DateTime $estimatedDelivery = null
    ) {
        // Cargar relaciones necesarias para las notificaciones
        $this->order->load([
            'user', 
            'items.product', 
            'shippingAddress'
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}