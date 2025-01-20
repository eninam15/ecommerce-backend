<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'path' => url('storage/' . $this->path),
            'is_primary' => $this->is_primary,
            'order' => $this->order,
        ];
    }
}
