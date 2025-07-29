<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\CouponService;
use App\Dtos\CartItemData;
use App\Enums\StockMovementReason;

class CartService
{
    public function __construct(
        protected CartRepositoryInterface $cartRepository,
        protected ProductService $productService,
        protected StockService $stockService,
        protected CouponService $couponService
    ) {}

    public function getOrCreateCart(string $userId)
    {
        return $this->cartRepository->getOrCreateCart($userId);
    }

    public function getCartProductIds(string $userId)
    {
        return $this->cartRepository->getCartProductIds($userId);
    }

    public function addToCart(string $userId, CartItemData $data)
    {
        DB::beginTransaction();

        try {
            // Verificar y reservar stock
            $product = $this->productService->reserveStock(
                $data->product_id, 
                $data->quantity, 
                $userId,
                null
            );

            // Obtener o crear carrito
            $cart = $this->getOrCreateCart($userId);
            
            // Actualizar la reserva con el cart_id
            $this->updateReservationWithCartId($data->product_id, $userId, $cart->id);

            // Agregar item al carrito
            $cartItem = $this->cartRepository->addItem($cart->id, $data);

            // Recalcular totales del carrito (incluyendo cupón si aplica)
            $this->recalculateCartTotals($cart);

            DB::commit();

            return $cartItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error adding item to cart: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateQuantity(string $userId, string $productId, int $quantity, string $operation)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($userId);
            $currentItem = $cart->items()->where('product_id', $productId)->first();

            if (!$currentItem) {
                throw new \Exception("Producto no encontrado en el carrito");
            }

            $newQuantity = match($operation) {
                'add' => $currentItem->quantity + $quantity,
                'subtract' => $currentItem->quantity - $quantity,
                'set' => $quantity,
                default => throw new \Exception("Operación no válida")
            };

            if ($newQuantity <= 0) {
                return $this->removeFromCart($userId, $productId);
            }

            $quantityDifference = $newQuantity - $currentItem->quantity;

            if ($quantityDifference > 0) {
                // Aumentar cantidad - necesita reservar más stock
                $this->productService->reserveStock(
                    $productId, 
                    $quantityDifference, 
                    $userId, 
                    $cart->id
                );
            } elseif ($quantityDifference < 0) {
                // Reducir cantidad - liberar parte del stock reservado
                $this->releasePartialReservation(
                    $productId, 
                    $cart->id, 
                    abs($quantityDifference)
                );
            }

            // Actualizar item en el carrito
            $cartItem = $this->cartRepository->updateItemQuantity(
                $cart->id, 
                $productId, 
                $newQuantity, 
                'set'
            );

            // Recalcular totales del carrito
            $this->recalculateCartTotals($cart);

            DB::commit();

            return $cartItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error updating cart item: ' . $e->getMessage(), 0, $e);
        }
    }

    public function removeFromCart(string $userId, string $productId)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($userId);
            
            // Liberar todas las reservas de este producto en este carrito
            $this->stockService->releaseCartReservations($cart->id);
            
            // Remover del carrito
            $cart = $this->cartRepository->removeItem($cart->id, $productId);

            // Recalcular totales del carrito
            $this->recalculateCartTotals($cart);

            DB::commit();

