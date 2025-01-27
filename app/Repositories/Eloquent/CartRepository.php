<?php
namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Cart;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Dtos\CartItemData;
use Illuminate\Support\Str;

class CartRepository implements CartRepositoryInterface
{
    public function __construct(
        protected Cart $cart,
        protected Product $product
    ) {}

    public function getOrCreateCart(string $userId)
    {
        return $this->cart->firstOrCreate(
            ['user_id' => $userId],
            ['total' => 0]
        );
    }

    public function addItem(string $cartId, CartItemData $data)
    {
        $cart = $this->cart->findOrFail($cartId);
        $product = $this->product->findOrFail($data->product_id);

        $existingItem = $cart->items()
            ->where('product_id', $data->product_id)
            ->first();

        if ($existingItem) {
            $existingItem->update([
                'quantity' => $existingItem->quantity + $data->quantity
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $data->product_id,
                'quantity' => $data->quantity,
                'price' => $product->price
            ]);
        }

        $this->updateCartTotal($cart);
        return $cart->load('items.product');
    }

    public function updateItemQuantity(string $cartId, string $productId, int $quantity, string $operation)
    {
        $cart = $this->cart->findOrFail($cartId);
        $cartItem = $cart->items()->where('product_id', $productId)->firstOrFail();

        switch ($operation) {
            case 'add':
                $cartItem->quantity += $quantity;
                break;
            case 'subtract':
                $cartItem->quantity -= $quantity;
                if ($cartItem->quantity < 1) {
                    $cartItem->delete();
                    //return response()->json(['message' => 'Producto eliminado del carrito'], 200);
                }
                break;
            case 'replace':
            default:
                $cartItem->quantity = $quantity;
                break;
        }

        $cartItem->save();

        $this->updateCartTotal($cart);

        return $cart->load('items.product');
    }

    public function removeItem(string $cartId, string $productId)
    {
        $cart = $this->cart->findOrFail($cartId);
        $cart->items()->where('product_id', $productId)->delete();

        $this->updateCartTotal($cart);

        return $cart->load('items.product');
    }

    public function clear(string $cartId)
    {
        $cart = $this->cart->findOrFail($cartId);
        $cart->items()->delete();
        $cart->update(['total' => 0]);

        return $cart;
    }

    public function getCart(string $cartId)
    {
        return $this->cart->with('items.product')->findOrFail($cartId);
    }

    private function updateCartTotal(Cart $cart)
    {
        $total = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });

        $cart->update(['total' => $total]);
    }
}
