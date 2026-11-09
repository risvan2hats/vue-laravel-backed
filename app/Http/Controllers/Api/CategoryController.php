<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'include_inactive' => ['sometimes', 'boolean'],
        ]);
        $filters['include_inactive'] = $request->user()?->is_admin && $request->boolean('include_inactive');

        return CategoryResource::collection($this->categories->paginate($filters));
    }

    public function show(Category $category): CategoryResource
    {
        if (! $category->is_active && ! auth()->user()?->is_admin) {
            abort(404);
        }

        return new CategoryResource($category->loadCount('products'));
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $category = $this->categories->create($attributes, $request->file('image'));

        return (new CategoryResource($category->loadCount('products')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $attributes = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return new CategoryResource($this->categories->update($category, $attributes, $request->file('image')));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categories->delete($category);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
