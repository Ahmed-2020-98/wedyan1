<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            // الحساب المعطَّل يبقى في الجدول ولا يُحذف: طلباته وفواتيره
            // مرتبطة به، وحذفه يعني فقدان سجلّ محاسبي.
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'avatar_path', 'is_active', 'last_login_at']);
        });
    }
};
