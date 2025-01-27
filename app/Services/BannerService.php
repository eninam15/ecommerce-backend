<?php

namespace App\Services;

use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerService
{
    protected $bannerRepository;

    public function __construct(BannerRepositoryInterface $bannerRepository)
    {
        $this->bannerRepository = $bannerRepository;
    }

    public function getAllBanners()
    {
        return $this->bannerRepository->getAllBanners();
    }

    public function createBanner(array $data)
    {
        return $this->bannerRepository->create($data);
    }

    public function getBannerById($id)
    {
        return $this->bannerRepository->findById($id);
    }

    public function updateBanner(Banner $banner, array $data)
    {
        return $this->bannerRepository->update($banner, $data);
    }

    public function deleteBanner(Banner $banner)
    {
        return $this->bannerRepository->delete($banner);
    }
}