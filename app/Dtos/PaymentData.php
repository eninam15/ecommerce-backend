<?php
namespace App\Dtos;

use App\Http\Requests\PaymentRequest;
use Spatie\DataTransferObject\DataTransferObject;

class PaymentData extends DataTransferObject
{
    public function __construct(
        public string $orderId,
        public string $paymentMethod,
        public string $provider,
        public string $status,
        public float $amount,
        public string $currency = 'USD',
        public ?string $transactionId = null,
        public ?array $metadata = null
    ) {}

    public static function fromRequest(PaymentRequest $request): self
    {
        return new self(
            orderId: $request->input('order_id'),
            paymentMethod: $request->input('payment_method'),
            provider: $request->input('provider'),
            status: $request->input('status'),
            amount: $request->input('amount'),
            currency: $request->input('currency', 'USD'),
            transactionId: $request->input('transaction_id'),
            metadata: $request->input('metadata')
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'order_id' => $this->orderId,
            'payment_method' => $this->paymentMethod,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'transaction_id' => $this->transactionId,
            'metadata' => $this->metadata
        ], fn($value) => $value !== null);
    }
}
