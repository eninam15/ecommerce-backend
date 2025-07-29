<?php

namespace App\Repositories\Interfaces;

use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Dtos\StockMovementData;
use App\Dtos\StockReservationData;
use Illuminate\Support\Collection;

interface StockRepositoryInterface
{
    public function createMovement(StockMovementData $data): StockMovement;
    
    public function createReservation(StockReservationData $data): StockReservation;
    
    public function findReservation(string $id): ?StockReservation;
    
    public function getActiveReservations(string $productId): Collection;
    
    public function getExpiredReservations(?string $productId = null): Collection;
    
    public function getProductMovements(string $productId, int $limit = 50): Collection;
    
    public function getReservedQuantity(string $productId): int;
    
    public function getCommittedQuantity(string $productId): int;
    
    public function releaseReservation(string $reservationId): bool;
    
    public function confirmReservation(string $reservationId): bool;
    
    public function getCartReservations(string $cartId): Collection;
    
    public function getOrderReservations(string $orderId): Collection;
}