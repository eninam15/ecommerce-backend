<?php
namespace App\Services;
use App\Dtos\ShippingAddressData;
use App\Repositories\Interfaces\ShippingAddressRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Dtos\OrderData;


class ShippingAddressService
{
    public function __construct(
        protected ShippingAddressRepositoryInterface $shippingAddressRepository,
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function getUserAddresses(string $userId)
    {
        return $this->shippingAddressRepository->getAllForUser($userId);
    }

    public function createAddress(string $userId, ShippingAddressData $data)
    {
        $shippingAddress = $this->shippingAddressRepository->create($userId, $data);

        if (!$shippingAddress || !$shippingAddress->id) {
            return null;
        }

        $orderData = new OrderData(
            shipping_address_id: $shippingAddress->id,
            notes: $shippingAddress->delivery_instructions
        );

        $orderResponse = $this->orderRepository->create($shippingAddress->user_id, $orderData);
        $shippingAddress->order_id = $orderResponse->id;
        return $shippingAddress;
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
