<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });

        // مقاسات جاهزة يضبطها الفنان مرة واحدة ويختار منها الزبون عند الحجز،
        // بدل أن يكتب أبعاداً حرّة يصعب تسعيرها وتأطيرها.
        Schema::create('dimension_presets', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->decimal('depth', 8, 2)->nullable();
            $table->string('unit', 8)->default('cm');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['width', 'height', 'unit']);
        });

        Schema::create('paintings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artist_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();

            $table->string('painting_type', 32)->default('ready_made');
            $table->string('availability', 32)->default('available');

            // اللوحة الجاهزة قطعة واحدة فريدة، فالافتراضي ١ لا ٠.
            $table->unsignedInteger('stock_quantity')->default(1);

            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->string('unit', 8)->default('cm');
            $table->decimal('weight', 8, 2)->nullable();

            $table->string('material')->nullable();
            $table->string('medium')->nullable();
            $table->string('frame_info')->nullable();
            $table->string('style')->nullable();
            $table->json('colors')->nullable();

            // إعدادات الحجز — تُقرأ فقط حين يكون النوع reservable أو custom_order
            $table->unsignedTinyInteger('deposit_percentage')->default(50);
            $table->unsignedSmallInteger('production_days')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('sold_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // الفهارس المركّبة تتبع مسارات التصفية الفعلية في صفحة المعرض،
            // لا كل عمود على حدة — انظر PaintingController::index
            $table->index(['painting_type', 'availability', 'price']);
            $table->index(['category_id', 'published_at']);
            $table->index(['is_featured', 'published_at']);
        });

        Schema::create('painting_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('painting_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            // مسارات النسخ المولّدة (thumb/medium/large) — انظر ImageService
            $table->json('variants')->nullable();
            $table->string('alt_text')->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['painting_id', 'is_main']);
            $table->index(['painting_id', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('painting_tag', function (Blueprint $table): void {
            $table->foreignId('painting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['painting_id', 'tag_id']);
        });

        // المقاسات المتاحة للوحة القابلة للحجز
        Schema::create('painting_dimension_preset', function (Blueprint $table): void {
            $table->foreignId('painting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dimension_preset_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->primary(['painting_id', 'dimension_preset_id'], 'painting_preset_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('painting_dimension_preset');
        Schema::dropIfExists('painting_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('painting_images');
        Schema::dropIfExists('paintings');
        Schema::dropIfExists('dimension_presets');
        Schema::dropIfExists('categories');
    }
};
