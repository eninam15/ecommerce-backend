<?php
namespace App\Repositories\Interfaces;

use App\Dtos\PaymentData;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

interface PaymentRepositoryInterface
{
    public function create(PaymentData $data): Payment;
    public function update(string $id, PaymentData $data): Payment;
    public function findByOrderId(string $orderId): ?Payment;
    public function createAttempt(Payment $payment, array $attemptData): PaymentAttempt;
    public function findPaymentAttempts(string $paymentId);
    public function completePayment(string $paymentId, array $completionData): Payment;
    public function findById(string $id): ?Payment;
    public function findByTransactionId(string $transactionId): ?Payment;
    public function getPendingPayments(): \Illuminate\Support\Collection;
    public function getPaymentsByStatus(PaymentStatus $status): \Illuminate\Support\Collection;
    public function getPaymentsByMethod(PaymentMethod $method): \Illuminate\Support\Collection;
    public function deleteExpiredPendingPayments(): int;
}
