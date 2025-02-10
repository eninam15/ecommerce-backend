<?php
namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class ShippingAddressData extends DataTransferObject
{
    public string $name;
    public string $last_name;
    public string $email;
    public string $address;
    public string $city;
    public string $phone;
    public ?string $delivery_instructions;
    public bool $is_default;

    public static function fromRequest($request): self
    {
        return new self([
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'address' => $request->address,
            'city' => $request->city,
            'phone' => $request->phone,
            'delivery_instructions' => $request->delivery_instructions,
            'is_default' => $request->boolean('is_default')
        ]);
    }
}
