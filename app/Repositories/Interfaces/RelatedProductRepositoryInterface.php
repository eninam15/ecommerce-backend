<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;

interface RelatedProductRepositoryInterface
{
    public function create(RelatedProductData $data): Collection;
    public function delete(string $productId, string $relatedProductId): bool;
    public function findByProduct(string $productId): Collection;
    public function findSimilarProducts(string $productId, int $limit = 5): Collection;
}