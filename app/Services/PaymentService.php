<?php
namespace App\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\OrderService;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Services\Payment\PaymentProviderFactory;
use App\Exceptions\PaymentException;
use App\Dtos\PaymentData;

class PaymentService
{
    public function __construct(
        protected PaymentRepositoryInterface $paymentRepository,
        protected OrderService $orderService
    ) {}

    public function initiatePayment(string $orderId, PaymentMethod $method, array $paymentData)
    {
        $order = $this->orderService->findOrder($orderId);

        // Verificar si ya existe un pago pendiente
        if ($this->hasActivePendingPayment($order)) {
            throw new Exception('Order has pending payment');
        }

        //$provider = PaymentProviderFactory::create($method);

        DB::beginTransaction();
        try {
            // Crear el registro de pago
            $payment = $this->paymentRepository->create(
                new PaymentData(
                    orderId: $order->id,
                    paymentMethod: $method->value,
                    provider: 'credit',
                    status: PaymentStatus::PENDING->value,
                    amount: $order->total,
                    currency: 'USD'
                )
            );
            /*// Procesar con el proveedor
            $result = $provider->createPayment($order, $paymentData);

            // Registrar el intento
            $this->paymentRepository->createAttempt($payment, [
                'status' => PaymentStatus::PROCESSING,
                'response_data' => $result
            ]);

            // Actualizar metadata del pago
            $this->paymentRepository->update($payment->id, [
                'metadata' => $result
            ]);*/

            DB::commit();
            return $payment->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function handleWebhook(Request $request, string $provider)
    {
        $providerInstance = PaymentProviderFactory::create(PaymentMethod::from($provider));

        if (!$providerInstance->validateWebhook($request)) {
            throw new PaymentException('Invalid webhook signature');
        }

        return $providerInstance->handleWebhook($request);
    }

    public function hasActivePendingPayment($order)
    {
        return $this->paymentRepository->findByOrderId($order->id) !== null;
    }

    public function completePayment(string $paymentId, array $data)
    {
        return DB::transaction(function () use ($paymentId, $data) {
            $payment = $this->paymentRepository->completePayment($paymentId, $data);

            // Actualizar estado de la orden
            if ($payment->status === PaymentStatus::COMPLETED) {
                $this->orderService->updateOrderStatus($payment->order_id, [
                    'status' => OrderStatus::PAID
                ]);
            }

            return $payment;
        });
    }

    public function getPaymentAttempts(string $paymentId)
    {
        return $this->paymentRepository->findPaymentAttempts($paymentId);
    }
}
