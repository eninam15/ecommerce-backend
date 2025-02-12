<?php
// AdminOrderController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Http\Resources\OrderResource;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Requests\Admin\OrderFilterRequest;
use App\Dtos\OrderStatusData;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function getAllOrders(OrderFilterRequest $request)
    {
        $orders = $this->orderService->getAllOrders(
            $request->get('status'),
            $request->get('date_from'),
            $request->get('date_to'),
            $request->get('search'),
            $request->get('per_page', 15)
        );

        return OrderResource::collection($orders);
    }

    public function getOrderByUser(string $userId)
    {
        $orders = $this->orderService->getUserOrders($userId);
        return OrderResource::collection($orders);
    }

    public function getOrderById(string $id)
    {
        $order = $this->orderService->findOrderWithDetails($id);
        //dd($order);
        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id)
    {
        $order = $this->orderService->updateOrderStatusAsAdmin(
            $id,
            OrderStatusData::fromRequest($request),
            auth()->id()
        );

        return new OrderResource($order);
    }
}
