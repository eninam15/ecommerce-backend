<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUserTracking;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'slug',
        'description',
        'price',
        'cost_price',
        'weight',
        'volume',
        'flavor',
        'presentation',
        'stock',
        'min_stock',
        'sku',
        'barcode',
        'status',
        'featured',
        'is_seasonal',
        'manufacture_date',
        'expiry_date',
        'nutritional_info',
        'ingredients',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'status' => 'boolean'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = Str::uuid()->toString();
            $model->slug = Str::slug($model->name);
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function relatedProducts()
    {
        return $this->hasMany(RelatedProduct::class)
            ->with('relatedProduct');
    }

    public function blogs()
    {
        return $this->belongsToMany(Blog::class, 'blog_product');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product')
            ->withPivot('discount_value', 'quantity_required');
    }

    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating');
    }
}
