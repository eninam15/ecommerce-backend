<?php

namespace App\Mail\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
        // Asegurar que las relaciones estén cargadas
        $this->order->load([
            'user',
            'items.product',
            'shippingAddress',
            'coupon'
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmación de Orden #{$this->order->id}",
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
            view: 'emails.orders.confirmation',
            with: [
                'order' => $this->order,
                'user' => $this->order->user,
                'items' => $this->order->items,
                'shippingAddress' => $this->order->shippingAddress,
                'coupon' => $this->order->coupon,
                'trackingUrl' => $this->getTrackingUrl(),
                'supportEmail' => config('mail.from.address'),
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
     * Get tracking URL for the order
     */
    private function getTrackingUrl(): string
    {
        return config('services.frontend.order_tracking_url') . '/' . $this->order->id;
    }
}