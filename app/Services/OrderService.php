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

    public function getAllOrders(?string $status = null, ?string $dateFrom = null,
    ?string $dateTo = null, ?string $search = null, int $perPage = 15)
    {
        return $this->orderRepository->getAllOrders($status, $dateFrom, $dateTo, $search, $perPage);
    }

    public function findOrderWithDetails(string $orderId)
    {
        return $this->orderRepository->findWithDetails($orderId);
    }

    public function updateOrderStatusAsAdmin(string $orderId, OrderStatusData $data, string $adminId)
    {
        $order = $this->orderRepository->update($orderId, $data);
        $this->addOrderStatusHistory($orderId, $data, $adminId);

        // Here you could add admin-specific logic, like:
        // - Logging admin actions
        // - Sending different types of notifications
        // - Triggering specific workflows

        return $order;
    }
}
