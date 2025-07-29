<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/banners', [BannerController::class, 'getBanners']);

// Public Product Routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/products/{product}/related', [ProductController::class, 'getRelatedProducts']);

// Public Category Routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// Public Blog Routes
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{blog}', [BlogController::class, 'show']);
Route::get('/products/{product}/blogs', [BlogController::class, 'productBlogs']);

// Payment Webhook
Route::post('webhooks/payments/{provider}', [PaymentController::class, 'webhook']);
Route::post('payments/ppe-webhook/{transactionCode}', [PaymentController::class, 'ppeWebhook']);

// ===== RUTAS PÚBLICAS DE STOCK (para frontend) =====
// Agregar a routes/api/public.php

Route::get('/products/{product}/stock-availability', function (string $productId, Request $request) {
    $stockService = app(\App\Services\StockService::class);
    $quantity = $request->input('quantity', 1);
    
    $availability = $stockService->checkAvailability($productId, $quantity);
    
    return response()->json([
        'available' => $availability->canFulfillRequest,
        'available_stock' => $availability->availableStock,
        'message' => $availability->canFulfillRequest 
            ? 'Stock disponible' 
            : "Solo quedan {$availability->availableStock} unidades disponibles"
    ]);
});

// Verificar validez de cupón público (para marketing)
Route::get('/coupons/{code}/check', function (string $code) {
    $coupon = \App\Models\Coupon::byCode($code)->first();
    
    if (!$coupon) {
        return response()->json([
            'valid' => false,
            'message' => 'Cupón no encontrado'
        ]);
    }
    
    return response()->json([
        'valid' => $coupon->isValid(),
        'name' => $coupon->name,
        'description' => $coupon->description,
        'discount_value' => $coupon->discount_value,
        'type' => $coupon->type,
        'minimum_amount' => $coupon->minimum_amount,
        'expires_at' => $coupon->expires_at
    ]);
});

// Tracking de clicks en emails (para analytics)
Route::get('/coupons/track/{notification}', function (string $notificationId) {
    $notification = \App\Models\CouponNotification::find($notificationId);
    
    if ($notification && $notification->status === 'sent') {
        $notification->markAsClicked();
    }
    
    // Redirigir a la tienda
    return redirect(config('app.frontend_url') . '/coupons');
});