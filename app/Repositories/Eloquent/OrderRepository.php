<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Enums\OrderStatus;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Dtos\OrderData;
use App\Dtos\OrderStatusData;
use App\Services\CartService;
use App\Services\ProductService;

class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        protected Order $order,
        protected CartService $cartService,
        protected ProductService $productService
    ) {}

    public function create(string $userId, OrderData $data)
    {
        DB::beginTransaction();

        try {
            $cart = $this->cartService->getOrCreateCart($userId);



            $order = $this->order->create([
                'user_id' => $userId,
                'shipping_address_id' => $data->shipping_address_id,
                'subtotal' => $cart->total,
                'tax' => $cart->total * 0.16,
                'shipping_cost' => 0,
                'total' => $cart->total + ($cart->total * 0.16),
                'status' => OrderStatus::PENDING,
                'notes' => $data->notes
            ]);

            foreach ($cart->items as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->price,
                    'subtotal' => $item->price * $item->quantity
                ]);

                /*$this->productService->updateStock(
                    $item->product_id,
                    -$item->quantity
                );*/
            }

            $this->addStatusHistory(
                $order->id,
                new OrderStatusData([
                    'status' => OrderStatus::PENDING,
                    'comment' => 'Order created'
                ]),
                $userId
            );

            $this->cartService->clearCart($userId);

            DB::commit();

            return $order->load(['items.product', 'shippingAddress', 'statusHistories']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(string $id, OrderStatusData $data)
    {
        $order = $this->order->findOrFail($id);

        $order->update([
            'status' => $data->status,
            'notes' => $data->comment ?? $order->notes
        ]);

        return $order;
    }

    public function find(string $id)
    {
        return $this->order->with([
            'items.product',
            'shippingAddress',
            'statusHistories',
            'user'
        ])->findOrFail($id);
    }

    public function getUserOrders(string $userId)
    {
        return $this->order->where('user_id', $userId)
            ->with(['items.product', 'statusHistories'])
            ->latest()
            ->get();
    }

    public function addStatusHistory(string $orderId, OrderStatusData $data, string $userId)
    {
        return OrderStatusHistory::create([
            'order_id' => $orderId,
            'status' => $data->status,
            'comment' => $data->comment,
            'created_by' => $userId
        ]);
    }
}
