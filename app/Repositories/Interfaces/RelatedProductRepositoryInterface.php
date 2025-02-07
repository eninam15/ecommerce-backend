<?php
namespace App\Repositories\Interfaces;

use App\Dtos\RelatedProductData;

interface RelatedProductRepositoryInterface
{
    public function create(RelatedProductData $data);
    public function delete(string $productId, string $relatedProductId);
    public function findByProduct(string $productId);
    public function findSimilarProducts(string $productId, int $limit = 5);
    public function calculateRelationshipScore(string $productId, string $relatedId);

}
