<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Availability;
use App\Enums\PaintingType;
use App\Models\Category;
use App\Models\DimensionPreset;
use App\Models\Painting;
use App\Models\PaintingImage;
use App\Models\Tag;
use App\Models\User;
use App\Services\ImageService;
use App\Support\ArtworkGenerator;
use Illuminate\Database\Seeder;
use App\Support\Slug;

final class CatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        ['تجريدي', 'لوحات تعتمد اللون والشكل بدل التمثيل المباشر.'],
        ['حديث', 'أعمال معاصرة بخطوط نظيفة ومساحات جريئة.'],
        ['كلاسيكي', 'أسلوب تقليدي بتقنيات الرسم الزيتي المتوارثة.'],
        ['بورتريه', 'رسم الوجوه والملامح بأسلوب فني.'],
        ['مناظر طبيعية', 'مشاهد من الصحراء والجبال والبحر.'],
        ['فن إسلامي', 'زخارف هندسية ونباتية مستوحاة من التراث.'],
        ['خط عربي', 'أعمال تتخذ من الحرف العربي موضوعاً بصرياً.'],
        ['طبيعة صامتة', 'تكوينات من عناصر ساكنة بإضاءة مدروسة.'],
        ['لوحات مخصصة', 'أعمال تُرسم خصيصاً بطلب من الزبون.'],
    ];

    private const PRESETS = [
        ['صغير', 30, 40],
        ['متوسط', 50, 70],
        ['كبير', 60, 90],
        ['كبير جداً', 80, 120],
        ['بانورامي', 100, 150],
    ];

    private const TAGS = [
        'ذهبي', 'أزرق', 'ترابي', 'أخضر', 'رمادي',
        'هادئ', 'جريء', 'زخرفي', 'معاصر', 'كلاسيكي',
    ];

    /** @var list<array{string,string,string,string}> عنوان، أسلوب، خامة، وسيط */
    private const TITLES = [
        ['سكون الصحراء', 'flow', 'قماش كتّاني', 'ألوان زيتية'],
        ['أثر الضوء', 'fields', 'قماش قطني', 'أكريليك'],
        ['طبقات الوادي', 'strata', 'قماش كتّاني', 'ألوان زيتية'],
        ['مدار الفجر', 'orbits', 'خشب مُعالج', 'أكريليك'],
        ['نسيج الرمل', 'strata', 'قماش كتّاني', 'ألوان مائية'],
        ['حوار الأزرق', 'flow', 'قماش قطني', 'ألوان زيتية'],
        ['ظلّ النخيل', 'fields', 'قماش كتّاني', 'أكريليك'],
        ['انعكاس', 'orbits', 'قماش قطني', 'ألوان زيتية'],
        ['رياح الشمال', 'flow', 'قماش كتّاني', 'أكريليك'],
        ['صمت المساء', 'strata', 'خشب مُعالج', 'ألوان زيتية'],
        ['تكوين ذهبي', 'fields', 'قماش كتّاني', 'ورق ذهب وأكريليك'],
        ['مدينة بعيدة', 'strata', 'قماش قطني', 'ألوان زيتية'],
        ['نبض', 'orbits', 'قماش كتّاني', 'أكريليك'],
        ['أفق مفتوح', 'flow', 'قماش كتّاني', 'ألوان زيتية'],
        ['هندسة النور', 'fields', 'خشب مُعالج', 'أكريليك'],
        ['خطوط الوادي', 'strata', 'قماش قطني', 'ألوان مائية'],
        ['دوائر الذاكرة', 'orbits', 'قماش كتّاني', 'ألوان زيتية'],
        ['حرف ولون', 'fields', 'قماش كتّاني', 'حبر وأكريليك'],
        ['موج ساكن', 'flow', 'قماش قطني', 'ألوان زيتية'],
        ['ضوء الظهيرة', 'fields', 'قماش كتّاني', 'أكريليك'],
        ['أرض وسماء', 'strata', 'قماش كتّاني', 'ألوان زيتية'],
        ['نافذة', 'fields', 'خشب مُعالج', 'أكريليك'],
        ['تجريد رقم ٧', 'flow', 'قماش قطني', 'ألوان زيتية'],
        ['ملامح', 'orbits', 'قماش كتّاني', 'فحم وأكريليك'],
    ];

    public function __construct(
        private readonly ImageService $images,
        private readonly ArtworkGenerator $artwork,
    ) {}

    public function run(): void
    {
        $artist = User::where('email', 'artist@wedyan.local')->firstOrFail();

        $categories = collect(self::CATEGORIES)->map(fn (array $c, int $i) => Category::create([
            'name' => $c[0],
            'slug' => Slug::make($c[0]),
            'description' => $c[1],
            'is_active' => true,
            'sort_order' => $i,
            'seo_title' => $c[0] . ' — لوحات فنية أصلية',
            'seo_description' => $c[1],
        ]));

        $presets = collect(self::PRESETS)->map(fn (array $p, int $i) => DimensionPreset::create([
            'label' => sprintf('%s — %d × %d سم', $p[0], $p[1], $p[2]),
            'width' => $p[1],
            'height' => $p[2],
            'unit' => 'cm',
            'sort_order' => $i,
        ]));

        $tags = collect(self::TAGS)->map(fn (string $t) => Tag::create([
            'name' => $t,
            'slug' => Slug::make($t),
        ]));

        foreach (self::TITLES as $index => [$title, $style, $material, $medium]) {
            $this->createPainting($artist, $categories, $presets, $tags, $index, $title, $style, $material, $medium);
        }
    }

    private function createPainting(
        User $artist,
        $categories,
        $presets,
        $tags,
        int $index,
        string $title,
        string $style,
        string $material,
        string $medium,
    ): void {
        $preset = self::PRESETS[$index % count(self::PRESETS)];
        [$width, $height] = [$preset[1], $preset[2]];

        /*
         | التوزيع مقصود: الأغلبية جاهزة للبيع الفوري، وربعها تقريباً قابل
         | للحجز، وواحدة طلب خاص — حتى يظهر المساران في العرض بلا تدخّل.
         */
        $type = match (true) {
            $index % 7 === 3 => PaintingType::Reservable,
            $index % 11 === 5 => PaintingType::CustomOrder,
            default => PaintingType::ReadyMade,
        };

        $availability = match (true) {
            $type !== PaintingType::ReadyMade => Availability::Available,
            $index % 9 === 4 => Availability::Sold,
            default => Availability::Available,
        };

        $basePrice = 1200 + ($index % 8) * 650 + ($width * $height / 12);
        $price = round($basePrice / 50) * 50;

        $painting = Painting::create([
            'artist_id' => $artist->id,
            'category_id' => $categories[$index % $categories->count()]->id,
            'title' => $title,
            'slug' => Slug::unique($title, 'paintings'),
            'sku' => sprintf('WD-%04d', $index + 1),
            'short_description' => "عمل أصلي بتوقيع الفنانة، {$medium} على {$material}.",
            'description' => $this->description($title, $material, $medium, $width, $height),
            'price' => $price,
            'discount_price' => $index % 6 === 2 ? round($price * 0.85 / 50) * 50 : null,
            'painting_type' => $type,
            'availability' => $availability,
            'stock_quantity' => $availability === Availability::Sold ? 0 : 1,
            'width' => $width,
            'height' => $height,
            'depth' => 2,
            'unit' => 'cm',
            'weight' => round(($width * $height) / 900, 2),
            'material' => $material,
            'medium' => $medium,
            'frame_info' => $index % 3 === 0 ? 'إطار خشبي طبيعي' : 'بلا إطار',
            'style' => $categories[$index % $categories->count()]->name,
            'colors' => ['ذهبي', 'ترابي', 'أزرق'],
            'deposit_percentage' => 50,
            'production_days' => $type->isReservable() ? 21 + ($index % 3) * 7 : null,
            'is_featured' => $index < 6,
            'seo_title' => "{$title} — لوحة فنية أصلية",
            'seo_description' => "لوحة {$title} بمقاس {$width}×{$height} سم، {$medium} على {$material}.",
            'published_at' => now()->subDays(60 - $index),
        ]);

        $painting->tags()->attach(
            $tags->random(mt_rand(2, 4))->pluck('id')->all()
        );

        if ($type->isReservable()) {
            $painting->dimensionPresets()->attach(
                $presets->take(4)->mapWithKeys(fn ($p, $i) => [
                    $p->id => ['price_modifier' => $i * 400],
                ])->all()
            );
        }

        // صورتان لكل لوحة: الرئيسية وتفصيلة، ليعمل المعرض والتكبير فعلياً.
        foreach ([0, 1] as $slot) {
            $stored = $this->images->storeContents(
                $this->artwork->generate(
                    (int) ($width * 14),
                    (int) ($height * 14),
                    ($index + 1) * 100 + $slot,
                    $style,
                ),
                'paintings',
            );

            PaintingImage::create([
                'painting_id' => $painting->id,
                'path' => $stored['path'],
                'variants' => $stored['variants'],
                'alt_text' => $slot === 0
                    ? "{$title} — لوحة {$medium} على {$material}"
                    : "{$title} — تفصيلة",
                'is_main' => $slot === 0,
                'sort_order' => $slot,
            ]);
        }
    }

    private function description(string $title, string $material, string $medium, int $w, int $h): string
    {
        return implode("\n\n", [
            "«{$title}» عمل أصلي مرسوم يدوياً بـ{$medium} على {$material}، بمقاس {$w} × {$h} سم.",
            'كل لوحة قطعة واحدة لا تتكرّر، موقّعة من الفنانة على الوجه ومختومة من الخلف بتاريخ الإنجاز.',
            'تُشحن اللوحة ملفوفة بطبقة واقية داخل صندوق خشبي مقوّى، مع شهادة أصالة.',
        ]);
    }
}
