<?php

namespace App\Repositories\Interfaces;

use App\Models\Banner;
use Illuminate\Http\Request;

interface BannerRepositoryInterface 
{
    public function create(array $data);
    public function update(Banner $banner, array $data);
    public function delete(Banner $banner);
    public function findById($id);
    public function getAllBanners();
}