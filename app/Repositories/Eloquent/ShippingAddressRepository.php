<?php

namespace App\Repositories\Eloquent;

use App\Models\ShippingAddress;
use App\Repositories\Interfaces\ShippingAddressRepositoryInterface;
use App\Dtos\ShippingAddressData;
use Illuminate\Support\Facades\DB;

class ShippingAddressRepository implements ShippingAddressRepositoryInterface
{
    public function __construct(protected ShippingAddress $model) {}

    public function getAllForUser(string $userId)
    {
        return $this->model->where('user_id', $userId)
                          ->orderBy('is_default', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();
    }

    public function create(string $userId, ShippingAddressData $data)
    {
        return DB::transaction(function () use ($userId, $data) {
            // Si es la dirección predeterminada, quitar el estado predeterminado de otras direcciones
            if ($data->is_default) {
                $this->model->where('user_id', $userId)
                           ->where('is_default', true)
                           ->update(['is_default' => false]);
            }

            // Si es la primera dirección del usuario, hacerla predeterminada
            $hasAddresses = $this->model->where('user_id', $userId)->exists();
            if (!$hasAddresses) {
                $data->is_default = true;
            }

            return $this->model->create(array_merge(
                $data->toArray(),
                ['user_id' => $userId]
            ));
        });
    }

    public function update(string $id, ShippingAddressData $data)
    {
        $address = $this->model->findOrFail($id);

        return DB::transaction(function () use ($address, $data) {
            // Si se está estableciendo como predeterminada
            if ($data->is_default && !$address->is_default) {
                $this->model->where('user_id', $address->user_id)
                           ->where('is_default', true)
                           ->update(['is_default' => false]);
            }

            $address->update($data->toArray());
            return $address->fresh();
        });
    }

    public function delete(string $id)
    {
        $address = $this->model->findOrFail($id);

        return DB::transaction(function () use ($address) {
            // Si la dirección a eliminar es la predeterminada, establecer otra como predeterminada
            if ($address->is_default) {
                $otherAddress = $this->model
                    ->where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->first();

                if ($otherAddress) {
                    $otherAddress->update(['is_default' => true]);
                }
            }

            return $address->delete();
        });
    }

    public function find(string $id)
    {
        return $this->model->findOrFail($id);
    }

    public function setDefault(string $userId, string $addressId)
    {
        return DB::transaction(function () use ($userId, $addressId) {
            // Quitar el estado predeterminado de todas las direcciones del usuario
            $this->model->where('user_id', $userId)
                       ->where('is_default', true)
                       ->update(['is_default' => false]);

            // Establecer la nueva dirección predeterminada
            $address = $this->model->findOrFail($addressId);
            $address->update(['is_default' => true]);

            return $address;
        });
    }
}
