<?php

namespace App\Services\Payment\Contracts;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    public function createPayment(Order $order, array $paymentData);
    public function processPayment(Payment $payment, array $paymentData);
    public function refundPayment(Payment $payment, ?float $amount = null);
    public function validateWebhook(Request $request): bool;
    public function handleWebhook(Request $request);
    public function retrievePaymentStatus(Payment $payment);
    public function cancelPayment(Payment $payment);
}
