<?php

namespace App\Mail\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?string $carrier = null,
        public ?\DateTime $estimatedDelivery = null
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
            subject: "🚚 Tu Orden #{$this->order->id} está en Camino",
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
            view: 'emails.orders.shipped',
            with: [
                'order' => $this->order,
                'user' => $this->order->user,
                'items' => $this->order->items,
                'shippingAddress' => $this->order->shippingAddress,
                'trackingNumber' => $this->trackingNumber,
                'carrier' => $this->carrier,
                'estimatedDelivery' => $this->estimatedDelivery,
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

    /**
     * Get carrier tracking URL if available
     */
    public function getCarrierTrackingUrl(): ?string
    {
        if (!$this->trackingNumber || !$this->carrier) {
            return null;
        }

        return match(strtolower($this->carrier)) {
            'dhl' => "https://www.dhl.com/tracking?id={$this->trackingNumber}",
            'fedex' => "https://www.fedex.com/tracking?id={$this->trackingNumber}",
            'ups' => "https://www.ups.com/tracking?id={$this->trackingNumber}",
            default => null
        };
    }
}