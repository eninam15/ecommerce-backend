<?php
namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class CartItemData extends DataTransferObject
{
    public string $product_id;
    public int $quantity;

    public static function fromRequest($request): self
    {
        return new self([
            'product_id' => $request->product_id,
            'quantity' => $request->quantity
        ]);
    }
}
