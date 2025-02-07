<?php
namespace App\Repositories\Interfaces;

use App\Dtos\BlogData;

interface BlogRepositoryInterface
{
    public function findById(string $id);
    public function create(BlogData $data);
    public function update(string $id, BlogData $data);
    public function delete(string $id);
    public function findByProduct(string $productId);
    public function findByCriteria(array $criteria);
}