            return $cart;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error removing item from cart: ' . $e->getMessage(), 0, $e);
        }
    }

    public function clearCart(string $userId)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($userId);
            
            // Liberar todas las reservas del carrito
            $this->stockService->releaseCartReservations($cart->id);
            
            // Remover cupón si está aplicado
            if ($cart->coupon_id) {
                $this->couponService->removeCouponFromCart($cart->id);
            }
            
            // Limpiar carrito
            $result = $this->cartRepository->clear($cart->id);

            DB::commit();

            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error clearing cart: ' . $e->getMessage(), 0, $e);
        }
    }

    // ===== MÉTODOS DE CUPONES =====

    /**
     * Recalcular totales del carrito (incluyendo cupón)
     */
    public function recalculateCartTotals($cart): void
    {
        $cart = $cart->fresh(['items.product', 'coupon']);
        
        // Calcular subtotal base
        $subtotal = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        // Si hay cupón aplicado, recalcular descuento
        if ($cart->coupon_id && $cart->coupon) {
            $coupon = $cart->coupon;
            
            // Verificar si el cupón sigue siendo válido
            if (!$coupon->isValid()) {
                // Remover cupón inválido
                $cart->update([
                    'coupon_id' => null,
                    'coupon_code' => null,
                    'coupon_discount' => 0,
                    'subtotal' => $subtotal,
                    'total' => $subtotal
                ]);
                return;
            }

            // Obtener items aplicables
            $cartItems = $cart->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'category_id' => $item->product->category_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'subtotal' => $item->price * $item->quantity
                ];
            })->toArray();

            $applicableItems = $this->getApplicableItemsForCoupon($coupon, $cartItems);
            $discountAmount = $coupon->calculateDiscount($subtotal, $applicableItems);

            $cart->update([
                'coupon_discount' => $discountAmount,
                'subtotal' => $subtotal,
                'total' => $subtotal - $discountAmount
            ]);
        } else {
            // Sin cupón
            $cart->update([
                'subtotal' => $subtotal,
                'total' => $subtotal
            ]);
        }
    }

    /**
     * Obtener items aplicables para un cupón
     */
    protected function getApplicableItemsForCoupon($coupon, array $cartItems): array
    {
        if ($coupon->type === \App\Enums\CouponType::PRODUCT_DISCOUNT) {
            $applicableProductIds = $coupon->products->pluck('id')->toArray();
            return array_filter($cartItems, function ($item) use ($applicableProductIds) {
                return in_array($item['product_id'], $applicableProductIds);
            });
        }

        if ($coupon->type === \App\Enums\CouponType::CATEGORY_DISCOUNT) {
            $applicableCategoryIds = $coupon->categories->pluck('id')->toArray();
            return array_filter($cartItems, function ($item) use ($applicableCategoryIds) {
                return in_array($item['category_id'], $applicableCategoryIds);
            });
        }

        return $cartItems; // Para otros tipos, todos los items son aplicables
    }

    // ===== MÉTODOS EXISTENTES DE STOCK =====

    public function releaseExpiredCartReservations(): int
    {
        return $this->stockService->releaseExpiredReservations();
    }

    public function validateCartStock(string $userId): array
    {
        $cart = $this->getOrCreateCart($userId);
        $issues = [];

        foreach ($cart->items as $item) {
            $availability = $this->productService->checkStockAvailability(
                $item->product_id, 
                $item->quantity
            );

            if (!$availability->canFulfillRequest) {
                $issues[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'requested' => $item->quantity,
                    'available' => $availability->availableStock,
                    'message' => "Stock insuficiente para {$item->product->name}"
                ];
            }
        }

        return $issues;
    }

    public function convertCartReservationsToOrder(string $cartId, string $orderId): bool
    {
        return DB::transaction(function () use ($cartId, $orderId) {
            $reservations = $this->stockService->getCartReservations($cartId);
            
            foreach ($reservations as $reservation) {
                $reservation->update([
                    'order_id' => $orderId,
                    'cart_id' => null,
                    'expires_at' => now()->addHours(24)
                ]);
            }

            return true;
        });
    }

    protected function releasePartialReservation(string $productId, string $cartId, int $quantityToRelease)
    {
        $currentReservations = $this->stockService->getCartReservations($cartId)
            ->where('product_id', $productId)
            ->where('status', 'active');

        $totalReserved = $currentReservations->sum('quantity');
        $newReservationQuantity = $totalReserved - $quantityToRelease;

        foreach ($currentReservations as $reservation) {
            $this->stockService->releaseReservation(
                $reservation->id, 
                StockMovementReason::CART_REMOVE
            );
        }

        if ($newReservationQuantity > 0) {
            $this->productService->reserveStock(
                $productId, 
                $newReservationQuantity, 
                auth()->id(), 
                $cartId
            );
        }
    }

    protected function updateReservationWithCartId(string $productId, string $userId, string $cartId)
    {
        $reservation = \App\Models\StockReservation::where('product_id', $productId)
            ->where('user_id', $userId)
            ->whereNull('cart_id')
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($reservation) {
            $reservation->update(['cart_id' => $cartId]);
        }
    }
}