<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Requests\Product\BulkProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\ProductService;
use App\Models\ProductImage;
use App\Models\Product;
use App\Dtos\ProductData;
use App\Dtos\ProductFilterData;
use App\Enums\ProductSortEnum;
use Illuminate\Support\Facades\Log;


class ProductController extends Controller
{
    protected $productService;

    public function __construct(
        ProductService $productService
    ) {
        $this->productService = $productService;
    }

    public function index(Request $request)
    {
        $filters = new ProductFilterData(
            search: $request->search,
            categoryIds: $request->category_ids ? explode(',', $request->category_ids) : null,
            status: $request->boolean('status'),
            minPrice: $request->min_price,
            maxPrice: $request->max_price,
            sortBy: $request->sort ? ProductSortEnum::tryFrom($request->sort) : null,
            onPromotion: $request->boolean('on_promotion'),
            perPage: $request->per_page ?? 100
        );

        $products = $this->productService->listProducts($filters, auth()->id());

        return ProductResource::collection($products);
    }


    public function store(ProductRequest $request)
    {
        //dd("Aqui el request ", $request);
        $product = $this->productService->createProduct(
            ProductData::fromRequest($request), '9e0a4d30-c294-4141-8c8f-dd77bd5a2466'
        );

        return new ProductResource($product->load(['category', 'images']));
    }

    public function bulkStore(BulkProductRequest $request)
    {
        try {
            $products = $this->productService->createBulkProducts(
                $request->validated()['products'],
                '9e0a4d30-c294-4141-8c8f-dd77bd5a2466'
            );

            $products->each(function ($product) {
                $product->load(['category']);
            });

            return ProductResource::collection($products);
        } catch (\Exception $e) {
            Log::error('Error en bulkStore', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function show(Product $product)
    {
        return new ProductResource(
            $product->load(['category', 'images', 'creator', 'updater'])
        );
    }

    public function update(ProductRequest $request, Product $product)
    {
        try {
            DB::beginTransaction();

            $product->update($request->except('images', 'primary_image'));

            if ($request->hasFile('images')) {
                // Delete old images from storage
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->path);
                }

                // Delete old images from database
                $product->images()->delete();

                // Store new images
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => $request->primary_image === $index,
                        'order' => $index
                    ]);
                }
            }

            DB::commit();

            return new ProductResource($product->load(['category', 'images', 'creator', 'updater']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();

            // Delete images from storage
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->path);
            }

            $product->delete();

            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /*public function show(string $id)
    {
        $product = $this->productService->findProduct($id);
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, string $id)
    {
        $product = $this->productService->updateProduct(
            $id,
            ProductData::fromRequest($request)
        );

        return new ProductResource($product);
    }

    public function destroy(string $id)
    {
        $this->productService->deleteProduct($id);
        return response()->noContent();
    }*/
}
