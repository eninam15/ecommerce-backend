<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ShippingAddressController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('coupons')->group(function () {
        // Validar cupón antes de aplicar
        Route::post('/validate', [CouponController::class, 'validateCoupon']);
        
        // Aplicar cupón al carrito
        Route::post('/apply', [CouponController::class, 'applyCoupon']);
        
        // Remover cupón del carrito
        Route::delete('/remove', [CouponController::class, 'removeCoupon']);
        
        // Obtener cupones válidos para el usuario
        Route::get('/available', [CouponController::class, 'getValidCoupons']);
    });
    
    // Cart Routes
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'getOrCreateCart']);
        Route::post('/items', [CartController::class, 'addItemtoCart']);
        Route::put('/{productId}/update', [CartController::class, 'updateQuantityItemCart']);
        Route::delete('/items/{productId}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    // Order Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
    });

    // Shipping Address Routes
    Route::apiResource('shipping-addresses', ShippingAddressController::class);
    Route::post('shipping-addresses/{id}/default', [ShippingAddressController::class, 'setDefault']);

    // Payment Routes
    Route::post('orders/{order}/payments', [PaymentController::class, 'initiate']);
    Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);

    // Review Routes
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::put('reviews/{review}', [ReviewController::class, 'update']);
    Route::get('products/{product}/reviews', [ReviewController::class, 'productReviews']);
});
