<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Category::query()
            ->withCount('products')
            ->when(! ($filters['include_inactive'] ?? false), fn ($query) => $query->where('is_active', true))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $attributes): Category
    {
        return Category::create($attributes);
    }

    public function update(Category $category, array $attributes): Category
    {
        $category->update($attributes);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return Category::query()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
