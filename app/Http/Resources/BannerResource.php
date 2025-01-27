<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image ? url(Storage::url($this->image)) : null,
            'link' => $this->link,
            'text_button' => $this->text_button,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}