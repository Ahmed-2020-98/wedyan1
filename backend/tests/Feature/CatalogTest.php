<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Painting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gallery_lists_only_published_paintings(): void
    {
        Painting::factory()->count(3)->create();
        Painting::factory()->draft()->create(['title' => 'مسوّدة']);

        $response = $this->getJson('/api/v1/paintings')->assertOk();

        $this->assertSame(3, $response->json('meta.total'));
        $this->assertNotContains('مسوّدة', array_column($response->json('data'), 'title'));
    }

    public function test_a_draft_painting_returns_404(): void
    {
        $draft = Painting::factory()->draft()->create();

        $this->getJson("/api/v1/paintings/{$draft->slug}")->assertNotFound();
    }

    public function test_paintings_can_be_filtered_by_type(): void
    {
        Painting::factory()->count(2)->create();
        Painting::factory()->reservable()->create();

        $this->getJson('/api/v1/paintings?filter[type]=reservable')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_paintings_can_be_filtered_by_price_range(): void
    {
        Painting::factory()->create(['price' => 1000]);
        Painting::factory()->create(['price' => 5000]);

        $this->getJson('/api/v1/paintings?filter[min_price]=2000&filter[max_price]=6000')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    /** كل ترتيب تعرضه الواجهة يجب أن يُقبل — الترتيب غير المُدرَج يعيد 400. */
    public function test_every_sort_offered_by_the_storefront_is_accepted(): void
    {
        Painting::factory()->count(2)->create();

        foreach (['-published_at', 'published_at', 'price', '-price', '-views_count', '-rating_avg'] as $sort) {
            $this->getJson("/api/v1/paintings?sort={$sort}")
                ->assertOk();
        }
    }

    public function test_search_matches_title_and_sku(): void
    {
        Painting::factory()->create(['title' => 'سكون الصحراء', 'sku' => 'WD-9001']);
        Painting::factory()->create(['title' => 'حوار الأزرق', 'sku' => 'WD-9002']);

        // urlencode مقصود: عميل الاختبار لا يرمّز العربية تلقائياً كما
        // يفعل المتصفح، فيصل المعامل مشوّهاً ويعود البحث فارغاً.
        $this->getJson('/api/v1/paintings?filter[search]=' . urlencode('الصحراء'))
            ->assertOk()->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/paintings?filter[search]=WD-9002')
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    /**
     * الأقواس حول مجموعة البحث ضرورية: بدونها يلتصق آخر orWhere بشرط
     * النشر فتظهر المسوّدات في نتائج البحث.
     */
    public function test_search_never_leaks_unpublished_paintings(): void
    {
        Painting::factory()->draft()->create(['title' => 'مسوّدة سرّية']);

        $this->getJson('/api/v1/paintings?filter[search]=' . urlencode('سرّية'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_the_detail_endpoint_exposes_the_full_record(): void
    {
        $painting = Painting::factory()->create(['material' => 'قماش كتّاني']);

        $this->getJson("/api/v1/paintings/{$painting->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', $painting->title)
            ->assertJsonPath('data.material', 'قماش كتّاني')
            ->assertJsonPath('data.isPurchasable', true)
            ->assertJsonStructure(['data' => ['price' => ['amount', 'currency', 'formatted'], 'seo', 'images']]);
    }

    public function test_a_reservable_painting_is_not_purchasable(): void
    {
        $painting = Painting::factory()->reservable()->create();

        $this->getJson("/api/v1/paintings/{$painting->slug}")
            ->assertOk()
            ->assertJsonPath('data.isPurchasable', false)
            ->assertJsonPath('data.isReservable', true)
            ->assertJsonPath('data.reservation.depositPercentage', 50);
    }

    public function test_category_counts_exclude_unpublished_paintings(): void
    {
        $category = Category::factory()->create();
        Painting::factory()->count(2)->create(['category_id' => $category->id]);
        Painting::factory()->draft()->create(['category_id' => $category->id]);

        $response = $this->getJson('/api/v1/categories')->assertOk();

        $this->assertSame(2, $response->json('data.0.paintingsCount'));
    }
}
