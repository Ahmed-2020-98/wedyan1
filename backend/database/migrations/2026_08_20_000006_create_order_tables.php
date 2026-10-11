<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('status', 32)->default('pending');
            $table->string('payment_status', 32)->default('pending');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);
            $table->string('currency', 3)->default('SAR');

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 64)->nullable();

            /*
             | العنوان مُجمَّد داخل الطلب لا مرتبطاً بجدول العناوين.
             | لو عدّل الزبون عنوانه بعد الشحن لتغيّر عنوان طلب قديم أُرسل
             | فعلاً إلى مكان آخر — وهو ما يُفسد أي نزاع أو تتبّع لاحق.
             */
            $table->string('shipping_name');
            $table->string('shipping_phone', 20);
            $table->string('shipping_country', 2)->default('SA');
            $table->string('shipping_city');
            $table->string('shipping_district')->nullable();
            $table->string('shipping_street');
            $table->string('shipping_postal_code', 16)->nullable();
            $table->text('shipping_notes')->nullable();

            $table->string('customer_email');
            $table->string('customer_phone', 20);

            $table->foreignId('shipping_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number', 64)->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // اللوحة قد تُحذف لاحقاً، لكن سطر الطلب يبقى بلقطته الخاصة.
            $table->foreignId('painting_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('sku', 64)->nullable();
            $table->string('image_path')->nullable();
            $table->json('dimensions')->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
