<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function __construct(private readonly ProductVariantService $variants) {}

    public function store(Request $request, Product $product): JsonResponse
    {
        $attributes = $this->validatedAttributes($request, $product);
        $variant = $this->variants->create($product, $attributes);

        return (new ProductVariantResource($variant))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, Product $product, int $variant): ProductVariantResource
    {
        $currentVariant = $product->variants()->findOrFail($variant);
        $attributes = $this->validatedAttributes($request, $product, $currentVariant);

        return new ProductVariantResource($this->variants->update($product, $variant, $attributes));
    }

    public function destroy(Product $product, int $variant): JsonResponse
    {
        $this->variants->delete($product, $variant);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(
        Request $request,
        Product $product,
        ?ProductVariant $variant = null,
    ): array {
        $required = $variant === null ? ['required'] : ['sometimes', 'required'];
        $validator = Validator::make($request->all(), [
            'attributes' => [...$required, 'array', 'min:1'],
            'attributes.*' => ['required', 'string', 'max:100'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('product_variants', 'sku')->ignore($variant),
            ],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'selling_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'stock' => [...$required, 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validator->after(function (ValidatorContract $validator) use ($request, $product, $variant): void {
            if ($validator->errors()->hasAny(['attributes', 'price', 'selling_price'])) {
                return;
            }

            $variantPrice = $request->input('price', $variant?->price);
            $regularPrice = $variantPrice ?? $product->price;
            $sellingPrice = $request->input('selling_price', $variant?->selling_price);

            if ($sellingPrice === null && $variantPrice === null) {
                $sellingPrice = $product->selling_price;
            }

            if ($sellingPrice !== null && (float) $sellingPrice > (float) $regularPrice) {
                $validator->errors()->add('selling_price', 'The selling price must not exceed the regular price.');
            }
        });

        return $validator->validate();
    }
}
