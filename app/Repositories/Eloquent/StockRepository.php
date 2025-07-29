<?php

namespace App\Repositories\Eloquent;

use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Repositories\Interfaces\StockRepositoryInterface;
use App\Dtos\StockMovementData;
use App\Dtos\StockReservationData;
use Illuminate\Support\Collection;

class StockRepository implements StockRepositoryInterface
{
    public function __construct(
        protected StockMovement $stockMovement,
        protected StockReservation $stockReservation
    ) {}

    public function createMovement(StockMovementData $data): StockMovement
    {
        return $this->stockMovement->create([
            'product_id' => $data->productId,
            'type' => $data->type->value,
            'reason' => $data->reason->value,
            'quantity' => $data->quantity,
            'reference_id' => $data->referenceId,
            'reference_type' => $data->referenceType,
            'expires_at' => $data->expiresAt,
            'notes' => $data->notes
        ]);
    }

    public function createReservation(StockReservationData $data): StockReservation
    {
        return $this->stockReservation->create([
            'product_id' => $data->productId,
            'user_id' => $data->userId,
            'cart_id' => $data->cartId,
            'order_id' => $data->orderId,
            'quantity' => $data->quantity,
            'status' => 'active',
            'expires_at' => $data->getExpiresAt()
        ]);
    }

    public function findReservation(string $id): ?StockReservation
    {
        return $this->stockReservation->find($id);
    }

    public function getActiveReservations(string $productId): Collection
    {
        return $this->stockReservation
            ->byProduct($productId)
            ->active()
            ->get();
    }

    public function getExpiredReservations(?string $productId = null): Collection
    {
        $query = $this->stockReservation->expired();
        
        if ($productId) {
            $query->byProduct($productId);
        }
        
        return $query->get();
    }

    public function getProductMovements(string $productId, int $limit = 50): Collection
    {
        return $this->stockMovement
            ->byProduct($productId)
            ->with(['creator'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getReservedQuantity(string $productId): int
    {
        return $this->stockReservation
            ->byProduct($productId)
            ->active()
            ->sum('quantity');
    }

    public function getCommittedQuantity(string $productId): int
    {
        return $this->stockReservation
            ->byProduct($productId)
            ->where('status', 'confirmed')
            ->sum('quantity');
    }

    public function releaseReservation(string $reservationId): bool
    {
        $reservation = $this->findReservation($reservationId);
        
        if (!$reservation || $reservation->status !== 'active') {
            return false;
        }

        $reservation->release();
        return true;
    }

    public function confirmReservation(string $reservationId): bool
    {
        $reservation = $this->findReservation($reservationId);
        
        if (!$reservation || $reservation->status !== 'active') {
            return false;
        }

        $reservation->confirm();
        return true;
    }

    public function getCartReservations(string $cartId): Collection
    {
        return $this->stockReservation
            ->where('cart_id', $cartId)
            ->active()
            ->get();
    }

    public function getOrderReservations(string $orderId): Collection
    {
        return $this->stockReservation
            ->where('order_id', $orderId)
            ->active()
            ->get();
    }
}