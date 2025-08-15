<?php

namespace App\Mail\Orders;

use App\Models\Order;
use App\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatus $newStatus,
        public ?string $comment = null
    ) {
        // Asegurar que las relaciones estén cargadas
        $this->order->load([
            'user',
            'items.product',
            'shippingAddress'
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubjectByStatus(),
            from: config('mail.from.address'),
            replyTo: config('mail.from.address')
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.orders.status-update',
            with: [
                'order' => $this->order,
                'user' => $this->order->user,
                'newStatus' => $this->newStatus,
                'statusLabel' => $this->newStatus->label(),
                'comment' => $this->comment,
                'trackingUrl' => $this->getTrackingUrl(),
                'supportEmail' => config('mail.from.address'),
                'statusMessage' => $this->getStatusMessage(),
                'nextSteps' => $this->getNextSteps(),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get subject based on status
     */
    private function getSubjectByStatus(): string
    {
        return match($this->newStatus) {
            OrderStatus::PAID => "✅ Pago Confirmado - Orden #{$this->order->id}",
            OrderStatus::PROCESSING => "📦 Preparando tu Orden #{$this->order->id}",
            OrderStatus::SHIPPED => "🚚 Orden Enviada #{$this->order->id}",
            OrderStatus::DELIVERED => "✅ Orden Entregada #{$this->order->id}",
            OrderStatus::CANCELLED => "❌ Orden Cancelada #{$this->order->id}",
            OrderStatus::REFUNDED => "💰 Reembolso Procesado - Orden #{$this->order->id}",
            default => "Actualización de Orden #{$this->order->id}"
        };
    }

    /**
     * Get status message
     */
    private function getStatusMessage(): string
    {
        return match($this->newStatus) {
            OrderStatus::PAID => "¡Excelente! Hemos confirmado tu pago y ya estamos preparando tu pedido.",
            OrderStatus::PROCESSING => "Tu orden está siendo preparada por nuestro equipo.",
            OrderStatus::SHIPPED => "¡Tu orden ya está en camino! Te llegará pronto.",
            OrderStatus::DELIVERED => "¡Tu orden ha sido entregada con éxito! Esperamos que disfrutes tus productos.",
            OrderStatus::CANCELLED => "Tu orden ha sido cancelada según tu solicitud.",
            OrderStatus::REFUNDED => "Hemos procesado tu reembolso. El dinero debería aparecer en tu cuenta pronto.",
            default => "Tu orden ha sido actualizada."
        };
    }

    /**
     * Get next steps based on status
     */
    private function getNextSteps(): ?string
    {
        return match($this->newStatus) {
            OrderStatus::PAID => "Comenzaremos a procesar tu orden inmediatamente. Te notificaremos cuando esté lista para envío.",
            OrderStatus::PROCESSING => "Te notificaremos tan pronto como tu orden sea enviada.",
            OrderStatus::SHIPPED => "Puedes rastrear tu envío usando el enlace de seguimiento incluido en este email.",
            OrderStatus::DELIVERED => "Si tienes algún problema con tu orden, no dudes en contactarnos.",
            OrderStatus::CANCELLED => "Si cambiaste de opinión, puedes realizar una nueva orden cuando gustes.",
            OrderStatus::REFUNDED => "El reembolso puede tardar 3-5 días hábiles en aparecer en tu cuenta.",
            default => null
        };
    }

    /**
     * Get tracking URL for the order
     */
    private function getTrackingUrl(): string
    {
        return config('services.frontend.order_tracking_url') . '/' . $this->order->id;
    }
}