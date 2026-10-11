<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('reservation_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_id')->constrained('users')->restrictOnDelete();

            $table->string('status', 32)->default('pending');

            /*
             | السعر يبقى null حتى يقبل الفنان الطلب ويحدّده: اللوحة المخصّصة
             | تُسعَّر بعد فهم المطلوب، لا قبله. ولذلك لا يمكن الدفع قبل القبول.
             */
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedTinyInteger('deposit_percentage')->default(50);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            $table->text('customer_note')->nullable();
            $table->text('artist_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->date('due_date')->nullable();

            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone', 20)->nullable();
            $table->string('shipping_country', 2)->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_district')->nullable();
            $table->string('shipping_street')->nullable();
            $table->string('shipping_postal_code', 16)->nullable();
            $table->string('tracking_number', 64)->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['artist_id', 'status']);
        });

        Schema::create('reservation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            // null حين يكون الطلب لوحة جديدة كلياً لا نسخة من عمل معروض.
            $table->foreignId('painting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dimension_preset_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->decimal('depth', 8, 2)->nullable();
            $table->string('unit', 8)->default('cm');
            $table->string('preferred_colors')->nullable();
            $table->string('reference_image_path')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();

            $table->index('reservation_id');
        });

        // سجلّ مراحل الرسم الذي يراه الزبون. هو ما يجعل انتظار شهرين محتملاً.
        Schema::create('reservation_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_from', 32)->nullable();
            $table->string('status_to', 32)->nullable();
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->nullable();
            // التحديث الداخلي لا يظهر للزبون — ملاحظات الفنان لنفسه.
            $table->boolean('is_visible_to_customer')->default(true);
            $table->timestamps();

            $table->index(['reservation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_updates');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('reservations');
    }
};
