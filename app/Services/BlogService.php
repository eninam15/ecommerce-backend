<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Interfaces\BlogRepositoryInterface;
use App\Dtos\BlogData;



class BlogService
{
    public function __construct(
        protected BlogRepositoryInterface $blogRepository
    ) {}

    public function createBlog(BlogData $data)
    {
        return $this->blogRepository->create($data);
    }

    public function updateBlog(string $id, BlogData $data): ?Blog
    {
        return $this->blogRepository->update($id, $data);
    }

    public function getProductBlogs(string $productId): Collection
    {
        return $this->blogRepository->findByProduct($productId);
    }

    public function listBlogs(array $criteria): LengthAwarePaginator
    {
        return $this->blogRepository->findByCriteria($criteria);
    }
}
