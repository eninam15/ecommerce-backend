<?php

namespace App\Services;

use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;
use App\Dtos\CouponUsageData;
use App\Services\StockService;
use App\Services\CartService;
use App\Services\CouponService;
use App\Enums\StockMovementReason;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\OrderRepositoryInterface;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository,
        protected StockService $stockService,
        protected CartService $cartService,
        protected CouponService $couponService
        //protected NotificationService $notificationService
    ) {}

    public function createOrder(string $userId, OrderData $data)
    {
        return DB::transaction(function () use ($userId, $data) {
            // Validar stock del carrito antes de proceder
            $stockIssues = $this->cartService->validateCartStock($userId);
            
            if (!empty($stockIssues)) {
                throw new \Exception('Stock insuficiente para algunos productos: ' . json_encode($stockIssues));
            }

            // Obtener carrito con cupón aplicado
            $cart = $this->cartService->getOrCreateCart($userId);
            $cart->load(['items.product', 'coupon']);

            // Validar cupón aplicado si existe
            if ($cart->coupon_id) {
                $this->validateCartCoupon($cart, $userId);
            }

            // Crear la orden con información del cupón
            $orderData = $this->prepareOrderDataWithCoupon($data, $cart);
            $order = $this->orderRepository->create($userId, $orderData);

            // Registrar uso del cupón si aplica
            if ($cart->coupon_id) {
                $this->couponService->recordCouponUsage(new CouponUsageData(
                    couponId: $cart->coupon_id,
                    userId: $userId,
                    orderId: $order->id,
                    discountAmount: $cart->coupon_discount
                ));
            }

            // Convertir reservas de carrito a reservas de orden
            $this->cartService->convertCartReservationsToOrder($cart->id, $order->id);

            // Limpiar carrito
            $this->cartService->clearCart($userId);

            // Enviar notificaciones
            //$this->notificationService->sendOrderConfirmation($order);

            return $order->load(['coupon']);
        });
    }

    public function updateOrderStatus(string $orderId, OrderStatusData $data)
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = $this->orderRepository->update($orderId, $data);

            // Gestionar stock según el nuevo estado
            $this->handleStockByOrderStatus($order, $data->status);

            // Manejar cancelación con cupón
            if ($data->status === OrderStatus::CANCELLED) {
                $this->handleCancelledOrderCoupon($order);
            }

            // Enviar notificaciones según el estado
            //$this->notificationService->sendOrderStatusUpdate($order);

            return $order;
        });
    }

    public function cancelOrder(string $orderId, string $reason = null): bool
    {
        return DB::transaction(function () use ($orderId, $reason) {
            $order = $this->orderRepository->find($orderId);
            
            if (!$order) {
                throw new \Exception('Orden no encontrada');
            }

            if (in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::CANCELLED])) {
                throw new \Exception('La orden no puede ser cancelada');
            }

            // Liberar stock reservado
            $this->releaseOrderStock($orderId, StockMovementReason::ORDER_CANCEL);

            // Manejar cupón usado (decrementar contador si es necesario)
            $this->handleCancelledOrderCoupon($order);

            // Actualizar estado de la orden
            $orderStatusData = new OrderStatusData([
                'status' => OrderStatus::CANCELLED,
                'comment' => $reason ?? 'Orden cancelada'
            ]);

            $this->orderRepository->update($orderId, $orderStatusData);

            return true;
        });
    }

    public function confirmPayment(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->orderRepository->find($orderId);
            
            if (!$order) {
                throw new \Exception('Orden no encontrada');
            }

            // Confirmar todas las reservas de stock (reducir stock real)
            $this->confirmOrderStock($orderId);

            // El cupón ya fue registrado al crear la orden, no hay que hacer nada más

            // Actualizar estado de la orden
            $orderStatusData = new OrderStatusData([
                'status' => OrderStatus::PAID,
                'comment' => 'Pago confirmado, stock reducido'
            ]);

            $this->updateOrderStatus($orderId, $orderStatusData);

            return true;
        });
    }

    public function processReturn(string $orderId, array $returnItems, string $reason = null): bool
    {
        return DB::transaction(function () use ($orderId, $returnItems, $reason) {
            $order = $this->orderRepository->find($orderId);
            
            if (!$order || $order->status !== OrderStatus::DELIVERED) {
                throw new \Exception('La orden no es elegible para devolución');
            }

            // Restituir stock para cada item devuelto
            foreach ($returnItems as $item) {
                $this->stockService->adjustStock(
                    $item['product_id'],
                    $this->getCurrentStock($item['product_id']) + $item['quantity'],
                    "Devolución de orden {$orderId}: {$reason}"
                );
            }

            // Para devoluciones completas, considerar si devolver el cupón
            $isCompleteReturn = $this->isCompleteReturn($order, $returnItems);
            if ($isCompleteReturn && $order->coupon_id) {
                $this->handleReturnedOrderCoupon($order);
            }

            // Registrar la devolución en el historial de la orden
            $orderStatusData = new OrderStatusData([
                'status' => OrderStatus::REFUNDED,
                'comment' => "Devolución procesada: {$reason}"
            ]);

            $this->addOrderStatusHistory($orderId, $orderStatusData, auth()->id());

            return true;
        });
    }

    // ===== MÉTODOS EXISTENTES =====

    public function findOrder(string $orderId)
    {
        return $this->orderRepository->find($orderId);
    }

    public function getUserOrders(string $userId)
    {
        return $this->orderRepository->getUserOrders($userId);
    }

    public function addOrderStatusHistory(string $orderId, OrderStatusData $data, string $userId)
    {
        return $this->orderRepository->addStatusHistory($orderId, $data, $userId);
    }

    public function getAllOrders(?string $status = null, ?string $dateFrom = null,
    ?string $dateTo = null, ?string $search = null, int $perPage = 15)
    {
        return $this->orderRepository->getAllOrders($status, $dateFrom, $dateTo, $search, $perPage);
    }

    public function findOrderWithDetails(string $orderId)
    {
        return $this->orderRepository->findWithDetails($orderId);
    }

    public function updateOrderStatusAsAdmin(string $orderId, OrderStatusData $data, string $adminId)
    {
        $order = $this->orderRepository->update($orderId, $data);
        $this->addOrderStatusHistory($orderId, $data, $adminId);

        $this->handleStockByOrderStatus($order, $data->status);

        return $order;
    }

    // ===== MÉTODOS PRIVADOS PARA CUPONES =====

    /**
     * Validar que el cupón del carrito sigue siendo válido
     */
    protected function validateCartCoupon($cart, string $userId): void
    {
        if (!$cart->coupon || !$cart->coupon->isValid()) {
            throw new \Exception('El cupón aplicado ya no es válido');
        }

        // Verificar que el usuario aún puede usar el cupón
        if (!$cart->coupon->canBeUsedBy($userId)) {
            throw new \Exception('Has excedido el límite de uso de este cupón');
        }
    }

    /**
     * Preparar datos de orden con información del cupón
     */
    protected function prepareOrderDataWithCoupon(OrderData $originalData, $cart): OrderData
    {
        $baseSubtotal = $cart->subtotal ?? $cart->total;
        $couponDiscount = $cart->coupon_discount ?? 0;
        $tax = $baseSubtotal * 0.16; // 16% de impuestos
        $shippingCost = 0;
        
        // Si hay cupón de envío gratis
        if ($cart->coupon && $cart->coupon->type === \App\Enums\CouponType::FREE_SHIPPING) {
            $shippingCost = 0;
        }

        $total = $baseSubtotal + $tax + $shippingCost - $couponDiscount;

        return new OrderData([
            'shipping_address_id' => $originalData->shipping_address_id,
            'notes' => $originalData->notes,
            'coupon_id' => $cart->coupon_id,
            'coupon_code' => $cart->coupon_code,
            'coupon_discount' => $couponDiscount,
            'subtotal' => $baseSubtotal,
            'tax' => $tax,
            'shipping_cost' => $shippingCost,
            'total' => $total
        ]);
    }

    /**
     * Manejar cupón de orden cancelada
     */
    protected function handleCancelledOrderCoupon($order): void
    {
        if (!$order->coupon_id) {
            return;
        }

        // Decrementar el contador de uso del cupón
        $coupon = \App\Models\Coupon::find($order->coupon_id);
        if ($coupon && $coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }

        // Eliminar el registro de uso
        \App\Models\CouponUsage::where('order_id', $order->id)->delete();
    }

    /**
     * Manejar cupón de orden devuelta
     */
    protected function handleReturnedOrderCoupon($order): void
    {
        if (!$order->coupon_id) {
            return;
        }

        // Para devoluciones completas, decrementar contador
        $coupon = \App\Models\Coupon::find($order->coupon_id);
        if ($coupon && $coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }
    }

    /**
     * Verificar si es una devolución completa
     */
    protected function isCompleteReturn($order, array $returnItems): bool
    {
        $totalOrderQuantity = $order->items->sum('quantity');
        $totalReturnQuantity = collect($returnItems)->sum('quantity');
        
        return $totalOrderQuantity === $totalReturnQuantity;
    }

    // ===== MÉTODOS EXISTENTES DE STOCK =====

    protected function handleStockByOrderStatus($order, OrderStatus $newStatus): void
    {
        switch ($newStatus) {
            case OrderStatus::PAID:
                $this->confirmOrderStock($order->id);
                break;
                
            case OrderStatus::CANCELLED:
                $this->releaseOrderStock($order->id, StockMovementReason::ORDER_CANCEL);
                break;
                
            case OrderStatus::DELIVERED:
                break;
                
            case OrderStatus::REFUNDED:
                break;
        }
    }

    protected function confirmOrderStock(string $orderId): void
    {
        $reservations = \App\Models\StockReservation::where('order_id', $orderId)
            ->where('status', 'active')
            ->get();

        foreach ($reservations as $reservation) {
            $this->stockService->confirmReservation($reservation->id);
        }
    }

    protected function releaseOrderStock(string $orderId, StockMovementReason $reason): void
    {
        $reservations = \App\Models\StockReservation::where('order_id', $orderId)
            ->where('status', 'active')
            ->get();

        foreach ($reservations as $reservation) {
            $this->stockService->releaseReservation($reservation->id, $reason);
        }
    }

    protected function getCurrentStock(string $productId): int
    {
        return \App\Models\Product::find($productId)->stock ?? 0;
    }
}