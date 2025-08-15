<?php

namespace App\Events\Orders;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class OrderDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public ?\DateTime $deliveredAt = null
    ) {
        // Cargar relaciones necesarias para las notificaciones
        $this->order->load([
            'user', 
            'items.product', 
            'shippingAddress'
        ]);
        
        // Si no se especifica fecha, usar la actual
        $this->deliveredAt = $deliveredAt ?? now();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}