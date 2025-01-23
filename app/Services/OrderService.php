<?php

namespace App\Services;

use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\OrderRepositoryInterface;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        //protected NotificationService $notificationService
    ) {}

    public function createOrder(string $userId, OrderData $data)
    {
        $order = $this->orderRepository->create($userId, $data);

        // Enviar notificaciones
        //$this->notificationService->sendOrderConfirmation($order);

        return $order;
    }

    public function updateOrderStatus(string $orderId, OrderStatusData $data)
    {
        $order = $this->orderRepository->update($orderId, $data);

        // Enviar notificaciones según el estado
        //$this->notificationService->sendOrderStatusUpdate($order);

        return $order;
    }

    public function findOrder(string $orderId)
    {
        return $this->orderRepository->find($orderId);
    }

    public function getUserOrders(string $userId)
    {
        return $this->orderRepository->getUserOrders($userId);
    }

    public function addOrderStatusHistory(string $orderId, OrderStatusData $data, string $userId)
    {
        return $this->orderRepository->addStatusHistory($orderId, $data, $userId);
    }
}
