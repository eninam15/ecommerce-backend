<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasUuids, HasFactory, HasUserTracking;

    protected $fillable = [
        'product_id',
        'type',
        'reason',
        'quantity',
        'stock_before',
        'stock_after',
        'reference_id',
        'reference_type',
        'expires_at',
        'created_by',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'expires_at' => 'datetime'
    ];

    // Relaciones
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProduct($query, string $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByReference($query, string $referenceId, string $referenceType)
    {
        return $query->where('reference_id', $referenceId)
                    ->where('reference_type', $referenceType);
    }

    public function scopeExpiredReservations($query)
    {
        return $query->where('type', 'reserve')
                    ->where('expires_at', '<', now())
                    ->whereNull('confirmed_at');
    }
}