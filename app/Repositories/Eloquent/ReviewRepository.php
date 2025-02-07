<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(protected Review $model) {}

    public function findById(string $id): ?Review
    {
        return $this->model->with(['user', 'product'])->find($id);
    }

    public function create(ReviewData $data): Review
    {
        return $this->model->create([
            'product_id' => $data->productId,
            'user_id' => $data->userId,
            'rating' => $data->rating,
            'comment' => $data->comment,
            'status' => $data->status,
        ]);
    }

    public function update(string $id, ReviewData $data): ?Review
    {
        $review = $this->findById($id);
        if (!$review) return null;

        $review->update([
            'rating' => $data->rating,
            'comment' => $data->comment,
            'status' => $data->status,
        ]);

        return $review;
    }

    public function findByProduct(string $productId): Collection
    {
        return $this->model->where('product_id', $productId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByUser(string $userId): Collection
    {
        return $this->model->where('user_id', $userId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAverageRating(string $productId): float
    {
        return $this->model->where('product_id', $productId)
            ->where('status', true)
            ->avg('rating') ?? 0.0;
    }

    public function delete(string $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }
}