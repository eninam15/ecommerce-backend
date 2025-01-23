<?php
namespace App\Services;
use App\Dtos\ShippingAddressData;
use App\Repositories\Interfaces\ShippingAddressRepositoryInterface;


class ShippingAddressService
{
    public function __construct(
        protected ShippingAddressRepositoryInterface $shippingAddressRepository
    ) {}

    public function getUserAddresses(string $userId)
    {
        return $this->shippingAddressRepository->getAllForUser($userId);
    }

    public function createAddress(string $userId, ShippingAddressData $data)
    {
        return $this->shippingAddressRepository->create($userId, $data);
    }

    public function updateAddress(string $id, ShippingAddressData $data)
    {
        return $this->shippingAddressRepository->update($id, $data);
    }

    public function deleteAddress(string $id)
    {
        return $this->shippingAddressRepository->delete($id);
    }

    public function setDefaultAddress(string $userId, string $addressId)
    {
        return $this->shippingAddressRepository->setDefault($userId, $addressId);
    }
}
