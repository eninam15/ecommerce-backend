<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class RelatedProductRepository implements RelatedProductRepositoryInterface
{
    public function __construct(protected RelatedProduct $model) {}

    public function create(RelatedProductData $data): Collection
    {
        $relatedProducts = collect();

        foreach ($data->relatedProductIds as $relatedId) {
            $score = $this->calculateRelationshipScore($data->productId, $relatedId);
            
            $relation = $this->model->create([
                'product_id' => $data->productId,
                'related_product_id' => $relatedId,
                'relationship_type' => $data->type->value,
                'score' => $score
            ]);

            // Crear la relación inversa también
            $this->model->create([
                'product_id' => $relatedId,
                'related_product_id' => $data->productId,
                'relationship_type' => $data->type->value,
                'score' => $score
            ]);

            $relatedProducts->push($relation);
        }

        return $relatedProducts;
    }

    public function delete(string $productId, string $relatedProductId): bool
    {
        // Eliminar ambas direcciones de la relación
        $this->model->where(function ($query) use ($productId, $relatedProductId) {
            $query->where('product_id', $productId)
                ->where('related_product_id', $relatedProductId);
        })->orWhere(function ($query) use ($productId, $relatedProductId) {
            $query->where('product_id', $relatedProductId)
                ->where('related_product_id', $productId);
        })->delete();

        return true;
    }

    public function findByProduct(string $productId): Collection
    {
        return $this->model->where('product_id', $productId)
            ->with('relatedProduct')
            ->orderBy('score', 'desc')
            ->get();
    }

    public function findSimilarProducts(string $productId, int $limit = 5): Collection
    {
        return $this->model->where('product_id', $productId)
            ->with('relatedProduct')
            ->orderBy('score', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('relatedProduct');
    }

    private function calculateRelationshipScore(string $productId, string $relatedId): float
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