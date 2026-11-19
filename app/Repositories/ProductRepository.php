<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->with([
                'category',
                'subCategory',
                'variants' => fn ($query) => $query->when(
                    ! ($filters['include_inactive'] ?? false),
                    fn ($variantQuery) => $variantQuery->where('is_active', true),
                ),
            ])
            ->when(! ($filters['include_inactive'] ?? false), fn ($query) => $query->where('is_active', true))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $category),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['sub_category'] ?? null, fn ($query, $subCategory) => $query->whereHas(
                'subCategory',
                fn ($subQuery) => $subQuery->where('slug', $subCategory),
            ))
            ->when($filters['sub_category_id'] ?? null, fn ($query, $subCategoryId) => $query->where('sub_category_id', $subCategoryId))
            ->when($filters['min_price'] ?? null, fn (Builder $query, $price) => $this->wherePrice($query, '>=', $price))
            ->when($filters['max_price'] ?? null, fn (Builder $query, $price) => $this->wherePrice($query, '<=', $price))
            ->when($filters['in_stock'] ?? false, fn (Builder $query) => $query->where(function (Builder $query) {
                $query->whereHas('variants', fn (Builder $variants) => $variants->where('is_active', true)->where('stock', '>', 0))
                    ->orWhere(fn (Builder $products) => $products->whereDoesntHave('variants')->where('stock', '>', 0));
            }));

        match ($sort) {
            'featured' => $products->orderByDesc('created_at'),
            'price-asc' => $products->orderByRaw($this->effectivePriceOrder('asc')),
            'price-desc' => $products->orderByRaw($this->effectivePriceOrder('desc')),
            'discount' => $products->orderByRaw($this->discountOrder())->orderByDesc('created_at'),
            'title' => $products->orderBy('title', $direction),
            'price' => $products->orderByRaw($this->effectivePriceOrder($direction)),
            'created_at' => $products->orderBy('created_at', $direction),
            default => $products->orderByDesc('created_at'),
        };

        return $products->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes)->load(['category', 'subCategory', 'variants']);
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh()->load(['category', 'subCategory', 'variants']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return Product::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    private function wherePrice(Builder $query, string $operator, mixed $price): Builder
    {
        return $query->where(function (Builder $query) use ($operator, $price) {
            $query->whereHas('variants', fn (Builder $variants) => $variants
                ->where('is_active', true)
                ->whereRaw(
                    "CAST(COALESCE(product_variants.selling_price, product_variants.price, products.selling_price, products.price) AS DECIMAL(10, 2)) {$operator} ?",
                    [$price],
                ))
                ->orWhere(fn (Builder $products) => $products
                    ->whereDoesntHave('variants')
                    ->whereRaw("CAST(COALESCE(selling_price, price) AS DECIMAL(10, 2)) {$operator} ?", [$price]));
        });
    }

    private function effectivePriceOrder(string $direction): string
    {
        return "COALESCE((SELECT MIN(COALESCE(product_variants.selling_price, product_variants.price, products.selling_price, products.price)) FROM product_variants WHERE product_variants.product_id = products.id AND product_variants.is_active = true), COALESCE(selling_price, price)) {$direction}";
    }

    private function discountOrder(): string
    {
        return 'COALESCE((SELECT MAX(CASE '
            .'WHEN product_variants.selling_price IS NOT NULL AND product_variants.selling_price < COALESCE(product_variants.price, products.price) '
            .'THEN (COALESCE(product_variants.price, products.price) - product_variants.selling_price) * 1.0 / COALESCE(product_variants.price, products.price) '
            .'WHEN product_variants.price IS NULL AND products.selling_price IS NOT NULL AND products.selling_price < products.price '
            .'THEN (products.price - products.selling_price) * 1.0 / products.price ELSE 0 END) '
            .'FROM product_variants WHERE product_variants.product_id = products.id AND product_variants.is_active = true), '
            .'CASE WHEN selling_price IS NOT NULL AND selling_price < price THEN (price - selling_price) * 1.0 / price ELSE 0 END) DESC';
    }
}
