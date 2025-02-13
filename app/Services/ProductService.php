<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Dtos\ProductData;
use App\Dtos\ProductFilterData;
use App\Dtos\ProductCartMarkingData;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\RelatedProductRepositoryInterface;
use App\Repositories\Interfaces\PromotionRepositoryInterface;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Dtos\RelatedProductData;




class ProductService
{
    protected $productRepository;
    protected $cartRepository;
    protected $relatedProductRepository;
    protected $promotionRepository;
    protected $reviewRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        CartRepositoryInterface $cartRepository,

        RelatedProductRepositoryInterface $relatedProductRepository,
        PromotionRepositoryInterface $promotionRepository,
        ReviewRepositoryInterface $reviewRepository
    ) {
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;

        $this->relatedProductRepository = $relatedProductRepository;
        $this->promotionRepository = $promotionRepository;
        $this->reviewRepository = $reviewRepository;
    }

    public function listProducts(ProductFilterData $filters, ?string $userId = null): LengthAwarePaginator {

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

    public function reserveStock(string $id, int $quantity)
    {
        return $this->productRepository->reserveStock($id, $quantity);
    }

    public function getProductDetail(string $id): ?Product
    {
        return $this->productRepository->findByIdWithRelations($id, [
            'category',
            'images',
            'relatedProducts',
            'blogs' => function ($query) {
                $query->where('status', true);
            },
            'reviews' => function ($query) {
                $query->where('status', true)
                    ->orderBy('created_at', 'desc');
            },
            'activePromotions'
        ]);
    }

    public function createProductRelations(RelatedProductData $data)
    {
        return $this->relatedProductRepository->create($data);
    }

    public function removeRelatedProduct(string $productId, string $relatedProductId): bool
    {
        return $this->relatedProductRepository->delete($productId, $relatedProductId);
    }

    public function findRelatedProducts(string $productId, int $limit = 5)
    {
        return $this->relatedProductRepository->findSimilarProducts($productId, $limit);
    }

    public function getProductPriceWithPromotions(string $productId, int $quantity = 1): array
    {
        $product = $this->productRepository->findById($productId);
        $regularPrice = $product->price * $quantity;

        $activePromotions = $this->promotionRepository->findByProduct($productId);
        if ($activePromotions->isEmpty()) {
            return [
                'regular_price' => $regularPrice,
                'final_price' => $regularPrice,
                'discount' => 0,
                'applied_promotion' => null
            ];
        }

        $bestPromotion = null;
        $lowestPrice = $regularPrice;

        foreach ($activePromotions as $promotion) {
            $priceWithPromotion = $this->calculatePromotionalPrice($product, $promotion, $quantity);
            if ($priceWithPromotion < $lowestPrice) {
                $lowestPrice = $priceWithPromotion;
                $bestPromotion = $promotion;
            }
        }

        return [
            'regular_price' => $regularPrice,
            'final_price' => $lowestPrice,
            'discount' => $regularPrice - $lowestPrice,
            'applied_promotion' => $bestPromotion ? new PromotionResource($bestPromotion) : null
        ];
    }

    private function calculatePromotionalPrice(Product $product, Promotion $promotion, int $quantity): float
    {
        $pivotData = $promotion->products->find($product->id)->pivot;
        $discountValue = $pivotData->discount_value ?? $promotion->discount_value;

        if ($quantity < ($pivotData->quantity_required ?? 1)) {
            return $product->price * $quantity;
        }

        $discountAmount = $promotion->discount_type === DiscountTypeEnum::PERCENTAGE->value
            ? ($product->price * $discountValue / 100)
            : $discountValue;

        return ($product->price - $discountAmount) * $quantity;
    }

}
