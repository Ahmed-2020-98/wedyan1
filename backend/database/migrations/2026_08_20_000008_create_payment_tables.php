<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            /*
             | متعدّد الأشكال لأن الدفعة قد تخصّ طلباً أو حجزاً.
             | والحجز قد يحمل أكثر من دفعة (عربون ثم المتبقّي)، ولذلك المبلغ
             | هنا لا في جدول الحجز.
             */
            $table->morphs('payable');

            $table->string('kind', 16)->default('full');
            $table->string('gateway', 32);
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_reference')->nullable();

            $table->decimal('amount', 10, 2);
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('status', 32)->default('pending');

            $table->string('failure_reason')->nullable();
            // لا تُخزَّن أي بيانات بطاقة هنا؛ آخر أربعة أرقام والعلامة فقط.
            $table->json('meta')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('gateway_payment_id');
        });

        /*
         | سجلّ كل حدث وصل من المزوّد بحمولته الخام.
         | لا يُحذف ولا يُعدَّل: عند أي نزاع محاسبي هذا هو الدليل، وهو أيضاً
         | ما يسمح بإعادة معالجة خطّاف فشل دون مطالبة المزوّد بإعادة إرساله.
         */
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);
            $table->string('gateway', 32);
            $table->string('gateway_reference')->nullable();
            $table->string('status', 32)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->index(['gateway', 'gateway_reference']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
