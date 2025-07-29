<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Dtos\StockMovementData;
use App\Dtos\StockReservationData;
use App\Dtos\StockAvailabilityData;
use App\Enums\StockMovementType;
use App\Enums\StockMovementReason;
use App\Enums\StockReservationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StockService
{
    public function __construct(
        protected Product $product,
        protected StockMovement $stockMovement,
        protected StockReservation $stockReservation
    ) {}

    /**
     * Verificar disponibilidad de stock para un producto
     */
    public function checkAvailability(string $productId, int $requestedQuantity = 1): StockAvailabilityData
    {
        $product = $this->product->findOrFail($productId);
        
        // Limpiar reservas expiradas antes de verificar
        $this->releaseExpiredReservations($productId);
        
        $reservedStock = $this->getReservedStock($productId);
        $committedStock = $this->getCommittedStock($productId);
        $availableStock = $product->stock - $reservedStock - $committedStock;
        
        $isAvailable = $availableStock > 0;
        $hasLowStock = $product->stock <= $product->min_stock;
        $canFulfillRequest = $availableStock >= $requestedQuantity;

        return new StockAvailabilityData(
            productId: $productId,
            totalStock: $product->stock,
            availableStock: max(0, $availableStock),
            reservedStock: $reservedStock,
            committedStock: $committedStock,
            isAvailable: $isAvailable,
            hasLowStock: $hasLowStock,
            requestedQuantity: $requestedQuantity,
            canFulfillRequest: $canFulfillRequest
        );
    }

    /**
     * Reservar stock temporalmente (para carrito)
     */
    public function reserveStock(StockReservationData $data): StockReservation
    {
        return DB::transaction(function () use ($data) {
            $availability = $this->checkAvailability($data->productId, $data->quantity);
            
            if (!$availability->canFulfillRequest) {
                throw new \Exception("Stock insuficiente. Disponible: {$availability->availableStock}, Solicitado: {$data->quantity}");
            }

            // Crear reserva
            $reservation = $this->stockReservation->create([
                'product_id' => $data->productId,
                'user_id' => $data->userId,
                'cart_id' => $data->cartId,
                'order_id' => $data->orderId,
                'quantity' => $data->quantity,
                'status' => StockReservationStatus::ACTIVE->value,
                'expires_at' => $data->getExpiresAt()
            ]);

            // Registrar movimiento
            $this->recordMovement(StockMovementData::createReservation(
                $data->productId,
                $data->quantity,
                $data->cartId ?? $data->orderId,
                $data->cartId ? 'cart' : 'order'
            ));

            Log::info("Stock reservado", [
                'product_id' => $data->productId,
                'quantity' => $data->quantity,
                'reservation_id' => $reservation->id
            ]);

            return $reservation;
        });
    }

    /**
     * Liberar reserva de stock
     */
    public function releaseReservation(string $reservationId, StockMovementReason $reason): bool
    {
        return DB::transaction(function () use ($reservationId, $reason) {
            $reservation = $this->stockReservation->findOrFail($reservationId);
            
            if ($reservation->status !== StockReservationStatus::ACTIVE->value) {
                return false;
            }

            $reservation->release();

            $this->recordMovement(StockMovementData::createRelease(
                $reservation->product_id,
                $reservation->quantity,
                $reason,
                $reservation->cart_id ?? $reservation->order_id,
                $reservation->cart_id ? 'cart' : 'order'
            ));

            Log::info("Stock liberado", [
                'reservation_id' => $reservationId,
                'product_id' => $reservation->product_id,
                'quantity' => $reservation->quantity
            ]);

            return true;
        });
    }

    /**
     * Confirmar reserva (orden confirmada)
     */
    public function confirmReservation(string $reservationId): bool
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = $this->stockReservation->findOrFail($reservationId);
            
            if ($reservation->status !== StockReservationStatus::ACTIVE->value) {
                throw new \Exception("La reserva no está activa");
            }

            $reservation->confirm();

            $this->recordMovement(StockMovementData::createReduction(
                $reservation->product_id,
                $reservation->quantity,
                StockMovementReason::PAYMENT_CONFIRM,
                $reservation->order_id,
                'order'
            ));

            // Reducir stock real del producto
            $product = $this->product->findOrFail($reservation->product_id);
            $product->decrement('stock', $reservation->quantity);

            Log::info("Reserva confirmada y stock reducido", [
                'reservation_id' => $reservationId,
                'product_id' => $reservation->product_id,
                'quantity' => $reservation->quantity
            ]);

            return true;
        });
    }

    /**
     * Liberar todas las reservas expiradas
     */
    public function releaseExpiredReservations(?string $productId = null): int
    {
        $query = $this->stockReservation->expired();
        
        if ($productId) {
            $query->byProduct($productId);
        }

        $expiredReservations = $query->get();
        $releasedCount = 0;

        foreach ($expiredReservations as $reservation) {
            if ($this->releaseReservation($reservation->id, StockMovementReason::EXPIRED_RESERVATION)) {
                $releasedCount++;
            }
        }

        return $releasedCount;
    }

    /**
     * Liberar reservas por carrito
     */
    public function releaseCartReservations(string $cartId): int
    {
        $reservations = $this->stockReservation
            ->where('cart_id', $cartId)
            ->where('status', StockReservationStatus::ACTIVE->value)
            ->get();

        $releasedCount = 0;
        foreach ($reservations as $reservation) {
            if ($this->releaseReservation($reservation->id, StockMovementReason::CART_REMOVE)) {
                $releasedCount++;
            }
        }

        return $releasedCount;
    }

    /**
     * Ajuste manual de stock
     */
    public function adjustStock(string $productId, int $newStock, string $reason = null): bool
    {
        return DB::transaction(function () use ($productId, $newStock, $reason) {
            $product = $this->product->findOrFail($productId);
            $oldStock = $product->stock;
            $difference = $newStock - $oldStock;

            if ($difference === 0) {
                return true;
            }

            $product->update(['stock' => $newStock]);

            $this->recordMovement(new StockMovementData(
                productId: $productId,
                type: $difference > 0 ? StockMovementType::RESTOCK : StockMovementType::ADJUSTMENT,
                reason: StockMovementReason::MANUAL_ADJUSTMENT,
                quantity: abs($difference),
                notes: $reason
            ));

            Log::info("Ajuste manual de stock", [
                'product_id' => $productId,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'difference' => $difference
            ]);

            return true;
        });
    }

    /**
     * Obtener stock reservado para un producto
     */
    protected function getReservedStock(string $productId): int
    {
        return $this->stockReservation
            ->byProduct($productId)
            ->active()
            ->sum('quantity');
    }

    /**
     * Obtener stock comprometido (órdenes confirmadas pero no entregadas)
     */
    protected function getCommittedStock(string $productId): int
    {
        return $this->stockReservation
            ->byProduct($productId)
            ->where('status', StockReservationStatus::CONFIRMED->value)
            ->sum('quantity');
    }

    /**
     * Registrar movimiento de stock
     */
    protected function recordMovement(StockMovementData $data): StockMovement
    {
        $product = $this->product->findOrFail($data->productId);
        
        return $this->stockMovement->create([
            'product_id' => $data->productId,
            'type' => $data->type->value,
            'reason' => $data->reason->value,
            'quantity' => $data->quantity,
            'stock_before' => $product->stock,
            'stock_after' => $product->stock, // Se actualizará si es necesario
            'reference_id' => $data->referenceId,
            'reference_type' => $data->referenceType,
            'expires_at' => $data->expiresAt,
            'notes' => $data->notes
        ]);
    }

    /**
     * Obtener historial de movimientos de un producto
     */
    public function getStockHistory(string $productId, int $limit = 50): \Illuminate\Support\Collection
    {
        return $this->stockMovement
            ->byProduct($productId)
            ->with(['creator'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockProducts(): \Illuminate\Support\Collection
    {
        return $this->product
            ->whereRaw('stock <= min_stock')
            ->where('status', true)
            ->with(['category'])
            ->get();
    }

    /**
     * Obtener reporte de stock para un producto
     */
    public function getStockReport(string $productId): array
    {
        $product = $this->product->with(['category'])->findOrFail($productId);
        $availability = $this->checkAvailability($productId);
        $recentMovements = $this->getStockHistory($productId, 10);
        
        $activeReservations = $this->stockReservation
            ->byProduct($productId)
            ->active()
            ->with(['user', 'cart', 'order'])
            ->get();

        return [
            'product' => $product,
            'availability' => $availability,
            'recent_movements' => $recentMovements,
            'active_reservations' => $activeReservations,
            'stock_turnover' => $this->calculateStockTurnover($productId),
            'reorder_point' => $this->calculateReorderPoint($productId)
        ];
    }

    /**
     * Calcular rotación de stock (simplificado)
     */
    protected function calculateStockTurnover(string $productId): float
    {
        $sales = $this->stockMovement
            ->byProduct($productId)
            ->byType(StockMovementType::REDUCE->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity');

        $product = $this->product->find($productId);
        
        return $product && $product->stock > 0 ? ($sales / $product->stock) : 0;
    }

    /**
     * Calcular punto de reorden
     */
    protected function calculateReorderPoint(string $productId): int
    {
        $dailyAverage = $this->stockMovement
            ->byProduct($productId)
            ->byType(StockMovementType::REDUCE->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('quantity') / 30;

        return max(ceil($dailyAverage * 7), 5); // 7 días de lead time mínimo
    }
}