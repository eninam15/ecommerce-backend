<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repositories\Interfaces\ReviewRepositoryInterface;
use App\Dtos\ReviewData;
use App\Models\Review;


class ReviewRepository implements ReviewRepositoryInterface
{
    public function __construct(protected Review $review) {}

    public function findById(string $id)
    {
        return $this->review->with(['user', 'product'])->find($id);
    }

    public function create(ReviewData $data)
    {
        return $this->review->create([
            'product_id' => $data->productId,
            'user_id' => $data->userId,
            'rating' => $data->rating,
            'comment' => $data->comment,
            'status' => $data->status,
        ]);
    }

    public function update(string $id, ReviewData $data)
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

    public function findByProduct(string $productId)
    {
        return $this->review->where('product_id', $productId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByUser(string $userId)
    {
        return $this->review->where('user_id', $userId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getAverageRating(string $productId)
    {
        return $this->review->where('product_id', $productId)
            ->where('status', true)
            ->avg('rating') ?? 0.0;
    }

    public function delete(string $id)
    {
        return $this->review->findOrFail($id)->delete();
    }
}
