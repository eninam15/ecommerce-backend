<?php
namespace App\Repositories\Interfaces;
use App\Dtos\CartItemData;


interface CartRepositoryInterface
{
    public function getOrCreateCart(string $userId);
    public function addItem(string $cartId, CartItemData $data);
    public function updateItemQuantity(string $cartId, string $productId, int $quantity);
    public function removeItem(string $cartId, string $productId);
    public function clear(string $cartId);
    public function getCart(string $cartId);
}
