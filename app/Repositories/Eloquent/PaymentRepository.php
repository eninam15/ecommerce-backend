<?php
namespace App\Repositories\Eloquent;

use App\Dtos\PaymentData;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\Carbon;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(PaymentData $data): Payment
    {
        return Payment::create($data->toArray());
    }

    public function update(string $id, PaymentData $data): Payment
    {
        $payment = $this->findById($id);
        $payment->update($data->toArray());
        return $payment->fresh();
    }

    public function findByOrderId(string $orderId): ?Payment
    {
        return Payment::where('order_id', $orderId)
            ->whereIn('status', [
                PaymentStatus::PENDING,
                PaymentStatus::PROCESSING
            ])
            ->first();
    }

    public function createAttempt(Payment $payment, array $attemptData): PaymentAttempt
    {
        return $payment->attempts()->create($attemptData);
    }

    public function findPaymentAttempts(string $paymentId)
    {
        return PaymentAttempt::where('payment_id', $paymentId)->get();
    }

    public function completePayment(string $paymentId, array $completionData): Payment
    {
        $payment = $this->findById($paymentId);

        $payment->update([
            'status' => $completionData['status'],
            'transaction_id' => $completionData['transaction_id'] ?? null,
            'paid_at' => now(),
            'metadata' => $completionData['metadata'] ?? null
        ]);

        return $payment->fresh();
    }

    public function findById(string $id): ?Payment
    {
        return Payment::findOrFail($id);
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    public function getPendingPayments(): \Illuminate\Support\Collection
    {
        return Payment::where('status', PaymentStatus::PENDING)
            ->orWhere('status', PaymentStatus::PROCESSING)
            ->get();
    }

    public function getPaymentsByStatus(PaymentStatus $status): \Illuminate\Support\Collection
    {
        return Payment::where('status', $status)->get();
    }

    public function getPaymentsByMethod(PaymentMethod $method): \Illuminate\Support\Collection
    {
        return Payment::where('payment_method', $method)->get();
    }

    public function deleteExpiredPendingPayments(): int
    {
        return Payment::where('status', PaymentStatus::PENDING)
            ->where('created_at', '<', Carbon::now()->subHours(24))
            ->delete();
    }

    public function findByPPETransactionId(string $transactionId): ?Payment
    {
        return Payment::whereJsonContains('metadata->ppe_transaction_id', $transactionId)->first();
    }
}
