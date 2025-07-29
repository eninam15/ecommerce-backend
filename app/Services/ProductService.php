<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Dtos\ProductData;
use App\Dtos\ProductFilterData;
use App\Dtos\ProductCartMarkingData;
use App\Dtos\StockReservationData;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\RelatedProductRepositoryInterface;
use App\Repositories\Interfaces\PromotionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Services\StockService;
use App\Dtos\RelatedProductData;
use App\Models\Product;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected CartRepositoryInterface $cartRepository,
        protected RelatedProductRepositoryInterface $relatedProductRepository,
        protected PromotionRepositoryInterface $promotionRepository,
        protected ReviewRepositoryInterface $reviewRepository,
        protected StockService $stockService
    ) {}

    public function listProducts(ProductFilterData $filters, ?string $userId = null): LengthAwarePaginator 
    {
        $cartMarking = $userId
            ? new ProductCartMarkingData(
                userId: $userId,
                cartItemProductIds: $this->cartRepository->getCartProductIds($userId)->toArray()
            ) : null;

        $criteria = [
            'search' => $filters->search,
            'category_ids' => $filters->categoryIds,
            'status' => $filters->status,
            'min_price' => $filters->minPrice,
            'max_price' => $filters->maxPrice,
            'on_promotion' => $filters->onPromotion,
        ];

        return $this->productRepository->findByCriteria(
            $criteria,
            $filters->sortBy,
            $cartMarking,
            $filters->perPage
        );
    }

    public function getAllProducts()
    {
        return $this->productRepository->all();
    }

    public function createProduct(ProductData $data, string $userId)
    {
        return $this->productRepository->create($data, $userId);
    }

    public function createBulkProducts(array $productsData, string $userId)
    {
        return $this->productRepository->createBulk($productsData, $userId);
    }

    public function updateProduct(string $id, ProductData $data)
    {
        return $this->productRepository->update($id, $data);
    }

    public function deleteProduct(string $id)
    {
        return $this->productRepository->delete($id);
    }

    public function findProduct(string $id)
    {
        return $this->productRepository->find($id);
    }

    // ===== MÉTODOS DE STOCK =====

    /**
     * Reservar stock para carrito
     */
    public function reserveStock(string $productId, int $quantity, ?string $userId = null, ?string $cartId = null): Product
    {
        $product = $this->productRepository->find($productId);
        
        if (!$product) {
            throw new \Exception("Producto no encontrado");
        }

        if (!$product->status) {
            throw new \Exception("Producto no disponible");
        }

        // Verificar disponibilidad
        $availability = $this->stockService->checkAvailability($productId, $quantity);
        
        if (!$availability->canFulfillRequest) {
            throw new \Exception(
                "Stock insuficiente. Disponible: {$availability->availableStock}, Solicitado: {$quantity}"
            );
        }

        // Crear reserva
        $reservationData = new StockReservationData(
            productId: $productId,
            quantity: $quantity,
            userId: $userId,
            cartId: $cartId,
            minutesToExpire: 30 // 30 minutos para reservas de carrito
        );

        $this->stockService->reserveStock($reservationData);

        return $product;
    }

    /**
     * Verificar disponibilidad de stock
     */
    public function checkStockAvailability(string $productId, int $quantity = 1)
    {
        return $this->stockService->checkAvailability($productId, $quantity);
    }

    /**
     * Liberar stock reservado
     */
    public function releaseStock(string $reservationId, string $reason = 'manual')
    {
        $reasonEnum = match($reason) {
            'cart_remove' => \App\Enums\StockMovementReason::CART_REMOVE,
            'order_cancel' => \App\Enums\StockMovementReason::ORDER_CANCEL,
            default => \App\Enums\StockMovementReason::MANUAL_ADJUSTMENT
        };

        return $this->stockService->releaseReservation($reservationId, $reasonEnum);
    }

    /**
     * Ajustar stock manualmente
     */
    public function adjustStock(string $productId, int $newStock, string $reason = null): bool
    {
        return $this->stockService->adjustStock($productId, $newStock, $reason);
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockProducts()
    {
        return $this->stockService->getLowStockProducts();
    }

    /**
     * Obtener reporte de stock de un producto
     */
    public function getStockReport(string $productId): array
    {
        return $this->stockService->getStockReport($productId);
    }

    // ===== MÉTODOS EXISTENTES =====

    public function createProductRelations(RelatedProductData $data)
    {
        return $this->relatedProductRepository->create($data);
    }

    public function removeRelatedProduct(string $productId, string $relatedProductId)
    {
        return $this->relatedProductRepository->delete($productId, $relatedProductId);
    }

    public function findRelatedProducts(string $productId)
    {
        return $this->relatedProductRepository->findByProduct($productId);
    }
}