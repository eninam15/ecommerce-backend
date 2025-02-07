<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;

interface ReviewRepositoryInterface
{
    public function findById(string $id): ?Review;
    public function create(ReviewData $data): Review;
    public function update(string $id, ReviewData $data): ?Review;
    public function delete(string $id): bool;
    public function findByProduct(string $productId): Collection;
    public function findByUser(string $userId): Collection;
    public function getAverageRating(string $productId): float;
}
