<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'image_url' => $this->image
                ? Storage::disk('public')->url($this->image)
                : asset('images/product-placeholder.png'),
            'price' => $this->price,
            'selling_price' => $this->selling_price,
            'current_price' => $this->currentPrice(),
            'sku' => $this->sku,
            'stock' => $this->relationLoaded('variants') ? $this->variants->sum('stock') : $this->stock,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function currentPrice(): string
    {
        if ($this->relationLoaded('variants')) {
            $lowestVariantPrice = $this->variants
                ->where('is_active', true)
                ->min(fn (ProductVariant $variant): float => (float) (
                    $variant->selling_price
                    ?? $variant->price
                    ?? $this->selling_price
                    ?? $this->price
                ));

            if ($lowestVariantPrice !== null) {
                return number_format((float) $lowestVariantPrice, 2, '.', '');
            }
        }

        return $this->selling_price ?? $this->price;
    }
}
