<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Dtos\ProductData;
use App\Dtos\ProductFilterData;
use App\Dtos\ProductCartMarkingData;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;

class ProductService
{
    protected $productRepository;
    protected $cartRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        CartRepositoryInterface $cartRepository
    ) {
        $this->productRepository = $productRepository;
        $this->cartRepository = $cartRepository;
    }

    public function getAllProducts()
    {
        return $this->productRepository->all();
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

    public function createProduct(ProductData $data, string $userId)
    {
        return $this->productRepository->create($data, $userId);
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

}
