<?php

namespace App\Http\Resources;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->value,
            'name' => $this->label(),
            'component' => $this->getComponent(),
            'icon' => $this->getIcon()
        ];
    }

    private function getComponent(): string
    {
        return match($this->value) {
            PaymentMethod::CREDIT_CARD->value => 'CreditCardPayment',
            PaymentMethod::QR->value => 'QrPayment',
            PaymentMethod::CASH_ON_DELIVERY->value => 'CashOnDeliveryPayment'
        };
    }
}
