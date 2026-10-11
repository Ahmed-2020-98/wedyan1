<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaintingDetailResource;
use App\Http\Resources\PaintingResource;
use App\Models\Painting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class PaintingController extends Controller
{
    /**
     * قائمة اللوحات مع البحث والتصفية والترتيب والتصفيح.
     *
     * كل شيء على الخادم لا في الواجهة: المعرض قد يضمّ مئات الأعمال، وإرسالها
     * كاملةً ثم تصفيتها في المتصفح يعني تحميل ميغابايتات لعرض ٢٤ بطاقة.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $paintings = QueryBuilder::for(Painting::class)
            ->allowedFilters(...[
                AllowedFilter::exact('type', 'painting_type'),
                AllowedFilter::exact('availability'),
                AllowedFilter::exact('style'),
                AllowedFilter::exact('material'),
                AllowedFilter::callback('category', $this->filterCategory(...)),
                AllowedFilter::callback('tag', $this->filterTag(...)),
                AllowedFilter::callback('search', $this->filterSearch(...)),
                AllowedFilter::callback('min_price', fn (Builder $q, $v) => $q->where('price', '>=', (float) $v)),
                AllowedFilter::callback('max_price', fn (Builder $q, $v) => $q->where('price', '<=', (float) $v)),
                AllowedFilter::callback('min_width', fn (Builder $q, $v) => $q->where('width', '>=', (float) $v)),
                AllowedFilter::callback('max_width', fn (Builder $q, $v) => $q->where('width', '<=', (float) $v)),
                AllowedFilter::callback('min_height', fn (Builder $q, $v) => $q->where('height', '>=', (float) $v)),
                AllowedFilter::callback('max_height', fn (Builder $q, $v) => $q->where('height', '<=', (float) $v)),
                AllowedFilter::callback('featured', fn (Builder $q, $v) => $q->where('is_featured', filter_var($v, FILTER_VALIDATE_BOOLEAN))),
            ])
            /*
             | published_at مذكور صراحةً رغم أنه الترتيب الافتراضي: مكتبة
             | التصفية ترفض بـ400 أي ترتيب غير مُدرَج هنا، حتى الافتراضي منه
             | حين ترسله الواجهة في العنوان.
             */
            ->allowedSorts(...[
                'price', 'created_at', 'published_at', 'views_count',
                'sold_count', 'rating_avg', 'title',
            ])
            // الأحدث أولاً: المعرض واجهة اكتشاف، والعمل الجديد هو ما يعيد الزائر.
            ->defaultSort('-published_at')
            ->published()
            // with لا لكل شيء: هذه الثلاثة فقط ما تعرضه البطاقة، وتحميلها
            // مسبقاً يمنع استعلاماً لكل لوحة (N+1).
            ->with(['category', 'mainImage', 'artist:id,name'])
            ->paginate(min((int) $request->integer('per_page', 24), 48))
            ->withQueryString();

        return PaintingResource::collection($paintings);
    }

    public function show(string $slug): PaintingDetailResource
    {
        $painting = Painting::published()
            ->where('slug', $slug)
            ->with(['category', 'artist:id,name', 'images', 'tags', 'dimensionPresets'])
            ->firstOrFail();

        // زيادة صامتة: increment وحده يتجنّب سباق القراءة والكتابة،
        // و saveQuietly يمنع تحديث updated_at فلا تبدو اللوحة «معدَّلة» بكل زيارة.
        $painting->incrementQuietly('views_count');

        return new PaintingDetailResource($painting);
    }

    /** لوحات ذات صلة: نفس التصنيف، مع استبعاد المعروضة نفسها. */
    public function related(string $slug): AnonymousResourceCollection
    {
        $painting = Painting::published()->where('slug', $slug)->firstOrFail();

        $related = Painting::published()
            ->where('id', '!=', $painting->id)
            ->when($painting->category_id, fn (Builder $q) => $q->where('category_id', $painting->category_id))
            ->with(['category', 'mainImage'])
            ->limit(4)
            ->get();

        return PaintingResource::collection($related);
    }

    /**
     * القيم المتاحة للفلاتر، محسوبة من الكتالوج الفعلي.
     *
     * الواجهة لا تخمّن الخيارات ولا تُثبّتها في الشيفرة: لو أضاف الفنان خامة
     * جديدة ظهرت في الفلتر فوراً بلا نشر جديد.
     */
    public function filters(): JsonResponse
    {
        $base = Painting::published();

        return response()->json([
            'data' => [
                'priceRange' => [
                    'min' => (float) ($base->clone()->min('price') ?? 0),
                    'max' => (float) ($base->clone()->max('price') ?? 0),
                ],
                'sizeRange' => [
                    'minWidth' => (float) ($base->clone()->min('width') ?? 0),
                    'maxWidth' => (float) ($base->clone()->max('width') ?? 0),
                    'minHeight' => (float) ($base->clone()->min('height') ?? 0),
                    'maxHeight' => (float) ($base->clone()->max('height') ?? 0),
                ],
                'styles' => $base->clone()->whereNotNull('style')->distinct()->orderBy('style')->pluck('style'),
                'materials' => $base->clone()->whereNotNull('material')->distinct()->orderBy('material')->pluck('material'),
            ],
        ]);
    }

    private function filterCategory(Builder $query, mixed $value): void
    {
        $slugs = is_array($value) ? $value : explode(',', (string) $value);

        $query->whereHas('category', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    private function filterTag(Builder $query, mixed $value): void
    {
        $slugs = is_array($value) ? $value : explode(',', (string) $value);

        $query->whereHas('tags', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    /**
     * بحث نصّي على العنوان والوصف ورمز المنتج والوسوم واسم الفنان.
     *
     * الأقواس حول المجموعة ضرورية: بدونها يلتصق آخر orWhere بشروط النشر
     * فتظهر لوحات غير منشورة في نتائج البحث.
     */
    private function filterSearch(Builder $query, mixed $value): void
    {
        $term = trim((string) $value);

        if ($term === '') {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('style', 'like', $like)
                ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like))
                ->orWhereHas('artist', fn (Builder $a) => $a->where('name', 'like', $like));
        });
    }
}
