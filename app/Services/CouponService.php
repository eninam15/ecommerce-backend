<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Cart;
use App\Models\User;
use App\Dtos\CouponData;
use App\Dtos\CouponValidationData;
use App\Dtos\CouponDiscountData;
use App\Dtos\CouponValidationResult;
use App\Dtos\ApplyCouponData;
use App\Dtos\CouponUsageData;
use App\Dtos\CouponFilterData;
use App\Enums\CouponType;
use App\Enums\CouponValidationResult as ValidationResultEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class CouponService
{
    public function __construct(
        protected Coupon $coupon,
        protected CouponUsage $couponUsage
    ) {}

    /**
     * Crear un nuevo cupón
     */
    public function createCoupon(CouponData $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            $coupon = $this->coupon->create([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'type' => $data->type->value,
                'discount_value' => $data->discountValue,
                'minimum_amount' => $data->minimumAmount,
                'maximum_discount' => $data->maximumDiscount,
                'usage_limit' => $data->usageLimit,
                'usage_limit_per_user' => $data->usageLimitPerUser,
                'first_purchase_only' => $data->firstPurchaseOnly,
                'status' => $data->status,
                'starts_at' => $data->startsAt,
                'expires_at' => $data->expiresAt
            ]);

            // Asociar categorías si aplica
            if (!empty($data->categoryIds)) {
                $coupon->categories()->sync($data->categoryIds);
            }

            // Asociar productos si aplica
            if (!empty($data->productIds)) {
                $coupon->products()->sync($data->productIds);
            }

            Log::info("Cupón creado", [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type
            ]);

            return $coupon->load(['categories', 'products']);
        });
    }

    /**
     * Validar un cupón antes de aplicarlo
     */
    public function validateCoupon(CouponValidationData $data): CouponValidationResult
    {
        $coupon = $this->coupon->byCode($data->couponCode)->first();

        // Verificar si el cupón existe
        if (!$coupon) {
            return CouponValidationResult::invalid(ValidationResultEnum::NOT_FOUND);
        }

        // Verificar si está activo
        if (!$coupon->isActive()) {
            return CouponValidationResult::invalid(ValidationResultEnum::INACTIVE);
        }

        // Verificar fechas de validez
        if (!$coupon->hasStarted()) {
            return CouponValidationResult::invalid(
                ValidationResultEnum::NOT_STARTED,
                ['starts_at' => $coupon->starts_at]
            );
        }

        if ($coupon->isExpired()) {
            return CouponValidationResult::invalid(
                ValidationResultEnum::EXPIRED,
                ['expires_at' => $coupon->expires_at]
            );
        }

        // Verificar límite de uso general
        if ($coupon->isExhausted()) {
            return CouponValidationResult::invalid(ValidationResultEnum::USAGE_LIMIT_EXCEEDED);
        }

        // Verificar límite de uso por usuario
        if (!$coupon->canBeUsedBy($data->userId)) {
            return CouponValidationResult::invalid(
                ValidationResultEnum::USER_LIMIT_EXCEEDED,
                [
                    'user_usage_count' => $coupon->getUserUsageCount($data->userId),
                    'limit' => $coupon->usage_limit_per_user
                ]
            );
        }

        // Verificar si es solo para primera compra
        if ($coupon->first_purchase_only && !$data->isFirstPurchase) {
            return CouponValidationResult::invalid(ValidationResultEnum::NOT_FIRST_PURCHASE);
        }

        // Verificar monto mínimo
        if ($coupon->minimum_amount && $data->cartSubtotal < $coupon->minimum_amount) {
            return CouponValidationResult::invalid(
                ValidationResultEnum::MINIMUM_AMOUNT_NOT_MET,
                [
                    'required_amount' => $coupon->minimum_amount,
                    'current_amount' => $data->cartSubtotal
                ]
            );
        }

        // Verificar productos/categorías aplicables
        $applicableItems = $this->getApplicableItems($coupon, $data->cartItems);
        
        if (in_array($coupon->type, [CouponType::CATEGORY_DISCOUNT, CouponType::PRODUCT_DISCOUNT]) 
            && empty($applicableItems)) {
            return CouponValidationResult::invalid(ValidationResultEnum::NO_APPLICABLE_PRODUCTS);
        }

        // Calcular descuento
        $discountData = $this->calculateDiscount($coupon, $data->cartSubtotal, $applicableItems);

        return CouponValidationResult::valid($discountData);
    }

    /**
     * Aplicar cupón al carrito
     */
    public function applyCouponToCart(ApplyCouponData $data): CouponValidationResult
    {
        return DB::transaction(function () use ($data) {
            $cart = Cart::with(['items.product'])->findOrFail($data->cartId);

            // Verificar si ya tiene un cupón aplicado
            if ($cart->coupon_id) {
                return CouponValidationResult::invalid(ValidationResultEnum::ALREADY_APPLIED);
            }

            // Preparar datos de validación
            $cartItems = $cart->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'category_id' => $item->product->category_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'subtotal' => $item->price * $item->quantity
                ];
            })->toArray();

            $isFirstPurchase = $this->isUserFirstPurchase($data->userId);

            $validationData = new CouponValidationData(
                couponCode: $data->couponCode,
                userId: $data->userId,
                cartSubtotal: $cart->items->sum(function ($item) {
                    return $item->price * $item->quantity;
                }),
                cartItems: $cartItems,
                isFirstPurchase: $isFirstPurchase,
                cartId: $data->cartId
            );

            // Validar cupón
            $validationResult = $this->validateCoupon($validationData);

            if (!$validationResult->isValid) {
                return $validationResult;
            }

            // Aplicar cupón al carrito
            $coupon = $this->coupon->byCode($data->couponCode)->first();
            $discountData = $validationResult->discountData;

            $cart->update([
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'coupon_discount' => $discountData->discountAmount,
                'subtotal' => $discountData->originalSubtotal,
                'total' => $discountData->finalSubtotal
            ]);

            Log::info("Cupón aplicado al carrito", [
                'cart_id' => $cart->id,
                'coupon_code' => $coupon->code,
                'discount_amount' => $discountData->discountAmount
            ]);

            return $validationResult;
        });
    }

    /**
     * Remover cupón del carrito
     */
    public function removeCouponFromCart(string $cartId): bool
    {
        return DB::transaction(function () use ($cartId) {
            $cart = Cart::with(['items'])->findOrFail($cartId);

            if (!$cart->coupon_id) {
                return false;
            }

            $originalSubtotal = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            $cart->update([
                'coupon_id' => null,
                'coupon_code' => null,
                'coupon_discount' => 0,
                'subtotal' => $originalSubtotal,
                'total' => $originalSubtotal
            ]);

            Log::info("Cupón removido del carrito", ['cart_id' => $cartId]);

            return true;
        });
    }

    /**
     * Registrar uso de cupón en una orden
     */
    public function recordCouponUsage(CouponUsageData $data): CouponUsage
    {
        return DB::transaction(function () use ($data) {
            $coupon = $this->coupon->findOrFail($data->couponId);

            // Crear registro de uso
            $usage = $this->couponUsage->create([
                'coupon_id' => $data->couponId,
                'user_id' => $data->userId,
                'order_id' => $data->orderId,
                'discount_amount' => $data->discountAmount
            ]);

            // Incrementar contador de usos
            $coupon->increment('used_count');

            Log::info("Uso de cupón registrado", [
                'coupon_id' => $data->couponId,
                'order_id' => $data->orderId,
                'discount_amount' => $data->discountAmount
            ]);

            return $usage;
        });
    }

    /**
     * Obtener cupones con filtros
     */
    public function getCoupons(CouponFilterData $filters): LengthAwarePaginator
    {
        $query = $this->coupon->query()
            ->with(['categories', 'products'])
            ->when($filters->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                          ->orWhere('name', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters->type, function ($q, $type) {
                $q->byType($type);
            })
            ->when($filters->status !== null, function ($q) use ($filters) {
                $q->where('status', $filters->status);
            })
            ->when($filters->expired !== null, function ($q) use ($filters) {
                if ($filters->expired) {
                    $q->where('expires_at', '<', now());
                } else {
                    $q->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>=', now());
                    });
                }
            })
            ->when($filters->exhausted !== null, function ($q) use ($filters) {
                if ($filters->exhausted) {
                    $q->whereRaw('used_count >= usage_limit');
                } else {
                    $q->where(function ($query) {
                        $query->whereNull('usage_limit')
                              ->orWhereRaw('used_count < usage_limit');
                    });
                }
            });

        return $query->orderByDesc('created_at')
                    ->paginate($filters->perPage);
    }

    /**
     * Obtener cupones válidos para un usuario
     */
    public function getValidCouponsForUser(string $userId): \Illuminate\Support\Collection
    {
        $isFirstPurchase = $this->isUserFirstPurchase($userId);

        return $this->coupon->valid()
            ->notExhausted()
            ->when(!$isFirstPurchase, function ($query) {
                $query->where('first_purchase_only', false);
            })
            ->get()
            ->filter(function ($coupon) use ($userId) {
                return $coupon->canBeUsedBy($userId);
            });
    }

    /**
     * Calcular descuento de un cupón
     */
    protected function calculateDiscount(Coupon $coupon, float $subtotal, array $applicableItems): CouponDiscountData
    {
        $discountAmount = $coupon->calculateDiscount($subtotal, $applicableItems);
        $freeShipping = $coupon->type === CouponType::FREE_SHIPPING;

        return new CouponDiscountData(
            couponId: $coupon->id,
            couponCode: $coupon->code,
            type: $coupon->type,
            discountAmount: $discountAmount,
            originalSubtotal: $subtotal,
            finalSubtotal: $subtotal - $discountAmount,
            freeShipping: $freeShipping,
            applicableItems: $applicableItems
        );
    }

    /**
     * Obtener items aplicables para el cupón
     */
    protected function getApplicableItems(Coupon $coupon, array $cartItems): array
    {
        if ($coupon->type === CouponType::PRODUCT_DISCOUNT) {
            $applicableProductIds = $coupon->products->pluck('id')->toArray();
            return array_filter($cartItems, function ($item) use ($applicableProductIds) {
                return in_array($item['product_id'], $applicableProductIds);
            });
        }

        if ($coupon->type === CouponType::CATEGORY_DISCOUNT) {
            $applicableCategoryIds = $coupon->categories->pluck('id')->toArray();
            return array_filter($cartItems, function ($item) use ($applicableCategoryIds) {
                return in_array($item['category_id'], $applicableCategoryIds);
            });
        }

        // Para otros tipos, todos los items son aplicables
        return $cartItems;
    }

    /**
     * Verificar si es la primera compra del usuario
     */
    protected function isUserFirstPurchase(string $userId): bool
    {
        return !\App\Models\Order::where('user_id', $userId)
            ->whereIn('status', ['paid', 'delivered', 'completed'])
            ->exists();
    }

    /**
     * Generar código único de cupón
     */
    public function generateUniqueCode(int $length = 8): string
    {
        return Coupon::generateUniqueCode($length);
    }

    /**
     * Obtener estadísticas de cupones
     */
    public function getCouponStats(): array
    {
        $totalCoupons = $this->coupon->count();
        $activeCoupons = $this->coupon->active()->count();
        $expiredCoupons = $this->coupon->where('expires_at', '<', now())->count();
        $exhaustedCoupons = $this->coupon->whereRaw('used_count >= usage_limit')->count();
        
        $totalUsages = $this->couponUsage->count();
        $totalDiscountAmount = $this->couponUsage->sum('discount_amount');
        
        $topCoupons = $this->coupon->withCount('usages')
            ->orderByDesc('usages_count')
            ->limit(5)
            ->get();

        return [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'expired_coupons' => $expiredCoupons,
            'exhausted_coupons' => $exhaustedCoupons,
            'total_usages' => $totalUsages,
            'total_discount_amount' => $totalDiscountAmount,
            'top_coupons' => $topCoupons
        ];
    }

    /**
     * Buscar cupón por código
     */
    public function findByCode(string $code): ?Coupon
    {
        return $this->coupon->byCode($code)->with(['categories', 'products'])->first();
    }

    /**
     * Actualizar cupón
     */
    public function updateCoupon(string $couponId, CouponData $data): Coupon
    {
        return DB::transaction(function () use ($couponId, $data) {
            $coupon = $this->coupon->findOrFail($couponId);

            $coupon->update([
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'type' => $data->type->value,
                'discount_value' => $data->discountValue,
                'minimum_amount' => $data->minimumAmount,
                'maximum_discount' => $data->maximumDiscount,
                'usage_limit' => $data->usageLimit,
                'usage_limit_per_user' => $data->usageLimitPerUser,
                'first_purchase_only' => $data->firstPurchaseOnly,
                'status' => $data->status,
                'starts_at' => $data->startsAt,
                'expires_at' => $data->expiresAt
            ]);

            // Actualizar asociaciones
            $coupon->categories()->sync($data->categoryIds ?? []);
            $coupon->products()->sync($data->productIds ?? []);

            return $coupon->fresh(['categories', 'products']);
        });
    }

    /**
     * Eliminar cupón
     */
    public function deleteCoupon(string $couponId): bool
    {
        $coupon = $this->coupon->findOrFail($couponId);
        
        // Verificar si tiene usos registrados
        if ($coupon->usages()->exists()) {
            // Soft delete para mantener integridad referencial
            return $coupon->delete();
        }

        // Hard delete si no tiene usos
        return $coupon->forceDelete();
    }
}