<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use App\Http\Requests\Stock\StockAdjustmentRequest;
use App\Http\Resources\StockReportResource;
use App\Http\Resources\ProductResource;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected ProductService $productService
    ) {}

    /**
     * Obtener reporte de stock de un producto
     */
    public function getStockReport(string $productId)
    {
        $report = $this->stockService->getStockReport($productId);
        
        return response()->json([
            'data' => $report
        ]);
    }

    /**
     * Ajustar stock manualmente
     */
    public function adjustStock(StockAdjustmentRequest $request, string $productId)
    {
        $success = $this->stockService->adjustStock(
            $productId,
            $request->new_stock,
            $request->reason
        );

        if ($success) {
            $report = $this->stockService->getStockReport($productId);
            
            return response()->json([
                'message' => 'Stock ajustado correctamente',
                'data' => $report
            ]);
        }

        return response()->json([
            'message' => 'Error al ajustar stock'
        ], 500);
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockProducts()
    {
        $products = $this->productService->getLowStockProducts();
        
        return ProductResource::collection($products);
    }

    /**
     * Obtener historial de movimientos de stock
     */
    public function getStockHistory(string $productId, Request $request)
    {
        $limit = $request->input('limit', 50);
        $movements = $this->stockService->getStockHistory($productId, $limit);
        
        return response()->json([
            'data' => $movements
        ]);
    }

    /**
     * Liberar reservas expiradas manualmente
     */
    public function releaseExpiredReservations()
    {
        $releasedCount = $this->stockService->releaseExpiredReservations();
        
        return response()->json([
            'message' => "Se liberaron {$releasedCount} reservas expiradas",
            'released_count' => $releasedCount
        ]);
    }

    /**
     * Verificar disponibilidad de stock
     */
    public function checkAvailability(string $productId, Request $request)
    {
        $quantity = $request->input('quantity', 1);
        $availability = $this->stockService->checkAvailability($productId, $quantity);
        
        return response()->json([
            'data' => $availability
        ]);
    }

    /**
     * Dashboard de stock general
     */
    public function stockDashboard()
    {
        $lowStockProducts = $this->productService->getLowStockProducts();
        $totalProducts = \App\Models\Product::count();
        $outOfStockProducts = \App\Models\Product::where('stock', '<=', 0)->count();
        $lowStockCount = $lowStockProducts->count();
        
        // Reservas activas totales
        $activeReservations = \App\Models\StockReservation::where('status', 'active')->count();
        $expiredReservations = \App\Models\StockReservation::where('status', 'active')
            ->where('expires_at', '<', now())->count();

        return response()->json([
            'data' => [
                'total_products' => $totalProducts,
                'out_of_stock_count' => $outOfStockProducts,
                'low_stock_count' => $lowStockCount,
                'active_reservations' => $activeReservations,
                'expired_reservations' => $expiredReservations,
                'low_stock_products' => ProductResource::collection($lowStockProducts->take(10))
            ]
        ]);
    }
}