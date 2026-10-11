<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * توليد المُعرِّفات اللطيفة (slugs) مع الإبقاء على الحروف العربية.
 *
 * Str::slug تنقل العربية حرفياً إلى لاتينية، فيصير «تجريدي» → «tgrydy»:
 * عنوان لا يقرأه أحد ولا يطابق ما يكتبه الباحث في جوجل. والمنصة عربية
 * بالكامل وجمهورها يبحث بالعربية، فالإبقاء على الحروف هو الخيار الصحيح
 * لتحسين الظهور، والمتصفحات تعرض العنوان مفكوك الترميز لا مرمّزاً.
 */
final class Slug
{
    public static function make(string $value): string
    {
        $value = trim($value);

        // توحيد المسافات وعلامات الوصل
        $value = preg_replace('/[\s_]+/u', '-', $value) ?? $value;

        // إبقاء العربية والحروف اللاتينية والأرقام وعلامة الوصل فقط
        $value = preg_replace('/[^\p{Arabic}\p{L}\p{N}-]+/u', '', $value) ?? $value;

        // إزالة التشكيل، فهو غير مرئي في العنوان ويكسر المطابقة
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $value) ?? $value;

        $value = preg_replace('/-+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * مُعرِّف فريد داخل جدول. يضيف لاحقة رقمية عند التعارض بدل سلسلة
     * عشوائية، فيبقى العنوان مقروءاً: «سكون-الصحراء-2» لا «سكون-الصحراء-x7f3q».
     */
    public static function unique(string $value, string $table, string $column = 'slug', ?int $ignoreId = null): string
    {
        $base = self::make($value);

        if ($base === '') {
            $base = Str::random(8);
        }

        $slug = $base;
        $suffix = 1;

        while (self::exists($slug, $table, $column, $ignoreId)) {
            $slug = $base . '-' . (++$suffix);
        }

        return $slug;
    }

    private static function exists(string $slug, string $table, string $column, ?int $ignoreId): bool
    {
        return \Illuminate\Support\Facades\DB::table($table)
            ->where($column, $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
