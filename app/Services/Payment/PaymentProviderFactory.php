<?php

namespace App\Services\Payment;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Enums\PaymentMethod;
use App\Services\Payment\Providers\StripePaymentProvider;
use App\Services\Payment\Providers\QrPaymentProvider;
use App\Services\Payment\Providers\CashOnDeliveryProvider;

class PaymentProviderFactory
{
    public static function create(PaymentMethod $method): PaymentProviderInterface
    {
        return match($method) {
            PaymentMethod::CREDIT_CARD => new StripePaymentProvider(),
            PaymentMethod::QR => new QrPaymentProvider(),
            PaymentMethod::CASH_ON_DELIVERY => new CashOnDeliveryProvider(),
            default => throw new \InvalidArgumentException('Invalid payment method')
        };
    }
}
