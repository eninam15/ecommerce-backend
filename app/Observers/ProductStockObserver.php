<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Enums\StockMovementType;
use App\Enums\StockMovementReason;
use Illuminate\Support\Facades\Log;

class ProductStockObserver
{
    /**
     * Handle the Product "updating" event.
     */
    public function updating(Product $product): void
    {
        // Solo procesar si el stock está cambiando
        if ($product->isDirty('stock')) {
            $oldStock = $product->getOriginal('stock');
            $newStock = $product->stock;
            $difference = $newStock - $oldStock;

            // Registrar el movimiento automáticamente
            if ($difference !== 0) {
                $this->recordStockMovement($product, $oldStock, $newStock, $difference);
            }

            // Alertas de stock bajo
            if ($newStock <= $product->min_stock && $oldStock > $product->min_stock) {
                Log::warning("Producto con stock bajo detectado", [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $newStock,
                    'min_stock' => $product->min_stock
                ]);

                // Aquí podrías disparar eventos o notificaciones
                // event(new LowStockAlert($product));
            }

            // Alerta de stock agotado
            if ($newStock <= 0 && $oldStock > 0) {
                Log::error("Producto sin stock", [
                    'product_id' => $product->id,
                    'product_name' => $product->name
                ]);

                // event(new OutOfStockAlert($product));
            }
        }
    }

    /**
     * Registrar movimiento de stock automáticamente
     */
    protected function recordStockMovement(Product $product, int $oldStock, int $newStock, int $difference): void
    {
        try {
            StockMovement::create([
                'product_id' => $product->id,
                'type' => $difference > 0 ? StockMovementType::RESTOCK->value : StockMovementType::REDUCE->value,
                'reason' => StockMovementReason::MANUAL_ADJUSTMENT->value,
                'quantity' => abs($difference),
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'notes' => 'Cambio automático detectado por Observer',
                'created_by' => auth()->id()
            ]);

            Log::info("Movimiento de stock registrado automáticamente", [
                'product_id' => $product->id,
                'type' => $difference > 0 ? 'restock' : 'reduce',
                'quantity' => abs($difference),
                'old_stock' => $oldStock,
                'new_stock' => $newStock
            ]);

        } catch (\Exception $e) {
            Log::error("Error registrando movimiento automático de stock", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}