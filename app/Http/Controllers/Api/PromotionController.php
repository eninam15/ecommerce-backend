<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PromotionController extends Controller
{
    // ... (código anterior) ...

    public function __construct(
        protected PromotionService $promotionService
    ) {}

    public function index(Request $request)
    {
        $promotions = $this->promotionService->listPromotions([
            'search' => $request->search,
            'status' => $request->status,
            'active' => $request->boolean('active'),
            'per_page' => $request->per_page
        ]);

        return PromotionResource::collection($promotions);
    }

    public function store(PromotionRequest $request)
    {
        $promotion = $this->promotionService->createPromotion(
            PromotionData::fromRequest($request)
        );

        return new PromotionResource($promotion);
    }

    public function show(string $id)
    {
        $promotion = $this->promotionService->getPromotion($id);
        
        return new PromotionResource($promotion);
    }

    public function update(PromotionRequest $request, string $id)
    {
        $promotion = $this->promotionService->updatePromotion(
            $id,
            PromotionData::fromRequest($request)
        );

        return new PromotionResource($promotion);
    }

    public function destroy(string $id)
    {
        $this->promotionService->deletePromotion($id);
        
        return response()->noContent();
    }

    public function getActivePromotions()
    {
        $promotions = $this->promotionService->getActivePromotions();
        
        return PromotionResource::collection($promotions);
    }

    public function getProductPromotions(string $productId)
    {
        $promotions = $this->promotionService->getProductPromotions($productId);
        
        return PromotionResource::collection($promotions);
    }
}