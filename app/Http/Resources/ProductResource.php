<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'attributes' => $this->attributes,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'in_cart' => $this->when(
                isset($this->in_cart),
                (bool) $this->in_cart
            ),
            'cart_quantity' => $this->when(
                isset($this->cart_quantity),
                (int) $this->cart_quantity
            ),
            'related_products' => ProductResource::collection($this->whenLoaded('relatedProducts')),
            'blogs' => BlogResource::collection($this->whenLoaded('blogs')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'average_rating' => $this->when($this->reviews_count > 0, $this->average_rating),
            'reviews_count' => $this->when($this->reviews_count > 0, $this->reviews_count),
            'active_promotions' => PromotionResource::collection($this->whenLoaded('activePromotions')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
