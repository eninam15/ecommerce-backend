<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;

interface PromotionRepositoryInterface
{
    public function findById(string $id): ?Promotion;
    public function create(PromotionData $data): Promotion;
    public function update(string $id, PromotionData $data): ?Promotion;
    public function delete(string $id): bool;
    public function findActive(): Collection;
    public function findByProduct(string $productId): Collection;
    public function findByCriteria(array $criteria): LengthAwarePaginator;
}
