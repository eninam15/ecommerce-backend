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
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
