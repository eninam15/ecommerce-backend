<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ReviewData;

interface ReviewRepositoryInterface
{
    public function findById(string $id);
    public function create(ReviewData $data);
    public function update(string $id, ReviewData $data);
    public function delete(string $id);
    public function findByProduct(string $productId);
    public function findByUser(string $userId);
    public function getAverageRating(string $productId);
}
