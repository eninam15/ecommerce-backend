<?php

use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\ProductController as AdminProductController;
use App\Http\Controllers\Api\BannerController as AdminBannerController;
use App\Http\Controllers\Api\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Api\Admin\PromotionController as AdminPromotionController;
use Illuminate\Support\Facades\Route;

//Route::middleware(['auth:sanctum'])->middleware('role:admin,api')
Route::middleware(['auth:sanctum', \Spatie\Permission\Middleware\RoleMiddleware::class.':admin,api'])
->prefix('admin')->group(function () {
    // Orders Management
    Route::get('/orders', [AdminOrderController::class, 'getAllOrders']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'getOrderById']);
    Route::get('/orders/user/{id}', [AdminOrderController::class, 'getOrderByUser']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    // Product Management
    Route::apiResource('products', AdminProductController::class);
    Route::post('products/{product}/related', [AdminProductController::class, 'addRelatedProduct']);
    Route::post('products/bulk', [AdminProductController::class, 'bulkStore']);
    Route::delete('products/{product}/related/{relatedProduct}', [AdminProductController::class, 'removeRelatedProduct']);

    // Category Management
    Route::apiResource('categories', AdminCategoryController::class);

    // Banner Management
    Route::post('banners', [AdminBannerController::class, 'store']);

    // Blog Management
    Route::apiResource('blogs', AdminBlogController::class);

    // Promotion Management
    Route::apiResource('promotions', AdminPromotionController::class);
});
