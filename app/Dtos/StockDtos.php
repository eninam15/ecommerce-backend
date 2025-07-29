<?php

namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;
use App\Enums\StockMovementType;
use App\Enums\StockMovementReason;

class StockMovementData extends DataTransferObject
{
    public function __construct(
        public string $productId,
        public StockMovementType $type,
        public StockMovementReason $reason,
        public int $quantity,
        public ?string $referenceId = null,
        public ?string $referenceType = null,
        public ?\DateTime $expiresAt = null,
        public ?string $notes = null
    ) {}

    public static function createReservation(
        string $productId,
        int $quantity,
        string $referenceId,
        string $referenceType,
        int $minutesToExpire = 30
    ): self {
        return new self(
            productId: $productId,
            type: StockMovementType::RESERVE,
            reason: $referenceType === 'cart' 
                ? StockMovementReason::CART_ADD 
                : StockMovementReason::ORDER_CREATE,
            quantity: $quantity,
            referenceId: $referenceId,
            referenceType: $referenceType,
            expiresAt: now()->addMinutes($minutesToExpire)
        );
    }

    public static function createRelease(
        string $productId,
        int $quantity,
        StockMovementReason $reason,
        ?string $referenceId = null,
        ?string $referenceType = null
    ): self {
        return new self(
            productId: $productId,
            type: StockMovementType::RELEASE,
            reason: $reason,
            quantity: $quantity,
            referenceId: $referenceId,
            referenceType: $referenceType
        );
    }

    public static function createReduction(
        string $productId,
        int $quantity,
        StockMovementReason $reason,
        ?string $referenceId = null,
        ?string $referenceType = null
    ): self {
        return new self(
            productId: $productId,
            type: StockMovementType::REDUCE,
            reason: $reason,
            quantity: $quantity,
            referenceId: $referenceId,
            referenceType: $referenceType
        );
    }
}

class StockReservationData extends DataTransferObject
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public ?string $userId = null,
        public ?string $cartId = null,
        public ?string $orderId = null,
        public int $minutesToExpire = 30
    ) {}

    public function getExpiresAt(): \DateTime
    {
        return now()->addMinutes($this->minutesToExpire);
    }
}

class StockAvailabilityData extends DataTransferObject
{
    public function __construct(
        public string $productId,
        public int $totalStock,
        public int $availableStock,
        public int $reservedStock,
        public int $committedStock,
        public bool $isAvailable,
        public bool $hasLowStock,
        public ?int $requestedQuantity = null,
        public ?bool $canFulfillRequest = null
    ) {}

    public static function fromProduct(Product $product, ?int $requestedQuantity = null): self
    {
        // Esta lógica se implementará en el StockService
        return new self(
            productId: $product->id,
            totalStock: $product->stock,
            availableStock: 0, // Calculado por el servicio
            reservedStock: 0,  // Calculado por el servicio
            committedStock: 0, // Calculado por el servicio
            isAvailable: false, // Calculado por el servicio
            hasLowStock: false, // Calculado por el servicio
            requestedQuantity: $requestedQuantity,
            canFulfillRequest: null // Calculado por el servicio
        );
    }
}