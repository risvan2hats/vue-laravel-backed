<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CategoryService
{
    public function __construct(private readonly CategoryRepository $categories) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->categories->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?UploadedFile $image = null): Category
    {
        $attributes['slug'] = $this->uniqueSlug($attributes['name']);

        if ($image !== null) {
            $attributes['image'] = $this->storeImage($image);
        }

        return $this->categories->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Category $category, array $attributes, ?UploadedFile $image = null): Category
    {
        if (isset($attributes['name']) && $attributes['name'] !== $category->name) {
            $attributes['slug'] = $this->uniqueSlug($attributes['name'], $category->id);
        }

        if ($image !== null) {
            $attributes['image'] = $this->storeImage($image);
        }

        $oldImage = $image !== null ? $category->image : null;
        $category = $this->categories->update($category, $attributes);

        if ($oldImage !== null) {
            Storage::disk('public')->delete($oldImage);
        }

        return $category->loadCount('products');
    }

    public function delete(Category $category): void
    {
        $image = $category->image;
        $this->categories->delete($category);

        if ($image !== null) {
            Storage::disk('public')->delete($image);
        }
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->categories->slugExists($slug, $exceptId)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('categories', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The category image could not be stored.');
        }

        return $path;
    }
}
