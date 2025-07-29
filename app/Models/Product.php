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
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'volume' => 'decimal:3',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'status' => 'boolean',
        'featured' => 'boolean',
        'is_seasonal' => 'boolean',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'nutritional_info' => 'array',
        'ingredients' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = Str::uuid()->toString();
            $model->slug = Str::slug($model->name);
        });
    }

    // ===== RELACIONES EXISTENTES =====
    
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

    // ===== NUEVAS RELACIONES DE STOCK =====
    
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    public function activeReservations()
    {
        return $this->stockReservations()->where('status', 'active');
    }

    public function expiredReservations()
    {
        return $this->stockReservations()
            ->where('status', 'active')
            ->where('expires_at', '<', now());
    }

    // ===== MÉTODOS Y ATTRIBUTES =====
    
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->avg('rating');
    }

    /**
     * Obtener stock disponible (total - reservado - comprometido)
     */
    public function getAvailableStockAttribute(): int
    {
        $reserved = $this->activeReservations()->sum('quantity');
        $committed = $this->stockReservations()
            ->where('status', 'confirmed')
            ->sum('quantity');
            
        return max(0, $this->stock - $reserved - $committed);
    }

    /**
     * Obtener stock reservado
     */
    public function getReservedStockAttribute(): int
    {
        return $this->activeReservations()->sum('quantity');
    }

    /**
     * Verificar si tiene stock bajo
     */
    public function getHasLowStockAttribute(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    /**
     * Verificar si está sin stock
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->available_stock <= 0;
    }

    /**
     * Obtener estado del stock
     */
    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'out_of_stock';
        }
        
        if ($this->has_low_stock) {
            return 'low_stock';
        }
        
        if ($this->available_stock <= 0) {
            return 'reserved';
        }
        
        return 'in_stock';
    }

    // ===== SCOPES =====
    
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock', '<=', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock <= min_stock');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', true)->inStock();
    }

    // ===== MÉTODOS DE STOCK =====
    
    /**
     * Verificar si puede satisfacer una cantidad solicitada
     */
    public function canFulfill(int $quantity): bool
    {
        return $this->available_stock >= $quantity;
    }

    /**
     * Obtener movimientos recientes de stock
     */
    public function getRecentStockMovements(int $limit = 10)
    {
        return $this->stockMovements()
            ->with(['creator'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Calcular rotación de stock
     */
    public function calculateStockTurnover(int $days = 30): float
    {
        $sales = $this->stockMovements()
            ->where('type', 'reduce')
            ->where('created_at', '>=', now()->subDays($days))
            ->sum('quantity');

        return $this->stock > 0 ? ($sales / $this->stock) : 0;
    }
}