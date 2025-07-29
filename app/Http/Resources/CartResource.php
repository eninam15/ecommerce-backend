<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            
            // Items del carrito
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items->count(),
            'total_quantity' => $this->items->sum('quantity'),
            
            // Información de cupón aplicado
            'coupon' => $this->when($this->coupon_id, [
                'id' => $this->coupon_id,
                'code' => $this->coupon_code,
                'name' => $this->whenLoaded('coupon', function () {
                    return $this->coupon->name;
                }),
                'type' => $this->whenLoaded('coupon', function () {
                    return [
                        'value' => $this->coupon->type,
                        'label' => $this->coupon->type->label()
                    ];
                }),
                'discount_amount' => $this->coupon_discount,
                'free_shipping' => $this->whenLoaded('coupon', function () {
                    return $this->coupon->type === \App\Enums\CouponType::FREE_SHIPPING;
                })
            ]),
            
            // Totales y cálculos
            'pricing' => [
                'subtotal' => $this->calculated_subtotal,
                'coupon_discount' => $this->coupon_discount,
                'tax' => $this->calculated_subtotal * 0.16,
                'shipping' => $this->when($this->coupon && $this->coupon->type === \App\Enums\CouponType::FREE_SHIPPING, 0, 0),
                'total' => $this->calculated_total + ($this->calculated_subtotal * 0.16),
                'savings' => $this->coupon_discount
            ],
            
            // Información de descuentos
            'discount_info' => $this->discount_info,
            
            // Resumen completo
            'summary' => $this->summary,
            
            // Información de estado
            'status' => [
                'has_items' => $this->items->count() > 0,
                'has_coupon' => $this->hasCoupon(),
                'is_empty' => $this->items->count() === 0,
                'can_checkout' => $this->items->count() > 0
            ],
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}