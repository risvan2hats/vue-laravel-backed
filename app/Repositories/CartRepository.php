<?php

namespace App\Repositories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

class CartRepository
{
    public function forUser(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function product(int $productId, bool $lockForUpdate = false): ?Product
    {
        $query = Product::query()->whereKey($productId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function variant(Product $product, int $variantId, bool $lockForUpdate = false): ?ProductVariant
    {
        $query = $product->variants()->whereKey($variantId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function item(Cart $cart, int $itemId, bool $lockForUpdate = false): ?CartItem
    {
        $query = $cart->items()->whereKey($itemId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function itemForSelection(
        Cart $cart,
        int $productId,
        ?int $variantId,
        bool $lockForUpdate = false,
    ): ?CartItem {
        $query = $cart->items()->where('product_id', $productId);
        $query = $variantId === null
            ? $query->whereNull('variant_id')
            : $query->where('variant_id', $variantId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function saveItem(CartItem $item): CartItem
    {
        $item->save();

        return $item;
    }

    public function deleteItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function withItems(Cart $cart): Cart
    {
        return $cart->load(['items.product.category', 'items.variant']);
    }
}
