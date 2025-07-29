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

    // ===== STOCK MANAGEMENT ROUTES =====
    
    // Dashboard de stock
    Route::get('/stock/dashboard', [StockController::class, 'stockDashboard']);
    
    // Productos con stock bajo
    Route::get('/stock/low-stock', [StockController::class, 'getLowStockProducts']);
    
    // Reporte de stock específico de producto
    Route::get('/stock/products/{product}/report', [StockController::class, 'getStockReport']);
    
    // Historial de movimientos de stock
    Route::get('/stock/products/{product}/history', [StockController::class, 'getStockHistory']);
    
    // Verificar disponibilidad
    Route::get('/stock/products/{product}/availability', [StockController::class, 'checkAvailability']);
    
    // Ajustar stock manualmente
    Route::patch('/stock/products/{product}/adjust', [StockController::class, 'adjustStock']);
    
    // Liberar reservas expiradas manualmente
    Route::post('/stock/cleanup-expired', [StockController::class, 'releaseExpiredReservations']);
    

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

    // ===== GESTIÓN COMPLETA DE CUPONES =====
    Route::apiResource('coupons', AdminCouponController::class);
    
    // Rutas adicionales para cupones
    Route::prefix('coupons')->group(function () {
        // Dashboard de cupones
        Route::get('/dashboard', [AdminCouponController::class, 'dashboard']);
        
        // Estadísticas generales
        Route::get('/stats', [AdminCouponController::class, 'stats']);
        
        // Generar código único
        Route::post('/generate-code', [AdminCouponController::class, 'generateCode']);
        
        // Obtener tipos de cupones disponibles
        Route::get('/types', [AdminCouponController::class, 'getTypes']);
        
        // Duplicar cupón
        Route::post('/{coupon}/duplicate', [AdminCouponController::class, 'duplicate']);
        
        // Activar/Desactivar cupón
        Route::patch('/{coupon}/toggle-status', [AdminCouponController::class, 'toggleStatus']);
        
        // Obtener usos de un cupón específico
        Route::get('/{coupon}/usages', [AdminCouponController::class, 'getUsages']);

        // ===== ANALYTICS AVANZADOS DE CUPONES =====
        Route::prefix('coupons/analytics')->group(function () {
            // Dashboard principal de analytics
            Route::get('/dashboard', [CouponAnalyticsController::class, 'dashboard']);
            
            // Análisis de segmentación de usuarios
            Route::get('/user-segmentation', [CouponAnalyticsController::class, 'userSegmentation']);
            
            // Efectividad por canal
            Route::get('/channel-effectiveness', [CouponAnalyticsController::class, 'channelEffectiveness']);
            
            // Predicciones y recomendaciones
            Route::get('/predictions', [CouponAnalyticsController::class, 'predictions']);
            
            // Generar reporte detallado
            Route::post('/report', [CouponAnalyticsController::class, 'generateReport']);
            
            // Comparar períodos
            Route::post('/compare-periods', [CouponAnalyticsController::class, 'comparePerios']);
            
            // Métricas en tiempo real
            Route::get('/realtime', [CouponAnalyticsController::class, 'realTimeMetrics']);
            
            // A/B testing results (futuro)
            Route::get('/ab-tests', [CouponAnalyticsController::class, 'abTestResults']);
        });

        // ===== AUTOMATIZACIÓN DE CUPONES =====
        Route::prefix('coupons/automation')->group(function () {
            // Disparar manualmente generación de cupones automáticos
            Route::post('/generate-abandoned-cart', function () {
                \App\Jobs\GenerateAbandonedCartCouponsJob::dispatch();
                return response()->json(['message' => 'Job de carritos abandonados iniciado']);
            });
            
            Route::post('/generate-loyalty', function () {
                \App\Jobs\GenerateLoyaltyCouponsJob::dispatch();
                return response()->json(['message' => 'Job de cupones de fidelidad iniciado']);
            });
            
            Route::post('/generate-seasonal', function () {
                \App\Jobs\GenerateSeasonalCouponsJob::dispatch();
                return response()->json(['message' => 'Job de cupones estacionales iniciado']);
            });
            
            Route::post('/send-expiring-notifications', function () {
                \App\Jobs\SendExpiringCouponsNotificationJob::dispatch();
                return response()->json(['message' => 'Job de notificaciones de expiración iniciado']);
            });
            
            // Procesar notificaciones pendientes manualmente
            Route::post('/process-notifications', function () {
                \App\Jobs\ProcessScheduledNotificationsJob::dispatch();
                return response()->json(['message' => 'Job de procesamiento de notificaciones iniciado']);
            });
        });
    });
});
