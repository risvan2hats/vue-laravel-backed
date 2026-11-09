<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductService
{
    public function __construct(private readonly ProductRepository $products) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return $this->products->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?UploadedFile $image = null): Product
    {
        $attributes['slug'] = $this->uniqueSlug($attributes['title']);

        if ($image !== null) {
            $attributes['image'] = $this->storeImage($image);
        }

        return $this->products->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Product $product, array $attributes, ?UploadedFile $image = null): Product
    {
        if (isset($attributes['title']) && $attributes['title'] !== $product->title) {
            $attributes['slug'] = $this->uniqueSlug($attributes['title'], $product->id);
        }

        if ($image !== null) {
            $attributes['image'] = $this->storeImage($image);
        }

        $oldImage = $image !== null ? $product->image : null;
        $product = $this->products->update($product, $attributes);

        if ($oldImage !== null) {
            Storage::disk('public')->delete($oldImage);
        }

        return $product;
    }

    public function delete(Product $product): void
    {
        $image = $product->image;
        $this->products->delete($product);

        if ($image !== null) {
            Storage::disk('public')->delete($image);
        }
    }

    private function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $baseSlug = Str::slug($title) ?: 'product';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->products->slugExists($slug, $exceptId)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('products', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The product image could not be stored.');
        }

        return $path;
    }
}
