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

    public function registerPPEDebt(string $orderId, array $requestData)
    {
        $order = $this->orderService->findOrder($orderId);

        if ($this->hasActivePendingPayment($order)) {
            throw new Exception('Order has pending payment');
        }

        DB::beginTransaction();
        try {
            // Create initial payment record
            $payment = $this->paymentRepository->create(
                new PaymentData(
                    orderId: $order->id,
                    paymentMethod: PaymentMethod::PPE->value,
                    provider: 'ppe',
                    status: PaymentStatus::PENDING->value,
                    amount: $order->total,
                    currency: 'BOB'
                )
            );

            // Prepare PPE debt registration data
            $ppeData = [
                'descripcion' => "Orden #{$order->id}",
                'codigoOrden' => $order->id,
                'datosPago' => [
                    'nombresCliente' => $order->customer->first_name,
                    'apellidosCliente' => $order->customer->last_name,
                    'tipoDocumentoCliente' => 1, // CI
                    'numeroDocumentoCliente' => $order->customer->document_number,
                    'montoTotal' => $order->total,
                    'moneda' => 'BOB',
                    'correo' => $order->customer->email
                ],
                'productos' => $this->formatOrderItemsForPPE($order->items)
            ];

            // Call PPE API
            $response = $this->callPPEApi('/transaccion/deuda', $ppeData);

            if (!$response['finalizado']) {
                throw new Exception($response['mensaje']);
            }

            // Update payment with PPE transaction data
            $this->paymentRepository->update($payment->id, new PaymentData(
                metadata: [
                    'ppe_transaction_id' => $response['datos']['codigoTransaccion'],
                    'ppe_redirect_url' => $response['datos']['urlRedireccion']
                ]
            ));

            DB::commit();
            return $payment->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function handlePPEWebhook(array $webhookData, string $transactionCode)
    {
        // Validate webhook data
        if (!isset($webhookData['finalizado']) || !isset($webhookData['estado'])) {
            throw new Exception('Invalid webhook data');
        }

        $payment = $this->paymentRepository->findByPPETransactionId($transactionCode);
        if (!$payment) {
            throw new Exception('Payment not found');
        }

        if ($webhookData['finalizado'] && $webhookData['estado'] === 'PROCESADO') {
            // Payment successful
            return $this->completePayment($payment->id, [
                'status' => PaymentStatus::COMPLETED->value,
                'transaction_id' => $webhookData['codigoSeguimiento'],
                'metadata' => [
                    'ppe_response' => $webhookData
                ]
            ]);
        } else {
            // Payment failed
            return $this->completePayment($payment->id, [
                'status' => PaymentStatus::FAILED->value,
                'metadata' => [
                    'ppe_response' => $webhookData,
                    'error_message' => $webhookData['mensaje']
                ]
            ]);
        }
    }

    protected function formatOrderItemsForPPE($items)
    {
        return $items->map(function($item) {
            return [
                'actividadEconomica' => '620100', // Configure based on your business
                'descripcion' => $item->product->name,
                'precioUnitario' => $item->unit_price,
                'unidadMedida' => 58, // Configure based on your products
                'cantidad' => $item->quantity
            ];
        })->toArray();
    }

    protected function callPPEApi(string $endpoint, array $data)
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => config('services.ppe.base_url'),
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.ppe.token'),
                'Content-Type' => 'application/json'
            ]
        ]);

        $response = $client->post($endpoint, [
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }
}
