<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
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

    public function addItem(User $user, int $productId, int $quantity, ?int $variantId = null): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity, $variantId) {
            $cart = $this->carts->forUser($user);
            $product = $this->carts->product($productId, true);

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'The selected product is not available.',
                ]);
            }

            $variant = $variantId === null
                ? null
                : $this->carts->variant($product, $variantId, true);

            if ($variantId !== null && ($variant === null || ! $variant->is_active)) {
                throw ValidationException::withMessages([
                    'variant_id' => 'The selected variant is not available for this product.',
                ]);
            }

            if ($variantId === null && $product->variants()->exists()) {
                throw ValidationException::withMessages([
                    'variant_id' => 'Select a variant for this product.',
                ]);
            }

            $item = $this->carts->itemForSelection($cart, $product->id, $variant?->id, true);
            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            $this->assertAvailableQuantity($newQuantity, $variant?->stock ?? $product->stock);

            $item ??= new CartItem([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'unit_price' => $this->unitPrice($product, $variant),
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

            $variant = $item->variant_id === null
                ? null
                : $this->carts->variant($product, $item->variant_id, true);

            if ($item->variant_id !== null && ($variant === null || ! $variant->is_active)) {
                throw ValidationException::withMessages([
                    'quantity' => 'This product variant is no longer available.',
                ]);
            }

            if ($item->variant_id === null && $product->variants()->exists()) {
                throw ValidationException::withMessages([
                    'quantity' => 'This product now requires a variant selection. Remove it and add a variant.',
                ]);
            }

            $this->assertAvailableQuantity($quantity, $variant?->stock ?? $product->stock);
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

    private function unitPrice(Product $product, ?ProductVariant $variant): string
    {
        if ($variant?->selling_price !== null) {
            return $variant->selling_price;
        }

        if ($variant?->price !== null) {
            return $variant->price;
        }

        return $product->selling_price ?? $product->price;
    }
}
