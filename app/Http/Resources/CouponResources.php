<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => [
                'value' => $this->type,
                'label' => $this->type->label(),
                'description' => $this->type->description()
            ],
            'discount_value' => $this->discount_value,
            'minimum_amount' => $this->minimum_amount,
            'maximum_discount' => $this->maximum_discount,
            'usage_limit' => $this->usage_limit,
            'usage_limit_per_user' => $this->usage_limit_per_user,
            'used_count' => $this->used_count,
            'first_purchase_only' => $this->first_purchase_only,
            'status' => [
                'active' => $this->status,
                'current_status' => $this->getStatus(),
                'label' => $this->getStatus()->label()
            ],
            'validity' => [
                'starts_at' => $this->starts_at,
                'expires_at' => $this->expires_at,
                'is_valid' => $this->isValid(),
                'has_started' => $this->hasStarted(),
                'is_expired' => $this->isExpired(),
                'is_exhausted' => $this->isExhausted()
            ],
            'usage_info' => [
                'remaining_uses' => $this->getRemainingUses(),
                'usage_percentage' => $this->usage_limit 
                    ? ($this->used_count / $this->usage_limit) * 100 
                    : 0
            ],
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

class CouponUsageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'coupon' => [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'name' => $this->coupon->name,
                'type' => $this->coupon->type
            ],
            'user' => new UserResource($this->whenLoaded('user')),
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'total' => $this->order->total
            ],
            'discount_amount' => $this->discount_amount,
            'created_at' => $this->created_at
        ];
    }
}

class AppliedCouponResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'coupon' => [
                'id' => $this->coupon_id,
                'code' => $this->coupon_code,
                'name' => $this->whenLoaded('coupon', function () {
                    return $this->coupon->name;
                }),
                'type' => $this->whenLoaded('coupon', function () {
                    return $this->coupon->type;
                })
            ],
            'discount_amount' => $this->coupon_discount,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'free_shipping' => $this->whenLoaded('coupon', function () {
                return $this->coupon->type === \App\Enums\CouponType::FREE_SHIPPING;
            }),
            'discount_percentage' => $this->subtotal > 0 
                ? ($this->coupon_discount / $this->subtotal) * 100 
                : 0
        ];
    }
}

class CouponValidationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'is_valid' => $this->resource->isValid,
            'result' => $this->resource->result->value,
            'message' => $this->resource->message,
            'discount_data' => $this->when(
                $this->resource->discountData,
                function () {
                    return [
                        'coupon_id' => $this->resource->discountData->couponId,
                        'coupon_code' => $this->resource->discountData->couponCode,
                        'type' => $this->resource->discountData->type,
                        'discount_amount' => $this->resource->discountData->discountAmount,
                        'original_subtotal' => $this->resource->discountData->originalSubtotal,
                        'final_subtotal' => $this->resource->discountData->finalSubtotal,
                        'free_shipping' => $this->resource->discountData->freeShipping,
                        'discount_percentage' => $this->resource->discountData->getDiscountPercentage()
                    ];
                }
            ),
            'validation_details' => $this->resource->validationDetails
        ];
    }
}

class CouponStatsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'overview' => [
                'total_coupons' => $this->resource['total_coupons'],
                'active_coupons' => $this->resource['active_coupons'],
                'expired_coupons' => $this->resource['expired_coupons'],
                'exhausted_coupons' => $this->resource['exhausted_coupons']
            ],
            'usage_stats' => [
                'total_usages' => $this->resource['total_usages'],
                'total_discount_amount' => $this->resource['total_discount_amount'],
                'average_discount' => $this->resource['total_usages'] > 0 
                    ? $this->resource['total_discount_amount'] / $this->resource['total_usages']
                    : 0
            ],
            'top_coupons' => CouponResource::collection($this->resource['top_coupons']),
            'status_distribution' => [
                'active_percentage' => $this->resource['total_coupons'] > 0 
                    ? ($this->resource['active_coupons'] / $this->resource['total_coupons']) * 100 
                    : 0,
                'expired_percentage' => $this->resource['total_coupons'] > 0 
                    ? ($this->resource['expired_coupons'] / $this->resource['total_coupons']) * 100 
                    : 0
            ]
        ];
    }
}