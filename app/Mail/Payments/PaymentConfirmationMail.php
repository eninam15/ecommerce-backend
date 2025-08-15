<?php

namespace App\Mail\Payments;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {
        // Asegurar que las relaciones estén cargadas
        $this->payment->load([
            'order.user',
            'order.items.product',
            'order.shippingAddress',
            'order.coupon'
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Pago Confirmado - Orden #{$this->payment->order->id}",
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
            view: 'emails.payments.confirmation',
            with: [
                'payment' => $this->payment,
                'order' => $this->payment->order,
                'user' => $this->payment->order->user,
                'items' => $this->payment->order->items,
                'shippingAddress' => $this->payment->order->shippingAddress,
                'coupon' => $this->payment->order->coupon,
                'trackingUrl' => $this->getTrackingUrl(),
                'supportEmail' => config('mail.from.address'),
                'paymentMethod' => $this->getPaymentMethodLabel(),
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
        return config('services.frontend.order_tracking_url') . '/' . $this->payment->order->id;
    }

    /**
     * Get payment method label
     */
    private function getPaymentMethodLabel(): string
    {
        return match($this->payment->payment_method) {
            'credit_card' => 'Tarjeta de Crédito',
            'qr' => 'Código QR',
            'cash_on_delivery' => 'Pago Contra Entrega',
            'bank_transfer' => 'Transferencia Bancaria',
            default => 'Otro Método'
        };
    }
}