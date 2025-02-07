<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repositories\Interfaces\RelatedProductRepositoryInterface;
use App\Dtos\RelatedProductData;
use App\Models\RelatedProduct;
use App\Models\Product;

class RelatedProductRepository implements RelatedProductRepositoryInterface
{
    public function __construct(protected RelatedProduct $relatedProduct) {}

    public function create(RelatedProductData $data)
    {
        $relatedProducts = collect();

        foreach ($data->relatedProductIds as $relatedId) {
            $score = $this->calculateRelationshipScore($data->productId, $relatedId);

            $relation = $this->relatedProduct->create([
                'product_id' => $data->productId,
                'related_product_id' => $relatedId,
                'relationship_type' => $data->type->value,
                'score' => $score
            ]);

            // Crear la relación inversa también
            $this->relatedProduct->create([
                'product_id' => $relatedId,
                'related_product_id' => $data->productId,
                'relationship_type' => $data->type->value,
                'score' => $score
            ]);

            $relatedProducts->push($relation);
        }

        return $relatedProducts;
    }

    public function delete(string $productId, string $relatedProductId)
    {
        // Eliminar ambas direcciones de la relación
        $this->relatedProduct->where(function ($query) use ($productId, $relatedProductId) {
            $query->where('product_id', $productId)
                ->where('related_product_id', $relatedProductId);
        })->orWhere(function ($query) use ($productId, $relatedProductId) {
            $query->where('product_id', $relatedProductId)
                ->where('related_product_id', $productId);
        })->delete();

        return true;
    }

    public function findByProduct(string $productId)
    {
        return $this->relatedProduct->where('product_id', $productId)
            ->with('relatedProduct')
            ->orderBy('score', 'desc')
            ->get();
    }

    public function findSimilarProducts(string $productId, int $limit = 5)
    {
        return $this->relatedProduct->where('product_id', $productId)
            ->with('relatedProduct')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('relatedProduct');
    }

    public function calculateRelationshipScore(string $productId, string $relatedId)
    {
        $product = Product::with('category')->find($productId);
        $relatedProduct = Product::with('category')->find($relatedId);

        $score = 0;

        // Mismo categoria: +3 puntos
        if ($product->category_id === $relatedProduct->category_id) {
            $score += 3;
        }

        // Rango de precio similar: +2 puntos
        if (abs($product->price - $relatedProduct->price) / $product->price < 0.3) {
            $score += 2;
        }

        // Atributos similares: +1 punto por cada atributo compartido
        $commonAttributes = array_intersect_key($product->attributes ?? [], $relatedProduct->attributes ?? []);
        $score += count($commonAttributes);

        return $score;
    }
}
