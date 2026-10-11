<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            // العدّ مقصور على المنشور: تصنيف يعرض «١٢ عملاً» ثم يفتح على
            // ثلاثة، تجربة مربكة تُفقد الثقة بالأرقام كلها.
            ->withCount(['paintings' => fn ($q) => $q->published()])
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(string $slug): CategoryResource
    {
        $category = Category::where('slug', $slug)
            ->where('is_active', true)
            ->withCount(['paintings' => fn ($q) => $q->published()])
            ->firstOrFail();

        return new CategoryResource($category);
    }
}
