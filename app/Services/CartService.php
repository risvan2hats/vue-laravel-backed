<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Repositories\CartRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly CartRepository $carts) {}

    public function show(User $user): Cart
    {
        return $this->carts->withItems($this->carts->forUser($user));
    }

    public function addItem(User $user, int $productId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {
            $cart = $this->carts->forUser($user);
            $product = $this->carts->product($productId, true);

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'The selected product is not available.',
                ]);
            }

            $item = $this->carts->itemForProduct($cart, $product->id, true);
            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            $this->assertAvailableQuantity($newQuantity, $product->stock);

            $item ??= new CartItem([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'unit_price' => $product->selling_price ?? $product->price,
            ]);
            $item->quantity = $newQuantity;
            $this->carts->saveItem($item);

            return $this->carts->withItems($cart);
        });
    }

    public function updateItem(User $user, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $itemId, $quantity) {
            $cart = $this->carts->forUser($user);
            $item = $this->carts->item($cart, $itemId, true);

            if ($item === null) {
                abort(404);
            }

            $product = $this->carts->product($item->product_id, true);

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'quantity' => 'This product is no longer available.',
                ]);
            }

            $this->assertAvailableQuantity($quantity, $product->stock);
            $item->quantity = $quantity;
            $this->carts->saveItem($item);

            return $this->carts->withItems($cart);
        });
    }

    public function removeItem(User $user, int $itemId): Cart
    {
        $cart = $this->carts->forUser($user);
        $item = $this->carts->item($cart, $itemId);

        if ($item === null) {
            abort(404);
        }

        $this->carts->deleteItem($item);

        return $this->carts->withItems($cart);
    }

    public function clear(User $user): Cart
    {
        $cart = $this->carts->forUser($user);
        $this->carts->clear($cart);

        return $this->carts->withItems($cart);
    }

    private function assertAvailableQuantity(int $quantity, int $stock): void
    {
        if ($quantity < 1 || $quantity > $stock) {
            throw ValidationException::withMessages([
                'quantity' => 'The requested quantity exceeds the available stock.',
            ]);
        }
    }
}
