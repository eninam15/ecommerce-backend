<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\CartResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\CartService;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Payment\PaymentRequest;
use App\Dtos\CartItemData;
use App\Services\PaymentService;
use App\Http\Resources\OrderResource;
use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;
use App\Enums\PaymentMethod;
use App\Http\Resources\PaymentResource;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function initiate(PaymentRequest $request, string $orderId)
    {
        $payment = $this->paymentService->initiatePayment(
            $orderId,
            PaymentMethod::from($request->payment_method),
            $request->payment_data ?? []
        );

        return new PaymentResource($payment);
    }

    public function webhook(Request $request, string $provider)
    {
        $result = $this->paymentService->handleWebhook($request, $provider);
        return response()->json($result);
    }

    public function confirm(string $paymentId)
    {
        // Para pagos contra entrega
        $payment = $this->paymentService->confirmPayment($paymentId);
        return new PaymentResource($payment);
    }
}
