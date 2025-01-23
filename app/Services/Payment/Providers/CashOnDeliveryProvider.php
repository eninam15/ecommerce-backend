<?php
namespace App\Services\Payment\Providers;

class CashOnDeliveryProvider implements PaymentProviderInterface
{
    public function createPayment(Order $order, array $paymentData)
    {
        // Simplemente registra el pago como pendiente
        return [
            'status' => PaymentStatus::PENDING,
            'amount' => $order->total
        ];
    }

    // Implementar los demás métodos...
}
