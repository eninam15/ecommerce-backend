<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ShippingAddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;


use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::middleware(['auth:sanctum'])->group(function () {
        // Rutas que requieren autenticación

        Route::get('/cart', [CartController::class, 'show']);
        Route::post('cart/items', [CartController::class, 'addItem']);
        Route::patch('cart/items/{productId}', [CartController::class, 'updateQuantity']);
        Route::delete('cart/items/{productId}', [CartController::class, 'removeItem']);
        Route::delete('cart', [CartController::class, 'clear']);


        Route::apiResource('orders', OrderController::class);
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);

        Route::apiResource('shipping-addresses', ShippingAddressController::class);
        Route::post('shipping-addresses/{id}/default', [ShippingAddressController::class, 'setDefault']);

        Route::post('orders/{order}/payments', [PaymentController::class, 'initiate']);
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);

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

    Route::apiResource('/categories', CategoryController::class);
    Route::apiResource('/products', ProductController::class);
});
