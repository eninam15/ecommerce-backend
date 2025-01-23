<?php

namespace App\Repositories\Interfaces;
use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;

interface OrderRepositoryInterface
{
    public function create(string $userId, OrderData $data);
    public function update(string $id, OrderStatusData $data);
    public function find(string $id);
    public function getUserOrders(string $userId);
    public function addStatusHistory(string $orderId, OrderStatusData $data, string $userId);
}
