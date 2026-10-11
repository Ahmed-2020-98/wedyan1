<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\ImageService;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function __construct(private readonly ImageService $images) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::withCount('paintings')->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $category = Category::create([
            ...$data,
            'slug' => Slug::unique($data['name'], 'categories'),
            'image_path' => $request->hasFile('image')
                ? $this->images->store($request->file('image'), 'categories')['path']
                : null,
        ]);

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request, $category);

        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = Slug::unique($data['name'], 'categories', 'slug', $category->id);
        }

        if ($request->hasFile('image')) {
            $this->images->delete($category->image_path);
            $data['image_path'] = $this->images->store($request->file('image'), 'categories')['path'];
        }

        $category->update($data);

        return response()->json(['data' => new CategoryResource($category->fresh())]);
    }

    public function destroy(Category $category): JsonResponse
    {
        // اللوحات لا تُحذف مع التصنيف — علاقتها nullOnDelete، فتصبح بلا تصنيف.
        $category->delete();

        return response()->json(['message' => 'حُذف التصنيف.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [$category === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);
    }
}
