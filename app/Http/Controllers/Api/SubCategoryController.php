<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubCategoryResource;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'max:255', 'exists:categories,slug'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
        ]);

        $subCategories = SubCategory::query()
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query->whereHas(
                'category',
                fn ($q) => $q->where('slug', $slug),
            ))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->with('category')
            ->orderBy('name')
            ->get();

        return SubCategoryResource::collection($subCategories);
    }

    public function show(SubCategory $subCategory): SubCategoryResource
    {
        return new SubCategoryResource($subCategory->load('category'));
    }
}
