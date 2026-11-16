<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductVariantService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Product $product, array $attributes): ProductVariant
    {
        $attributes['options_hash'] = $this->optionsHash($attributes['attributes']);
        $this->assertUniqueOptions($product, $attributes['options_hash']);

        return $product->variants()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, int $variantId, array $attributes): ProductVariant
    {
        $variant = $product->variants()->findOrFail($variantId);

        if (array_key_exists('attributes', $attributes)) {
            $attributes['options_hash'] = $this->optionsHash($attributes['attributes']);
            $this->assertUniqueOptions($product, $attributes['options_hash'], $variant);
        }

        $variant->update($attributes);

        return $variant->refresh();
    }

    public function delete(Product $product, int $variantId): void
    {
        $product->variants()->findOrFail($variantId)->delete();
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function optionsHash(array $attributes): string
    {
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    private function assertUniqueOptions(
        Product $product,
        string $optionsHash,
        ?ProductVariant $variant = null,
    ): void {
        $exists = $product->variants()
            ->where('options_hash', $optionsHash)
            ->when($variant !== null, fn ($query) => $query->whereKeyNot($variant->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'attributes' => 'A variant with these options already exists for this product.',
            ]);
        }
    }
}
