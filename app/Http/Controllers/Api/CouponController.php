<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use App\Services\CartService;
use App\Http\Requests\Coupon\ApplyCouponRequest;
use App\Http\Resources\CouponValidationResource;
use App\Http\Resources\CartResource;
use App\Dtos\ApplyCouponData;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
        protected CartService $cartService
    ) {}

    /**
     * Validar cupón antes de aplicar
     */
    public function validateCoupon(ApplyCouponRequest $request)
    {
        $cart = $this->cartService->getOrCreateCart(auth()->id());
        
        $cartItems = $cart->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'category_id' => $item->product->category_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
                'subtotal' => $item->price * $item->quantity
            ];
        })->toArray();

        $isFirstPurchase = !\App\Models\Order::where('user_id', auth()->id())
            ->whereIn('status', ['paid', 'delivered', 'completed'])
            ->exists();

        $validationData = new \App\Dtos\CouponValidationData(
            couponCode: $request->coupon_code,
            userId: auth()->id(),
            cartSubtotal: $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            }),
            cartItems: $cartItems,
            isFirstPurchase: $isFirstPurchase,
            cartId: $cart->id
        );

        $result = $this->couponService->validateCoupon($validationData);

        return new CouponValidationResource($result);
    }

    /**
     * Aplicar cupón al carrito
     */
    public function applyCoupon(ApplyCouponRequest $request)
    {
        $cart = $this->cartService->getOrCreateCart(auth()->id());

        $applyCouponData = new ApplyCouponData(
            couponCode: $request->coupon_code,
            userId: auth()->id(),
            cartId: $cart->id
        );

        $result = $this->couponService->applyCouponToCart($applyCouponData);

        if ($result->isValid) {
            $cart = $cart->fresh(['items.product', 'coupon']);
            return response()->json([
                'success' => true,
                'message' => 'Cupón aplicado correctamente',
                'cart' => new CartResource($cart),
                'discount_data' => new CouponValidationResource($result)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result->message,
            'validation_result' => new CouponValidationResource($result)
        ], 422);
    }

    /**
     * Remover cupón del carrito
     */
    public function removeCoupon()
    {
        $cart = $this->cartService->getOrCreateCart(auth()->id());

        $success = $this->couponService->removeCouponFromCart($cart->id);

        if ($success) {
            $cart = $cart->fresh(['items.product']);
            return response()->json([
                'success' => true,
                'message' => 'Cupón removido correctamente',
                'cart' => new CartResource($cart)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No hay cupón aplicado en el carrito'
        ], 422);
    }

    /**
     * Obtener cupones válidos para el usuario
     */
    public function getValidCoupons()
    {
        $coupons = $this->couponService->getValidCouponsForUser(auth()->id());

        return response()->json([
            'data' => \App\Http\Resources\CouponResource::collection($coupons),
            'message' => 'Cupones disponibles para tu cuenta'
        ]);
    }
}