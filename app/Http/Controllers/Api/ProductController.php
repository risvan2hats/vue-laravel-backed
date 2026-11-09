<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'gte:min_price'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['title', 'price', 'created_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'include_inactive' => ['sometimes', 'boolean'],
        ]);
        $filters['include_inactive'] = $request->user()?->is_admin && $request->boolean('include_inactive');

        return ProductResource::collection($this->products->paginate($filters));
    }

    public function show(Product $product): ProductResource
    {
        if (! $product->is_active && ! auth()->user()?->is_admin) {
            abort(404);
        }

        return new ProductResource($product->load('category'));
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $this->validatedAttributes($request);
        $product = $this->products->create($attributes, $request->file('image'));

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $attributes = $this->validatedAttributes($request, $product);

        return new ProductResource($this->products->update($product, $attributes, $request->file('image')));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->products->delete($product);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(Request $request, ?Product $product = null): array
    {
        $required = $product === null ? ['required'] : ['sometimes', 'required'];
        $validator = Validator::make($request->all(), [
            'category_id' => [...$required, 'integer', 'exists:categories,id'],
            'title' => [...$required, 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'price' => [...$required, 'numeric', 'min:0.01'],
            'selling_price' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product)],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validator->after(function (ValidatorContract $validator) use ($request, $product): void {
            $price = $request->input('price', $product?->price);
            $sellingPrice = $request->input('selling_price', $product?->selling_price);

            if ($sellingPrice !== null && $price !== null && (float) $sellingPrice > (float) $price) {
                $validator->errors()->add('selling_price', 'The selling price must not exceed the regular price.');
            }
        });

        return $validator->validate();
    }
}
