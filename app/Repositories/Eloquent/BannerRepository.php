<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Models\Banner;
use Illuminate\Support\Facades\Storage;

class BannerRepository implements BannerRepositoryInterface
{
    public function create(array $data)
    {
        // Manejar la subida de imagen sin librería externa
        if (isset($data['image'])) {
            $imageData = $data['image'];
            $imageName = 'banner_' . uniqid() . '.' . $imageData->getClientOriginalExtension();
            $path = $imageData->storeAs('banners', $imageName, 'public');
            $data['image'] = $path;
        }

        return Banner::create($data);
    }

    public function update(Banner $banner, array $data)
    {
        // Manejar actualización de imagen
        if (isset($data['image'])) {
            // Eliminar imagen anterior si existe
            if ($banner->image) {
                Storage::disk('public')->delete($banner->image);
            }

            $imageData = $data['image'];
            $imageName = 'banner_' . uniqid() . '.' . $imageData->getClientOriginalExtension();
            $path = $imageData->storeAs('banners', $imageName, 'public');
            $data['image'] = $path;
        }

        $banner->update($data);
        return $banner;
    }

    public function delete(Banner $banner)
    {
        // Eliminar imagen asociada
        if ($banner->image) {
            Storage::disk('public')->delete($banner->image);
        }

        return $banner->delete();
    }

    public function findById($id)
    {
        return Banner::findOrFail($id);
    }

    public function getAllBanners()
    {
        return Banner::all();
    }
}