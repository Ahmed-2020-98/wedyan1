<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manage;

use App\Enums\Availability;
use App\Enums\PaintingType;
use App\Http\Controllers\Api\V1\Manage\Concerns\ScopesToArtist;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaintingDetailResource;
use App\Http\Resources\PaintingResource;
use App\Models\Painting;
use App\Models\PaintingImage;
use App\Services\ImageService;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class PaintingController extends Controller
{
    use ScopesToArtist;

    public function __construct(private readonly ImageService $images) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->scopeForUser(Painting::query(), $request->user())
            ->with(['category', 'mainImage']);

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('sku', 'like', $like));
        }

        foreach (['painting_type' => 'type', 'availability' => 'availability'] as $column => $param) {
            if ($value = $request->query($param)) {
                $query->where($column, $value);
            }
        }

        return PaintingResource::collection($query->latest()->paginate(20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $painting = $this->find($request, $id);

        return response()->json([
            'data' => new PaintingDetailResource(
                $painting->load(['category', 'artist:id,name', 'images', 'tags', 'dimensionPresets'])
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $painting = DB::transaction(function () use ($request, $data): Painting {
            $painting = Painting::create([
                ...$data,
                // الفنان لا يختار مالك اللوحة: هو مالكها دائماً. والمدير
                // ينسبها إلى الفنان الوحيد في المنصة.
                'artist_id' => $request->user()->isArtist()
                    ? $request->user()->id
                    : ($data['artist_id'] ?? $request->user()->id),
                'slug' => Slug::unique($data['title'], 'paintings'),
                'sku' => $data['sku'] ?? $this->generateSku(),
            ]);

            $this->syncRelations($painting, $request);

            return $painting;
        });

        return response()->json([
            'data' => new PaintingDetailResource($painting->load(['category', 'images', 'tags'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $painting = $this->find($request, $id);
        $data = $this->validated($request, $painting);

        DB::transaction(function () use ($painting, $data, $request): void {
            // المُعرِّف يتغيّر فقط مع العنوان، وإلا كُسرت الروابط المفهرسة
            // في محرّكات البحث والمشاركة على غير طائل.
            if (isset($data['title']) && $data['title'] !== $painting->title) {
                $data['slug'] = Slug::unique($data['title'], 'paintings', 'slug', $painting->id);
            }

            $painting->update($data);
            $this->syncRelations($painting, $request);
        });

        return response()->json([
            'data' => new PaintingDetailResource($painting->fresh(['category', 'images', 'tags', 'dimensionPresets'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $painting = $this->find($request, $id);

        // حذف ناعم: سطور الطلبات السابقة ما تزال تشير إليها، وحذفها فعلياً
        // يُفقد الفواتير صورها وروابطها.
        $painting->delete();

        return response()->json(['message' => 'حُذفت اللوحة.']);
    }

    /** رفع صور. التحقق من النوع والحجم في قواعد التحقق أدناه. */
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $painting = $this->find($request, $id);

        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $created = [];
        $hasMain = $painting->images()->where('is_main', true)->exists();
        $sort = (int) $painting->images()->max('sort_order');

        foreach ($request->file('images') as $file) {
            $stored = $this->images->store($file, 'paintings');

            $created[] = PaintingImage::create([
                'painting_id' => $painting->id,
                'path' => $stored['path'],
                'variants' => $stored['variants'],
                'alt_text' => $request->input('alt_text') ?? $painting->title,
                'is_main' => ! $hasMain,
                'sort_order' => ++$sort,
            ]);

            $hasMain = true;
        }

        return response()->json([
            'data' => new PaintingDetailResource($painting->fresh(['images'])),
            'uploaded' => count($created),
        ], 201);
    }

    public function deleteImage(Request $request, int $id, int $imageId): JsonResponse
    {
        $painting = $this->find($request, $id);
        $image = $painting->images()->findOrFail($imageId);

        $this->images->delete($image->path, $image->variants);
        $wasMain = $image->is_main;
        $image->delete();

        // لا نترك اللوحة بلا صورة رئيسية: الأولى المتبقية ترث الدور.
        if ($wasMain) {
            $painting->images()->orderBy('sort_order')->first()?->update(['is_main' => true]);
        }

        return response()->json(['data' => new PaintingDetailResource($painting->fresh(['images']))]);
    }

    public function setMainImage(Request $request, int $id, int $imageId): JsonResponse
    {
        $painting = $this->find($request, $id);
        $image = $painting->images()->findOrFail($imageId);

        $painting->images()->update(['is_main' => false]);
        $image->update(['is_main' => true]);

        return response()->json(['data' => new PaintingDetailResource($painting->fresh(['images']))]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Painting $painting = null): array
    {
        $required = $painting === null ? 'required' : 'sometimes';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('paintings', 'sku')->ignore($painting?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => [$required, 'numeric', 'min:0', 'max:10000000'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'painting_type' => [$required, Rule::enum(PaintingType::class)],
            'availability' => ['sometimes', Rule::enum(Availability::class)],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'width' => ['nullable', 'numeric', 'min:1', 'max:2000'],
            'height' => ['nullable', 'numeric', 'min:1', 'max:2000'],
            'depth' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'unit' => ['nullable', 'string', 'in:cm,inch,m'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'material' => ['nullable', 'string', 'max:255'],
            'medium' => ['nullable', 'string', 'max:255'],
            'frame_info' => ['nullable', 'string', 'max:255'],
            'style' => ['nullable', 'string', 'max:255'],
            'colors' => ['nullable', 'array', 'max:10'],
            'colors.*' => ['string', 'max:64'],
            'deposit_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'production_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_featured' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'tag_ids' => ['sometimes', 'array', 'max:20'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'dimension_preset_ids' => ['sometimes', 'array', 'max:20'],
            'dimension_preset_ids.*' => ['integer', 'exists:dimension_presets,id'],
        ]);
    }

    private function syncRelations(Painting $painting, Request $request): void
    {
        if ($request->has('tag_ids')) {
            $painting->tags()->sync($request->input('tag_ids', []));
        }

        if ($request->has('dimension_preset_ids')) {
            $painting->dimensionPresets()->sync($request->input('dimension_preset_ids', []));
        }
    }

    private function find(Request $request, int $id): Painting
    {
        return $this->scopeForUser(Painting::query(), $request->user())->findOrFail($id);
    }

    private function generateSku(): string
    {
        return 'WD-' . str_pad((string) (Painting::withTrashed()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
