<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaintingType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PaintingResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Painting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Cache;

final class HomeController extends Controller
{
    /**
     * كل ما تحتاجه الصفحة الرئيسية في طلب واحد.
     *
     * الصفحة تُصيَّر على الخادم، وكل طلب إضافي هنا يضاف مباشرةً إلى زمن
     * أول بايت. طلب واحد مُخزَّن مؤقتاً أفضل من ستة طلبات متوازية.
     */
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('home:sections', now()->addMinutes(10), function (): array {
            $with = ['category', 'mainImage'];

            return [
                'featured' => $this->plain(PaintingResource::collection(
                    Painting::published()->where('is_featured', true)
                        ->with($with)->latest('published_at')->limit(6)->get()
                )),

                'newest' => $this->plain(PaintingResource::collection(
                    Painting::published()->with($with)->latest('published_at')->limit(8)->get()
                )),

                'bestSellers' => $this->plain(PaintingResource::collection(
                    Painting::published()->where('sold_count', '>', 0)
                        ->with($with)->orderByDesc('sold_count')->limit(4)->get()
                )),

                'reservable' => $this->plain(PaintingResource::collection(
                    Painting::published()
                        ->whereIn('painting_type', [
                            PaintingType::Reservable->value,
                            PaintingType::CustomOrder->value,
                        ])
                        ->with($with)->limit(4)->get()
                )),

                'categories' => $this->plain(CategoryResource::collection(
                    Category::where('is_active', true)->whereNull('parent_id')
                        ->withCount(['paintings' => fn ($q) => $q->published()])
                        ->orderBy('sort_order')->limit(9)->get()
                )),

                'banners' => Banner::live()->where('placement', 'home_hero')
                    ->orderBy('sort_order')->get()
                    ->map(fn (Banner $b) => [
                        'title' => $b->title,
                        'subtitle' => $b->subtitle,
                        'imageUrl' => $b->url(),
                        'linkUrl' => $b->link_url,
                        'ctaLabel' => $b->cta_label,
                    ])->all(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * يحوّل المورد إلى مصفوفة بدائية بالكامل قبل التخزين المؤقت.
     *
     * ->resolve() وحدها لا تكفي: تُحلّ المستوى الأعلى فقط وتترك الموارد
     * المتداخلة (mainImage مثلاً) كائنات كما هي، فتُخزَّن مُسلسَلة وتعود من
     * الذاكرة كـ __PHP_Incomplete_Class — أي صور بلا روابط في الواجهة.
     *
     * المرور بـ json_encode يجبر السلسلة كاملةً على الحلّ.
     */
    private function plain(JsonResource|ResourceCollection $resource): array
    {
        return json_decode((string) $resource->toJson(), true) ?? [];
    }
}
