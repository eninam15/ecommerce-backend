<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Services\ProductService;
use App\Dtos\CartItemData;

class CartService
{
    public function __construct(
        protected CartRepositoryInterface $cartRepository,
        protected ProductService $productService
    ) {}

    public function getOrCreateCart(string $userId)
    {
        return $this->cartRepository->getOrCreateCart($userId);
    }

    public function addToCart(string $userId, CartItemData $data)
    {
        DB::beginTransaction();

        try {
            $product = $this->productService->reserveStock($data->product_id, $data->quantity);

            $cart = $this->getOrCreateCart($userId);
            $cartItem = $this->cartRepository->addItem($cart->id, $data);

            DB::commit();

            return $cartItem;
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Error adding item to cart', 0, $e);
        }
    }

    public function updateQuantity(string $userId, string $productId, int $quantity, string $operation)
    {
        $cart = $this->getOrCreateCart($userId);
        return $this->cartRepository->updateItemQuantity($cart->id, $productId, $quantity, $operation);
    }

    public function removeFromCart(string $userId, string $productId)
    {
        $cart = $this->getOrCreateCart($userId);
        return $this->cartRepository->removeItem($cart->id, $productId);
    }

    public function clearCart(string $userId)
    {
        $cart = $this->getOrCreateCart($userId);
        return $this->cartRepository->clear($cart->id);
    }
}
