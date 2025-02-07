<?php
namespace App\Services;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Dtos\ReviewData;


class ReviewService
{
    public function __construct(
        protected ReviewRepositoryInterface $reviewRepository,
        protected ProductRepositoryInterface $productRepository
    ) {}

    public function createReview(ReviewData $data)
    {
        $this->validateProduct($data->productId);
        $this->validateUserReview($data->productId, $data->userId);

        return $this->reviewRepository->create($data);
    }

    public function updateReview(string $id, ReviewData $data): ?Review
    {
        $review = $this->reviewRepository->findById($id);

        if (!$review || $review->user_id !== $data->userId) {
            throw new AuthorizationException('No estás autorizado para actualizar esta reseña');
        }

        return $this->reviewRepository->update($id, $data);
    }

    public function deleteReview(string $id, string $userId): bool
    {
        $review = $this->reviewRepository->findById($id);

        if (!$review || $review->user_id !== $userId) {
            throw new AuthorizationException('No estás autorizado para eliminar esta reseña');
        }

        return $this->reviewRepository->delete($id);
    }

    public function getProductReviews(string $productId)
    {
        return $this->reviewRepository->findByProduct($productId);
    }

    public function getUserReviews(string $userId)
    {
        return $this->reviewRepository->findByUser($userId);
    }

    public function getAverageRating(string $productId): float
    {
        return $this->reviewRepository->getAverageRating($productId);
    }

    private function validateProduct(string $productId): void
    {
        $product = $this->productRepository->findById($productId);
        if (!$product || !$product->status) {
            throw new ValidationException('El producto no está disponible para reseñas');
        }
    }

    private function validateUserReview(string $productId, string $userId): void
    {
        $existingReview = $this->reviewRepository->findByProduct($productId)
            ->where('user_id', $userId)
            ->first();

        if ($existingReview) {
            throw new ValidationException('Ya has realizado una reseña para este producto');
        }
    }
}
