<?php
namespace App\Services\Payment\Providers;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use Illuminate\Http\Request;
use Stripe\Webhook;

class StripePaymentProvider implements PaymentProviderInterface
{
    protected $stripe;

    public function __construct()
    {
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
    }

    public function createPayment(Order $order, array $paymentData)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $order->total * 100, // Stripe usa centavos
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]
            ]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    public function processPayment(Payment $payment, array $paymentData)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->confirm(
                $paymentData['payment_intent_id'],
                ['payment_method' => $paymentData['payment_method_id']]
            );

            return [
                'status' => PaymentStatus::COMPLETED,
                'transaction_id' => $paymentIntent->id,
                'metadata' => $paymentIntent->toArray()
            ];
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    public function refundPayment(Payment $payment, ?float $amount = null)
    {
        try {
            $refundParams = [
                'payment_intent' => $payment->transaction_id
            ];

            if ($amount) {
                $refundParams['amount'] = $amount * 100; // Stripe usa centavos
            }

            $refund = $this->stripe->refunds->create($refundParams);

            return [
                'status' => PaymentStatus::REFUNDED,
                'refund_id' => $refund->id,
                'metadata' => $refund->toArray()
            ];
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    public function validateWebhook(Request $request): bool
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $webhookSecret = config('services.stripe.webhook_secret');

            Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $eventType = $payload['type'];

        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handleSuccessfulPayment($payload);
            case 'payment_intent.payment_failed':
                return $this->handleFailedPayment($payload);
            default:
                return null;
        }
    }

    public function retrievePaymentStatus(Payment $payment)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve(
                $payment->transaction_id
            );

            return [
                'status' => $this->mapStripeStatus($paymentIntent->status),
                'metadata' => $paymentIntent->toArray()
            ];
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    public function cancelPayment(Payment $payment)
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->cancel(
                $payment->transaction_id
            );

            return [
                'status' => PaymentStatus::CANCELLED,
                'metadata' => $paymentIntent->toArray()
            ];
        } catch (\Exception $e) {
            throw new PaymentException($e->getMessage());
        }
    }

    private function handleSuccessfulPayment($payload)
    {
        $paymentIntent = $payload['data']['object'];

        return [
            'status' => PaymentStatus::COMPLETED,
            'transaction_id' => $paymentIntent['id'],
            'metadata' => $paymentIntent,
            'order_id' => $paymentIntent['metadata']['order_id']
        ];
    }

    private function handleFailedPayment($payload)
    {
        $paymentIntent = $payload['data']['object'];

        return [
            'status' => PaymentStatus::FAILED,
            'transaction_id' => $paymentIntent['id'],
            'metadata' => $paymentIntent,
            'order_id' => $paymentIntent['metadata']['order_id']
        ];
    }

    private function mapStripeStatus(string $stripeStatus): string
    {
        $statusMap = [
            'requires_payment_method' => PaymentStatus::PENDING,
            'requires_confirmation' => PaymentStatus::PENDING,
            'requires_action' => PaymentStatus::PENDING,
            'processing' => PaymentStatus::PROCESSING,
            'succeeded' => PaymentStatus::COMPLETED,
            'requires_capture' => PaymentStatus::PROCESSING,
            'canceled' => PaymentStatus::CANCELLED
        ];

        return $statusMap[$stripeStatus] ?? PaymentStatus::FAILED;
    }
}
