<?php

namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class OrderData extends DataTransferObject
{
    public string $shipping_address_id;
    public ?string $notes;
    public ?array $cart_items;

    public static function fromRequest($request): self
    {
        return new self([
            'shipping_address_id' => $request->shipping_address_id,
            'notes' => $request->notes,
            'cart_items' => $request->cart_items
        ]);
    }
}
