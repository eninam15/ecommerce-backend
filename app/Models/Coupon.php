<?php

namespace App\Models;

use App\Traits\HasUserTracking;
use App\Enums\CouponType;
use App\Enums\CouponStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use HasUuids, HasFactory, SoftDeletes, HasUserTracking;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'discount_value',
        'minimum_amount',
        'maximum_discount',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'first_purchase_only',
        'status',
        'starts_at',
        'expires_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'type' => CouponType::class,
        'discount_value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'first_purchase_only' => 'boolean',
        'status' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->code = strtoupper($model->code);
        });
    }

    // ===== RELACIONES =====

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'coupon_categories');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'coupon_products');
    }

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    // ===== SCOPES =====

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeValid($query)
    {
        return $query->active()
                    ->where(function ($q) {
                        $q->whereNull('starts_at')
                          ->orWhere('starts_at', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', now());
                    });
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    public function scopeByType($query, CouponType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeNotExhausted($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('usage_limit')
              ->orWhereRaw('used_count < usage_limit');
        });
    }

    // ===== MÉTODOS DE ESTADO =====

    public function isActive(): bool
    {
        return $this->status === true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function hasStarted(): bool
    {
        return !$this->starts_at || $this->starts_at <= now();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit && $this->used_count >= $this->usage_limit;
    }

    public function isValid(): bool
    {
        return $this->isActive() && 
               $this->hasStarted() && 
               !$this->isExpired() && 
               !$this->isExhausted();
    }

    public function getStatus(): CouponStatus
    {
        if (!$this->isActive()) {
            return CouponStatus::INACTIVE;
        }

        if ($this->isExpired()) {
            return CouponStatus::EXPIRED;
        }

        if ($this->isExhausted()) {
            return CouponStatus::EXHAUSTED;
        }

        return CouponStatus::ACTIVE;
    }

    // ===== MÉTODOS DE USO =====

    public function canBeUsedBy(string $userId): bool
    {
        if (!$this->usage_limit_per_user) {
            return true;
        }

        $userUsageCount = $this->usages()
            ->where('user_id', $userId)
            ->count();

        return $userUsageCount < $this->usage_limit_per_user;
    }

    public function getUserUsageCount(string $userId): int
    {
        return $this->usages()
            ->where('user_id', $userId)
            ->count();
    }

    public function getRemainingUses(): ?int
    {
        if (!$this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->used_count);
    }

    public function getUserRemainingUses(string $userId): ?int
    {
        if (!$this->usage_limit_per_user) {
            return null;
        }

        $userUsageCount = $this->getUserUsageCount($userId);
        return max(0, $this->usage_limit_per_user - $userUsageCount);
    }

    // ===== MÉTODOS DE CÁLCULO =====

    public function calculateDiscount(float $subtotal, array $applicableItems = []): float
    {
        switch ($this->type) {
            case CouponType::PERCENTAGE:
                $discount = $subtotal * ($this->discount_value / 100);
                return $this->maximum_discount 
                    ? min($discount, $this->maximum_discount) 
                    : $discount;

            case CouponType::FIXED_AMOUNT:
                return min($this->discount_value, $subtotal);

            case CouponType::FREE_SHIPPING:
                return 0; // El descuento se aplica al envío, no al subtotal

            case CouponType::CATEGORY_DISCOUNT:
            case CouponType::PRODUCT_DISCOUNT:
                $applicableTotal = collect($applicableItems)->sum('subtotal');
                $discount = $applicableTotal * ($this->discount_value / 100);
                return $this->maximum_discount 
                    ? min($discount, $this->maximum_discount) 
                    : $discount;

            default:
                return 0;
        }
    }

    public function isApplicableToProduct(string $productId): bool
    {
        // Si no tiene productos específicos, aplica a todos
        if ($this->products()->count() === 0 && $this->categories()->count() === 0) {
            return true;
        }

        // Verificar si el producto está directamente asociado
        if ($this->products()->where('product_id', $productId)->exists()) {
            return true;
        }

        // Verificar si la categoría del producto está asociada
        $product = Product::find($productId);
        if ($product && $this->categories()->where('category_id', $product->category_id)->exists()) {
            return true;
        }

        return false;
    }

    // ===== MÉTODOS ESTÁTICOS =====

    public static function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function findByCode(string $code): ?self
    {
        return self::byCode($code)->first();
    }
}

// ===== MODELO COUPON USAGE =====

class CouponUsage extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'discount_amount'
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2'
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}