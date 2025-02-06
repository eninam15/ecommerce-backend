<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Dtos\ProductData;
use App\Dtos\ProductCartMarkingData;
use Illuminate\Support\Str;
use App\Enums\ProductSortEnum;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(protected Product $model) {}

    public function all()
    {
        return $this->model->with('category')->get();
    }

    public function findByCriteria(
        array $criteria,
        ?ProductSortEnum $sortBy,
        ?ProductCartMarkingData $cartMarking = null,
        int $perPage = 100
    ): LengthAwarePaginator {

        $query = $this->model->query()
            ->when($criteria['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(name) LIKE ?', ["%".strtolower($search)."%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%".strtolower($search)."%"]);
                });
            })
            ->when($criteria['category_ids'] ?? null, function ($query, $categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->when($criteria['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($criteria['min_price'] ?? null, function ($query, $price) {
                $query->where('price', '>=', $price);
            })
            ->when($criteria['max_price'] ?? null, function ($query, $price) {
                $query->where('price', '<=', $price);
            })
            ->when($cartMarking?->userId && $cartMarking?->cartItemProductIds,
                function ($query) use ($cartMarking) {
                    $query->addSelect([
                        '*',
                        'in_cart' => function ($subQuery) use ($cartMarking) {
                            $subQuery->select(
                                DB::raw('EXISTS (
                                    SELECT 1
                                    FROM cart_items
                                    JOIN carts ON cart_items.cart_id = carts.id
                                    WHERE cart_items.product_id = products.id
                                    AND carts.user_id = ?
                                ) as in_cart')
                            )->addBinding($cartMarking->userId);
                        },
                        'cart_quantity' => function ($subQuery) use ($cartMarking) {
                            $subQuery->select(
                                DB::raw('(
                                    SELECT COALESCE(SUM(cart_items.quantity), 0)
                                    FROM cart_items
                                    JOIN carts ON cart_items.cart_id = carts.id
                                    WHERE cart_items.product_id = products.id
                                    AND carts.user_id = ?
                                ) as cart_quantity')
                            )->addBinding($cartMarking->userId);
                        }
                    ]);
                }
            );
            /*->when($criteria['on_promotion'] ?? false, function ($query) {
                $query->whereHas('promotions', function ($q) {
                    $q->active(); // Assumes a scope in Promotion model
                });
            });*/

        // Sorting logic
        match ($sortBy) {
            ProductSortEnum::MOST_SOLD => $query->orderByDesc('total_sales'),
            ProductSortEnum::LATEST => $query->orderByDesc('created_at'),
            ProductSortEnum::PRICE_ASC => $query->orderBy('price'),
            ProductSortEnum::PRICE_DESC => $query->orderByDesc('price'),
            ProductSortEnum::MOST_RATED => $query->orderByDesc('average_rating'),
            /*ProductSortEnum::PROMOTION => $query->whereHas('promotions', function ($q) {
                $q->active()->orderByDesc('discount_percentage');
            }),*/
            default => $query
        };

        return $query
            ->with(['category', 'images'])
            ->paginate($perPage);
    }

    public function create(ProductData $data, string $userId)
    {
        return DB::transaction(function () use ($data, $userId) {
            $product = $this->model->create([
                'name' => $data->name,
                'slug' => Str::slug($data->name),
                'description' => $data->description,
                'price' => $data->price,
                'stock' => $data->stock,
                'category_id' => $data->category_id,
                'status' => $data->status,
                'attributes' => $data->attributes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Manejar las imágenes
            if (!empty($data->images)) {
                foreach ($data->images as $index => $image) {
                    $filename = uniqid() . '_' . $image->getClientOriginalName();
                    $path = $image->storeAs('products', $filename, 'public');

                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => $data->primary_image === $index,
                        'order' => $index,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }

            return $product;
        });
    }

    public function update(string $id, ProductData $data)
    {
        $product = $this->model->findOrFail($id);
        $product->update([
            'name' => $data->name,
            'slug' => Str::slug($data->name),
            'description' => $data->description,
            'price' => $data->price,
            'stock' => $data->stock,
            'category_id' => $data->category_id,
            'sku' => $data->sku,
            'metadata' => $data->metadata,
            'is_active' => $data->is_active
        ]);

        if (!empty($data->images)) {
            $product->clearMediaCollection('images');
            foreach ($data->images as $image) {
                $product->addMedia($image)
                    ->toMediaCollection('images');
            }
        }

        return $product->load('category');
    }

    public function delete(string $id)
    {
        $product = $this->model->findOrFail($id);
        return $product->delete();
    }

    public function find(string $id)
    {
        return $this->model->with('category')->findOrFail($id);
    }

    public function findByCategory(string $categoryId)
    {
        return $this->model->where('category_id', $categoryId)->with('category')->get();
    }

    public function reserveStock(string $productId, int $quantity)
    {
        $product = $this->model->findOrFail($productId);

        if ($product->stock < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        $product->stock -= $quantity;
        $product->save();

        return $product;
    }

}
