<?php
namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class ShippingAddressData extends DataTransferObject
{
    public string $name;
    public string $recipient_name;
    public string $phone;
    public string $address_line1;
    public ?string $address_line2;
    public string $city;
    public string $state;
    public string $country;
    public string $postal_code;
    public bool $is_default;
    public ?string $delivery_instructions;

    public static function fromRequest($request): self
    {
        return new self([
            'name' => $request->name,
            'recipient_name' => $request->recipient_name,
            'phone' => $request->phone,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'is_default' => $request->boolean('is_default'),
            'delivery_instructions' => $request->delivery_instructions
        ]);
    }
}
