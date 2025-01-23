<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\StoreShippingAddressRequest;
use App\Http\Requests\Shipping\UpdateShippingAddressRequest;
use App\Http\Resources\ShippingAddressResource;
use App\Services\ShippingAddressService;
use App\Dtos\ShippingAddressData;

class ShippingAddressController extends Controller
{
    public function __construct(
        protected ShippingAddressService $shippingAddressService
    ) {}

    public function index()
    {
        $addresses = $this->shippingAddressService->getUserAddresses(auth()->id());
        return ShippingAddressResource::collection($addresses);
    }

    public function store(StoreShippingAddressRequest $request)
    {
        $address = $this->shippingAddressService->createAddress(
            auth()->id(),
            ShippingAddressData::fromRequest($request)
        );

        return new ShippingAddressResource($address);
    }

    public function update(UpdateShippingAddressRequest $request, string $id)
    {
        $address = $this->shippingAddressService->updateAddress(
            $id,
            ShippingAddressData::fromRequest($request)
        );

        return new ShippingAddressResource($address);
    }

    public function destroy(string $id)
    {
        $this->shippingAddressService->deleteAddress($id);
        return response()->noContent();
    }

    public function setDefault(string $id)
    {
        $address = $this->shippingAddressService->setDefaultAddress(
            auth()->id(),
            $id
        );

        return new ShippingAddressResource($address);
    }
}
