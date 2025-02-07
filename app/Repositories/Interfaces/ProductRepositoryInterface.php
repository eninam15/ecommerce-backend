<?php

namespace App\Repositories\Interfaces;
use App\Dtos\ProductData;

interface ProductRepositoryInterface
{
    public function all();
    public function create(ProductData $data, string $userId);
    public function update(string $id, ProductData $data);
    public function delete(string $id);
    public function findById(string $id);
    public function findByCategory(string $categoryId);
    public function reserveStock(string $id, int $quantity);
}
