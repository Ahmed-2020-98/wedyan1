<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Availability;
use App\Enums\PaintingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Painting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'artist_id', 'category_id', 'title', 'slug', 'sku',
        'short_description', 'description', 'price', 'discount_price',
        'painting_type', 'availability', 'stock_quantity',
        'width', 'height', 'depth', 'unit', 'weight',
        'material', 'medium', 'frame_info', 'style', 'colors',
        'deposit_percentage', 'production_days',
        'is_featured', 'seo_title', 'seo_description', 'published_at',
    ];

    /**
     * قيم افتراضية في الذاكرة تطابق افتراضيات قاعدة البيانات.
     *
     * بدونها يعود النموذج المُنشأ حديثاً بـ null في هذه الحقول — لأن
     * الافتراضي يُطبَّق في قاعدة البيانات ولا يعرفه الكائن — فينهار المورد
     * عند قراءة availability->value في أول استجابة بعد الإنشاء.
     */
    protected $attributes = [
        'painting_type' => 'ready_made',
        'availability' => 'available',
        'stock_quantity' => 1,
        'unit' => 'cm',
        'deposit_percentage' => 50,
        'is_featured' => false,
        'views_count' => 0,
        'sold_count' => 0,
        'rating_avg' => 0,
        'rating_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'painting_type' => PaintingType::class,
            'availability' => Availability::class,
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'weight' => 'decimal:2',
            'colors' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artist_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PaintingImage::class)->orderBy('sort_order');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(PaintingImage::class)->where('is_main', true);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function dimensionPresets(): BelongsToMany
    {
        // اسم الجدول صريح: اشتقاق Laravel الأبجدي ينتج
        // dimension_preset_painting بينما الهجرة تسمّيه painting_dimension_preset.
        return $this->belongsToMany(DimensionPreset::class, 'painting_dimension_preset')
            ->withPivot('price_modifier');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /*
    |--------------------------------------------------------------------------
    | النطاقات
    |--------------------------------------------------------------------------
    */

    /** المنشور فقط: غير المنشور مسوّدة لا يراها الزائر. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('painting_type', PaintingType::ReadyMade->value)
            ->where('availability', Availability::Available->value)
            ->where('stock_quantity', '>', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | مساعدات
    |--------------------------------------------------------------------------
    */

    /** السعر الفعلي بعد الخصم إن وُجد. */
    public function effectivePrice(): float
    {
        $discount = $this->discount_price === null ? null : (float) $this->discount_price;

        return $discount !== null && $discount > 0 && $discount < (float) $this->price
            ? $discount
            : (float) $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->effectivePrice() < (float) $this->price;
    }

    /** هل يمكن وضعها في السلة الآن؟ */
    public function isPurchasable(): bool
    {
        return $this->painting_type === PaintingType::ReadyMade
            && $this->availability->isPurchasable()
            && $this->stock_quantity > 0
            && $this->published_at !== null;
    }

    /** هل تقبل طلب حجز؟ */
    public function isReservable(): bool
    {
        return $this->painting_type->isReservable()
            && $this->availability !== Availability::Unavailable
            && $this->published_at !== null;
    }
}
