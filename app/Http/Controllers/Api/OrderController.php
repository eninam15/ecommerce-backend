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
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Dtos\CartItemData;
use App\Services\OrderService;
use App\Http\Resources\OrderResource;
use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index()
    {
        $orders = $this->orderService->getUserOrders(auth()->id());
        return OrderResource::collection($orders);
    }

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->createOrder(
            auth()->id(),
            OrderData::fromRequest($request)
        );

        return new OrderResource($order);
    }

    public function show(string $id)
    {
        $order = $this->orderService->findOrder($id);
        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id)
    {
        $order = $this->orderService->updateOrderStatus(
            $id,
            OrderStatusData::fromRequest($request)
        );

        return new OrderResource($order);
    }
}
