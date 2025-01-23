<?php
namespace App\Repositories\Interfaces;

use App\Dtos\ShippingAddressData;

interface ShippingAddressRepositoryInterface
{
    public function getAllForUser(string $userId);
    public function create(string $userId, ShippingAddressData $data);
    public function update(string $id, ShippingAddressData $data);
    public function delete(string $id);
    public function find(string $id);
    public function setDefault(string $userId, string $addressId);
}
