<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Banner\BannerRequest;
use App\Services\BannerService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;



class BannerController extends Controller
{
    protected $bannerService;

    public function __construct(BannerService $bannerService)
    {
        $this->bannerService = $bannerService;
    }

    public function index()
    {
        $banners = $this->bannerService->getAllBanners();
        return BannerResource::collection($banners);
    }
    
    public function store(BannerRequest $request)
    {
        $banner = $this->bannerService->createBanner($request->validated());
        return new BannerResource($banner);
    }
    

    public function show($id)
    {
        $banner = $this->bannerService->getBannerById($id);
        return response()->json($banner);
    }

    public function update(BannerRequest $request, $id)
    {
        $banner = $this->bannerService->getBannerById($id);
        $updatedBanner = $this->bannerService->updateBanner($banner, $request->validated());
        return response()->json($updatedBanner);
    }

    public function destroy($id)
    {
        $banner = $this->bannerService->getBannerById($id);
        $this->bannerService->deleteBanner($banner);
        return response()->json(null, 204);
    }
}