<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cart extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 
        'total',
        'subtotal',
        'coupon_id',
        'coupon_code',
        'coupon_discount'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'coupon_discount' => 'decimal:2'
    ];

    // ===== RELACIONES =====

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    // ===== ATTRIBUTES Y MÉTODOS =====

    /**
     * Obtener el subtotal calculado (sin cupón)
     */
    public function getCalculatedSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
    }

    /**
     * Obtener el total calculado (con cupón)
     */
    public function getCalculatedTotalAttribute(): float
    {
        $subtotal = $this->getCalculatedSubtotalAttribute();
        return $subtotal - $this->coupon_discount;
    }

    /**
     * Verificar si tiene cupón aplicado
     */
    public function hasCoupon(): bool
    {
        return !is_null($this->coupon_id);
    }

    /**
     * Obtener información del descuento
     */
    public function getDiscountInfoAttribute(): array
    {
        if (!$this->hasCoupon()) {
            return [
                'has_discount' => false,
                'discount_amount' => 0,
                'discount_percentage' => 0,
                'free_shipping' => false
            ];
        }

        $discountPercentage = $this->subtotal > 0 
            ? ($this->coupon_discount / $this->subtotal) * 100 
            : 0;

        $freeShipping = $this->coupon && 
            $this->coupon->type === \App\Enums\CouponType::FREE_SHIPPING;

        return [
            'has_discount' => true,
            'discount_amount' => $this->coupon_discount,
            'discount_percentage' => round($discountPercentage, 2),
            'free_shipping' => $freeShipping,
            'coupon_code' => $this->coupon_code,
            'coupon_type' => $this->coupon?->type
        ];
    }

    /**
     * Obtener resumen del carrito
     */
    public function getSummaryAttribute(): array
    {
        $subtotal = $this->calculated_subtotal;
        $discount = $this->coupon_discount;
        $tax = $subtotal * 0.16; // 16% impuestos
        $shipping = 0;

        // Si hay cupón de envío gratis
        if ($this->coupon && $this->coupon->type === \App\Enums\CouponType::FREE_SHIPPING) {
            $shipping = 0;
        }

        $total = $subtotal + $tax + $shipping - $discount;

        return [
            'items_count' => $this->items->count(),
            'total_quantity' => $this->items->sum('quantity'),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'shipping' => $shipping,
            'total' => $total,
            'discount_info' => $this->discount_info
        ];
    }

    // ===== SCOPES =====

    public function scopeWithCoupon($query)
    {
        return $query->whereNotNull('coupon_id');
    }

    public function scopeWithoutCoupon($query)
    {
        return $query->whereNull('coupon_id');
    }
}