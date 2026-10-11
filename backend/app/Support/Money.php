<?php

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

/**
 * تمثيل موحّد للمبالغ في مخرجات الواجهة البرمجية.
 *
 * نُخرج القيمة الخام والنص المنسّق معاً: الخام للحساب والمقارنة في الواجهة،
 * والمنسّق حتى يكون مصدر التنسيق واحداً. لولا ذلك لاختلف شكل السعر بين
 * صفحة اللوحة والفاتورة ورسالة البريد، وهو فرق يلاحظه الزبون ويقلق منه.
 */
final readonly class Money
{
    public function __construct(
        public float $amount,
        public string $currency = 'SAR',
    ) {}

    public static function of(float|string|null $amount, ?string $currency = null): self
    {
        return new self(
            (float) ($amount ?? 0),
            $currency ?? (string) config('platform.currency', 'SAR'),
        );
    }

    /**
     * الأرقام لاتينية والتقويم ميلادي — نفس القرار المتَّخذ في الواجهة.
     * انظر التعليق في web/src/app/shared/pipes.ts
     */
    public function formatted(): string
    {
        $formatter = new NumberFormatter('ar-SA-u-nu-latn', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);

        return (string) $formatter->formatCurrency($this->amount, $this->currency);
    }

    /** @return array{amount: float, currency: string, formatted: string} */
    public function toArray(): array
    {
        return [
            'amount' => round($this->amount, 2),
            'currency' => $this->currency,
            'formatted' => $this->formatted(),
        ];
    }
}
