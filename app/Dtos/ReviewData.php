<?php
namespace App\Dtos;
use App\Http\Requests\Review\ReviewRequest;

class ReviewData
{
    public function __construct(
        public readonly string $productId,
        public readonly string $userId,
        public readonly int $rating,
        public readonly ?string $comment,
        public readonly bool $status = true,
    ) {}

    public static function fromRequest(ReviewRequest $request): self
    {
        return new self(
            productId: $request->product_id,
            userId: auth()->id(),
            rating: $request->rating,
            comment: $request->comment,
        );
    }
}
