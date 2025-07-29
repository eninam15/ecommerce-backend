<?php

namespace App\Repositories\Interfaces;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Dtos\CouponData;
use App\Dtos\CouponFilterData;
use App\Dtos\CouponUsageData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface
{
    public function create(CouponData $data): Coupon;
    
    public function update(string $id, CouponData $data): Coupon;
    
    public function findById(string $id): ?Coupon;
    
    public function findByCode(string $code): ?Coupon;
    
    public function delete(string $id): bool;
    
    public function findByCriteria(CouponFilterData $filters): LengthAwarePaginator;
    
    public function getValidCoupons(): Collection;
    
    public function getActiveCoupons(): Collection;
    
    public function getExpiredCoupons(): Collection;
    
    public function getExhaustedCoupons(): Collection;
    
    public function recordUsage(CouponUsageData $data): CouponUsage;
    
    public function getUserUsages(string $userId): Collection;
    
    public function getCouponUsages(string $couponId): Collection;
    
    public function incrementUsageCount(string $couponId): bool;
    
    public function decrementUsageCount(string $couponId): bool;
}