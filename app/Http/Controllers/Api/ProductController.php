<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\ProductService;
use App\Models\ProductImage;
use App\Models\Product;
use App\Dtos\ProductData;

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
        $products = Product::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($request->status !== null, function ($query) use ($request) {
                $query->where('status', $request->boolean('status'));
            })
            ->when($request->min_price, function ($query, $price) {
                $query->where('price', '>=', $price);
            })
            ->when($request->max_price, function ($query, $price) {
                $query->where('price', '<=', $price);
            })
            ->with(['category', 'images', 'creator', 'updater'])
            ->paginate($request->per_page ?? 100);

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        $product = $this->productService->createProduct(
            ProductData::fromRequest($request), '9e0a4d30-c294-4141-8c8f-dd77bd5a2466'
        );

        return new ProductResource($product->load(['category', 'images', 'created_by', 'updated_by']));
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
