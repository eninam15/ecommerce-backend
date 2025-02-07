<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;

class PromotionService
{
    public function __construct(
        protected PromotionRepositoryInterface $promotionRepository,
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function createPromotion(PromotionData $data): Promotion
    {
        $this->validatePromotionOverlap($data);
        $this->validateProducts($data->products);
        
        return $this->promotionRepository->create($data);
    }

    public function updatePromotion(string $id, PromotionData $data): ?Promotion
    {
        $this->validatePromotionOverlap($data, $id);
        $this->validateProducts($data->products);
        
        return $this->promotionRepository->update($id, $data);
    }

    public function getPromotion(string $id): ?Promotion
    {
        return $this->promotionRepository->findById($id);
    }

    public function deletePromotion(string $id): bool
    {
        return $this->promotionRepository->delete($id);
    }

    public function listPromotions(array $criteria): LengthAwarePaginator
    {
        return $this->promotionRepository->findByCriteria($criteria);
    }

    public function getActivePromotions(): Collection
    {
        return $this->promotionRepository->findActive();
    }

    public function getProductPromotions(string $productId): Collection
    {
        return $this->promotionRepository->findByProduct($productId);
    }

    private function validatePromotionOverlap(PromotionData $data, ?string $excludePromotionId = null): void
    {
        foreach ($data->products as $product) {
            $overlappingPromotions = $this->promotionRepository->findByProduct($product['product_id'])
                ->filter(function ($promotion) use ($data, $excludePromotionId) {
                    return $promotion->id !== $excludePromotionId &&
                           $promotion->ends_at > $data->startsAt && 
                           $promotion->starts_at < $data->endsAt;
                });

            if ($overlappingPromotions->isNotEmpty()) {
                throw new ValidationException("Product {$product['product_id']} already has an active promotion in the selected date range");
            }
        }
    }

    private function validateProducts(array $products): void
    {
        foreach ($products as $product) {
            $productModel = $this->productRepository->findById($product['product_id']);
            if (!$productModel || !$productModel->status) {
                throw new ValidationException("Product {$product['product_id']} is not active or doesn't exist");
            }
        }
    }

    public function calculatePromotionalPrice(string $productId, int $quantity = 1): float
    {
        $activePromotions = $this->promotionRepository->findByProduct($productId);
        
        if ($activePromotions->isEmpty()) {
            return null;
        }

        // Obtener la mejor promoción aplicable
        return $activePromotions->map(function ($promotion) use ($productId, $quantity) {
            return $this->calculatePriceWithPromotion($promotion, $productId, $quantity);
        })->min();
    }

}