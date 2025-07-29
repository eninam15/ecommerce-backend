<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockReservation extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'cart_id',
        'order_id',
        'quantity',
        'status',
        'expires_at',
        'confirmed_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime'
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                    ->where('status', 'active');
    }

    public function scopeByProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Métodos
    public function isExpired(): bool
    {
        return $this->expires_at < now() && $this->status === 'active';
    }

    public function confirm(): void
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now()
        ]);
    }

    public function release(): void
    {
        $this->update(['status' => 'released']);
    }
}