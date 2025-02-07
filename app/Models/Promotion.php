<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Promotion extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'description',
        'type', // 'discount', 'combo', 'seasonal'
        'discount_type', // 'percentage', 'fixed'
        'discount_value',
        'starts_at',
        'ends_at',
        'status',
        'min_quantity',
        'max_quantity'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => 'boolean'
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot('discount_value', 'quantity_required');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
