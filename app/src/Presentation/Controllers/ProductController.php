<?php

namespace App\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\src\Domain\Models\Product;
use App\src\Domain\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
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
            ->paginate($request->per_page ?? 15);

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        try {
            DB::beginTransaction();

            // Preparar los datos del producto
            $productData = $request->except('images', 'primary_image');

            
            // Agregar el usuario que crea el producto
            $productData['created_by'] = "9e0392ad-ef98-459f-9529-7c718e22fc05"; // Obtén el ID del usuario autenticado
            $productData['updated_by'] = "9e0392ad-ef98-459f-9529-7c718e22fc05"; // Puedes inicializarlo con el mismo valor
            
            // Manejar atributos que vienen como form-data
            $attributes = [];
            foreach ($request->all() as $key => $value) {
                if (strpos($key, 'attributes[') === 0) {
                    $attributeKey = str_replace(['attributes[', ']'], '', $key);
                    $attributes[$attributeKey] = $value;
                }
            }
            
            if (!empty($attributes)) {
                $productData['attributes'] = $attributes;
            }



            // Crear el producto
            $product = Product::create($productData);

            // Manejar las imágenes
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    // Generar un nombre único para la imagen
                    $filename = uniqid() . '_' . $image->getClientOriginalName();
                    
                    // Almacenar la imagen
                    $path = $image->storeAs('products', $filename, 'public');
                    
                    // Crear el registro de la imagen
                    $product->images()->create([
                        'path' => $path,
                        'is_primary' => $request->input('primary_image') == $index,
                        'order' => $index
                    ]);
                }
            }

            DB::commit();

            return new ProductResource($product->load(['category', 'images', 'created_by', 'updated_by']));
        } catch (\Exception $e) {
            DB::rollBack();
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
}