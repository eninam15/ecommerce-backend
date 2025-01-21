<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Traits\HasUuid;

class Product extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, HasUuid;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'is_active',
        'sku',
        'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useFallbackUrl('/images/placeholder.jpg')
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(100)
                    ->height(100);

                $this->addMediaConversion('medium')
                    ->width(400)
                    ->height(400);

                $this->addMediaConversion('large')
                    ->width(800)
                    ->height(800);
            });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
