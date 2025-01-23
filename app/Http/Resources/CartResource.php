<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;


class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'total' => $this->total,
            'items_count' => $this->items_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
