<?php
namespace App\Services;

use App\Dtos\ProductData;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class ProductService
{
    protected $productRepository;
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts()
    {
        return $this->productRepository->all();
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
