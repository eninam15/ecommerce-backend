<?php
namespace App\Enums;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case QR = 'qr';
    case CASH_ON_DELIVERY = 'cash_on_delivery';

    public function label(): string
    {
        return match($this) {
            self::CREDIT_CARD => 'Credit Card',
            self::QR => 'QR Payment',
            self::CASH_ON_DELIVERY => 'Cash on Delivery'
        };
    }
}
