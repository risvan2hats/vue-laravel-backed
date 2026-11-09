<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['title', 'price', 'created_at'], true)
            ? $filters['sort']
            : 'created_at';

        return Product::query()
            ->with('category')
            ->when(! ($filters['include_inactive'] ?? false), fn ($query) => $query->where('is_active', true))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['min_price'] ?? null, fn ($query, $price) => $query->where('price', '>=', $price))
            ->when($filters['max_price'] ?? null, fn ($query, $price) => $query->where('price', '<=', $price))
            ->orderBy($sort, ($filters['direction'] ?? null) === 'asc' ? 'asc' : 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $attributes): Product
    {
        return Product::create($attributes)->load('category');
    }

    public function update(Product $product, array $attributes): Product
    {
        $product->update($attributes);

        return $product->refresh()->load('category');
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
}
