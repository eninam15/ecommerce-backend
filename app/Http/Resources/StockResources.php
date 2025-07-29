<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'type' => $this->type,
            'reason' => $this->reason,
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reference_id' => $this->reference_id,
            'reference_type' => $this->reference_type,
            'expires_at' => $this->expires_at,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

class StockReservationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'cart_id' => $this->cart_id,
            'order_id' => $this->order_id,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'confirmed_at' => $this->confirmed_at,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

class StockAvailabilityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'product_id' => $this->productId,
            'total_stock' => $this->totalStock,
            'available_stock' => $this->availableStock,
            'reserved_stock' => $this->reservedStock,
            'committed_stock' => $this->committedStock,
            'is_available' => $this->isAvailable,
            'has_low_stock' => $this->hasLowStock,
            'requested_quantity' => $this->requestedQuantity,
            'can_fulfill_request' => $this->canFulfillRequest,
            'stock_status' => $this->getStockStatus(),
            'availability_message' => $this->getAvailabilityMessage()
        ];
    }

    protected function getStockStatus(): string
    {
        if ($this->resource->totalStock <= 0) {
            return 'out_of_stock';
        }
        
        if ($this->resource->hasLowStock) {
            return 'low_stock';
        }
        
        if ($this->resource->availableStock <= 0) {
            return 'reserved';
        }
        
        return 'in_stock';
    }

    protected function getAvailabilityMessage(): string
    {
        return match($this->getStockStatus()) {
            'out_of_stock' => 'Producto sin stock',
            'low_stock' => 'Stock bajo',
            'reserved' => 'Stock completamente reservado',
            'in_stock' => 'Stock disponible',
            default => 'Estado desconocido'
        };
    }
}

class StockReportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'product' => new ProductResource($this->resource['product']),
            'availability' => new StockAvailabilityResource($this->resource['availability']),
            'recent_movements' => StockMovementResource::collection($this->resource['recent_movements']),
            'active_reservations' => StockReservationResource::collection($this->resource['active_reservations']),
            'stock_turnover' => round($this->resource['stock_turnover'], 2),
            'reorder_point' => $this->resource['reorder_point'],
            'recommendations' => $this->getRecommendations()
        ];
    }

    protected function getRecommendations(): array
    {
        $recommendations = [];
        $availability = $this->resource['availability'];

        if ($availability->hasLowStock) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Stock bajo - considerar reposición',
                'action' => 'restock'
            ];
        }

        if ($availability->totalStock <= 0) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Producto sin stock',
                'action' => 'urgent_restock'
            ];
        }

        if ($this->resource['stock_turnover'] > 2) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Alta rotación de stock - considerar aumentar stock mínimo',
                'action' => 'increase_min_stock'
            ];
        }

        return $recommendations;
    }
}