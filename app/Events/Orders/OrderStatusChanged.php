<?php

namespace App\Events\Orders;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class OrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatus $previousStatus,
        public OrderStatus $newStatus,
        public ?string $comment = null
    ) {
        // Cargar relaciones necesarias para las notificaciones
        $this->order->load([
            'user', 
            'items.product', 
            'shippingAddress'
        ]);
    }

    /**
     * Verificar si el cambio requiere notificación
     */
    public function shouldNotify(): bool
    {
        // Solo notificar en ciertos cambios de estado
        return in_array($this->newStatus, [
            OrderStatus::PAID,
            OrderStatus::PROCESSING,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDED
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