<?php
namespace App\Services\Payment\Providers;

class QrPaymentProvider implements PaymentProviderInterface
{
    public function createPayment(Order $order, array $paymentData)
    {
        // Implementar generación de QR para el pago
        $qrData = $this->generateQrCode($order);

        return [
            'qr_code' => $qrData,
            'expiration' => now()->addMinutes(30)
        ];
    }

    // Implementar los demás métodos...
}
