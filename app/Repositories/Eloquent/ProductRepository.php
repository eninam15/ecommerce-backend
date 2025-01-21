<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\DTOs\ProductData;
use Illuminate\Support\Str;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(protected Product $model) {}

    public function all()
    {
        return $this->model->with('category')->get();
    }

    public function create(ProductData $data)
    {
        return DB::transaction(function () use ($data) {
            $product = $this->model->create([
                'name' => $data->name,
                'slug' => Str::slug($data->name),
                'description' => $data->description,
                'price' => $data->price,
                'stock' => $data->stock,
                'category_id' => $data->category_id,
                'status' => $data->status,
                'attributes' => $data->attributes,
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

    public function updateStock(string $id, int $quantity)
    {
        $product = $this->model->findOrFail($id);
        $product->stock = $quantity;
        $product->save();

        return $product;
    }
}
