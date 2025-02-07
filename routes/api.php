<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ShippingAddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RelatedProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {
        // Rutas que requieren autenticación


        Route::get('/cart', [CartController::class, 'getOrCreateCart']);
        Route::post('cart/items', [CartController::class, 'addItemtoCart']);
        Route::put('/cart/{productId}/update', [CartController::class, 'updateQuantityItemCart']);
        Route::delete('cart/items/{productId}', [CartController::class, 'removeItem']);
        Route::delete('cart', [CartController::class, 'clear']);


        Route::apiResource('orders', OrderController::class);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);

        Route::apiResource('shipping-addresses', ShippingAddressController::class);
        Route::post('shipping-addresses/{id}/default', [ShippingAddressController::class, 'setDefault']);

        Route::post('orders/{order}/payments', [PaymentController::class, 'initiate']);
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);

          // Blogs
        Route::apiResource('blogs', BlogController::class);
        Route::get('products/{product}/blogs', [BlogController::class, 'productBlogs']);

        // Reviews
        Route::post('reviews', [ReviewController::class, 'store']);
        Route::put('reviews/{review}', [ReviewController::class, 'update']);
        Route::get('products/{product}/reviews', [ReviewController::class, 'productReviews']);

        // Promotions
        Route::apiResource('promotions', PromotionController::class);
        Route::get('active-promotions', [PromotionController::class, 'getActivePromotions']);
        Route::get('products/{product}/promotions', [PromotionController::class, 'getProductPromotions']);

        // Related Products
        Route::post('related-products', [RelatedProductController::class, 'store']);
        Route::delete('products/{product}/related/{relatedProduct}', [RelatedProductController::class, 'destroy']);
        Route::get('products/{product}/related', [RelatedProductController::class, 'getRelatedProducts']);

        Route::middleware(['role:admin'])->group(function () {
            // Rutas solo para administradores
        });

        Route::middleware(['permission:create users'])->group(function () {
            // Rutas que requieren permiso específico
        });
    });

    // Rutas para webhooks (sin middleware de autenticación)
    Route::post('webhooks/payments/{provider}', [PaymentController::class, 'webhook']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::apiResource('banners', BannerController::class);


    Route::apiResource('/categories', CategoryController::class);
    Route::apiResource('/products', ProductController::class);
});
