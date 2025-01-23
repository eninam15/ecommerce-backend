<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\CartResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\CartService;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Dtos\CartItemData;


class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function show()
    {
        $cart = $this->cartService->getOrCreateCart(auth()->id());
        return new CartResource(
            $cart->load([
                'items.product' => function ($query) {
                    $query->with(['category', 'images']);
                }
            ])
        );
    }

    public function addItem(AddToCartRequest $request)
    {
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Es necesario autenticarse para realizar esta acción.',
            ], 401);
        }

        $cart = $this->cartService->addToCart(
            auth()->id(),
            CartItemData::fromRequest($request)
        );

        return new CartResource($cart->load('items.product'));
    }


    public function updateQuantity(UpdateCartItemRequest $request, string $productId)
    {
        $cart = $this->cartService->updateQuantity(
            auth()->id(),
            $productId,
            $request->quantity
        );
        return new CartResource($cart->load('items.product'));
    }

    public function removeItem(string $productId)
    {
        $cart = $this->cartService->removeFromCart(auth()->id(), $productId);
        return new CartResource($cart->load('items.product'));
    }

    public function clear()
    {
        $this->cartService->clearCart(auth()->id());
        return response()->noContent();
    }
}
