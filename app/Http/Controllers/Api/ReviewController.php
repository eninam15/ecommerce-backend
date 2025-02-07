<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviewService
    ) {}

    public function store(ReviewRequest $request)
    {
        $review = $this->reviewService->createReview(
            ReviewData::fromRequest($request)
        );

        return new ReviewResource($review);
    }

    public function update(ReviewRequest $request, string $id)
    {
        $review = $this->reviewService->updateReview(
            $id,
            ReviewData::fromRequest($request)
        );

        return new ReviewResource($review);
    }

    public function productReviews(string $productId)
    {
        $reviews = $this->reviewService->getProductReviews($productId);
        
        return ReviewResource::collection($reviews);
    }
}
