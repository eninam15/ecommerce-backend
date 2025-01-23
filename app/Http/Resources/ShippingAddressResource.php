<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingAddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'postal_code' => $this->postal_code,
            'is_default' => $this->is_default,
            'delivery_instructions' => $this->delivery_instructions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'formatted_address' => $this->getFormattedAddress()
        ];
    }

    private function getFormattedAddress(): string
    {
        $parts = [
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->country,
            $this->postal_code
        ];

        return implode(', ', array_filter($parts));
    }
}
