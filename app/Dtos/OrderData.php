<?php

namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class OrderData extends DataTransferObject
{
    public string $shipping_address_id;
    public ?string $notes;

    public static function fromRequest($request): self
    {
        return new self([
            'shipping_address_id' => $request->shipping_address_id,
            'notes' => $request->notes
        ]);
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'shipping_address_id' => $data['shipping_address_id'],
            'notes' => $data['notes'] ?? null
        ]);
    }
}
