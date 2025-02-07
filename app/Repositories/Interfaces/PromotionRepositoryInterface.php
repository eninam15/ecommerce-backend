<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;
use App\Dtos\PromotionData;

interface PromotionRepositoryInterface
{
    public function findById(string $id);
    public function create(PromotionData $data);
    public function update(string $id, PromotionData $data);
    public function delete(string $id);
    public function findActive();
    public function findByProduct(string $productId);
    public function findByCriteria(array $criteria);
}
