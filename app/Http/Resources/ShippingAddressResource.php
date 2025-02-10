<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingAddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'phone' => $this->phone,
            'delivery_instructions' => $this->delivery_instructions,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'formatted_address' => $this->getFormattedAddress()
        ];
    }

    private function getFormattedAddress(): string
    {
        $parts = [
            $this->address,
            $this->city,
        ];

        return implode(', ', array_filter($parts));
    }
}
