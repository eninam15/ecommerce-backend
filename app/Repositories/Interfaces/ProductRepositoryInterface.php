<?php

namespace App\Repositories\Interfaces;
use App\DTOs\ProductData;

interface ProductRepositoryInterface
{
    public function all();
    public function create(ProductData $data);
    public function update(string $id, ProductData $data);
    public function delete(string $id);
    public function find(string $id);
    public function findByCategory(string $categoryId);
    public function updateStock(string $id, int $quantity);
}
