<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->string('country', 2)->default('SA');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('street');
            $table->string('postal_code', 16)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // الزائر غير المسجّل يحمل رمزاً في المتصفح، وتُدمج سلّته في سلّة
            // حسابه عند تسجيل الدخول بدل أن يفقد ما اختاره.
            $table->uuid('guest_token')->nullable()->unique();
            $table->foreignId('coupon_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('painting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // اللوحة الواحدة تظهر مرّة واحدة في السلة؛ تكرارها يزيد الكمية.
            $table->unique(['cart_id', 'painting_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('painting_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'painting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};
