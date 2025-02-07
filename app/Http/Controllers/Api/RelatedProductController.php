<?php
namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Http\Requests\Product\RelatedProductRequest;
use App\Http\Resources\ProductResource;
use App\Dtos\RelatedProductData;


class RelatedProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function store(RelatedProductRequest $request)
    {

        $relatedProducts = $this->productService->createProductRelations(
            RelatedProductData::fromRequest($request)
        );

        return ProductResource::collection($relatedProducts);
    }

    public function destroy(string $productId, string $relatedProductId)
    {
        $this->productService->removeRelatedProduct($productId, $relatedProductId);

        return response()->noContent();
    }

    public function getRelatedProducts(string $productId)
    {
        $relatedProducts = $this->productService->findRelatedProducts($productId);

        return ProductResource::collection($relatedProducts);
    }
}
