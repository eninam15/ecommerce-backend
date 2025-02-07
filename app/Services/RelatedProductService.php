<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;

class RelatedProductService
{
    public function __construct(
        protected RelatedProductRepositoryInterface $relatedProductRepository,
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function createRelations(RelatedProductData $data): Collection
    {
        $this->validateProducts(array_merge([$data->productId], $data->relatedProductIds));
        $this->validateRelationships($data);
        
        return $this->relatedProductRepository->create($data);
    }

    public function removeRelation(string $productId, string $relatedProductId): bool
    {
        return $this->relatedProductRepository->delete($productId, $relatedProductId);
    }

    public function getRelatedProducts(string $productId, int $limit = 5): Collection
    {
        return $this->relatedProductRepository->findSimilarProducts($productId, $limit);
    }

    public function getAllRelations(string $productId): Collection
    {
        return $this->relatedProductRepository->findByProduct($productId);
    }

    private function validateProducts(array $productIds): void
    {
        foreach ($productIds as $productId) {
            $product = $this->productRepository->findById($productId);
            if (!$product || !$product->status) {
                throw new ValidationException("Product {$productId} is not active or doesn't exist");
            }
        }
    }

    private function validateRelationships(RelatedProductData $data): void
    {
        if (in_array($data->productId, $data->relatedProductIds)) {
            throw new ValidationException("A product cannot be related to itself");
        }

        $existingRelations = $this->relatedProductRepository->findByProduct($data->productId)
            ->pluck('related_product_id')
            ->intersect($data->relatedProductIds);

        if ($existingRelations->isNotEmpty()) {
            throw new ValidationException("Some products are already related");
        }
    }

    private function calculatePriceWithPromotion(Promotion $promotion, string $productId, int $quantity): float
    {
        $product = $promotion->products->firstWhere('product_id', $productId);
        $originalPrice = $product->price * $quantity;

        switch ($promotion->type) {
            case PromotionType::PERCENTAGE_DISCOUNT:
                return $this->calculatePercentageDiscount($originalPrice, $promotion->discount_value);

            case PromotionType::FIXED_AMOUNT_DISCOUNT:
                return $this->calculateFixedDiscount($originalPrice, $promotion->discount_value);

            case PromotionType::BUY_X_GET_Y_FREE:
                return $this->calculateBuyXGetYFree($product->price, $quantity, $promotion->min_quantity, $promotion->free_quantity);

            case PromotionType::BUNDLE_PRICE:
                return $this->calculateBundlePrice($product->price, $quantity, $promotion->min_quantity, $promotion->bundle_price);

            default:
                throw new InvalidPromotionTypeException("Invalid promotion type: {$promotion->type}");
        }
    }

    private function calculatePercentageDiscount(float $originalPrice, float $discountPercentage): float
    {
        return $originalPrice * (1 - ($discountPercentage / 100));
    }

    private function calculateFixedDiscount(float $originalPrice, float $discountAmount): float
    {
        return max(0, $originalPrice - $discountAmount);
    }

    private function calculateBuyXGetYFree(
        float $unitPrice, 
        int $quantity, 
        int $minQuantity, 
        int $freeQuantity
    ): float {
        if ($quantity < $minQuantity) {
            return $unitPrice * $quantity;
        }

        $sets = floor($quantity / ($minQuantity + $freeQuantity));
        $remainder = $quantity % ($minQuantity + $freeQuantity);

        $discountedQuantity = ($sets * $minQuantity) + min($remainder, $minQuantity);
        return $unitPrice * $discountedQuantity;
    }

    private function calculateBundlePrice(
        float $unitPrice, 
        int $quantity, 
        int $bundleQuantity, 
        float $bundlePrice
    ): float {
        if ($quantity < $bundleQuantity) {
            return $unitPrice * $quantity;
        }

        $bundles = floor($quantity / $bundleQuantity);
        $remainder = $quantity % $bundleQuantity;

        return ($bundles * $bundlePrice) + ($remainder * $unitPrice);
    }
}