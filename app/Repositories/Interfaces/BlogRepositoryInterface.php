<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;

interface BlogRepositoryInterface
{
    public function findById(string $id): ?Blog;
    public function create(BlogData $data): Blog;
    public function update(string $id, BlogData $data): ?Blog;
    public function delete(string $id): bool;
    public function findByProduct(string $productId): Collection;
    public function findByCriteria(array $criteria): LengthAwarePaginator;
}