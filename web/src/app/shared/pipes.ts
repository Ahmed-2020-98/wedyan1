import { Pipe, PipeTransform } from '@angular/core';

import type { Dimensions, Money } from '../core/models';

/*
|------------------------------------------------------------------------------
| أنابيب العرض العربية
|------------------------------------------------------------------------------
| مبنية على Intl لا على مكتبة خارجية، ولا نستخدم أنابيب Angular الجاهزة
| (date | number | currency) في أي مكان.
|
| السبب: تسجيل بيانات المحلّية 'ar' في Angular يجلب معه الأرقام الهندية
| (٠١٢٣) والتقويم الهجري في بعض الصيغ، وكلاهما غير مرغوب هنا. سلسلة
| المحلّية أدناه مقصودة بأجزائها:
|   ca-gregory → تقويم ميلادي (تواريخ الطلبات والشحن ميلادية)
|   nu-latn    → أرقام لاتينية 123 لا ١٢٣، لأنها ما يظهر في إشعارات الدفع
|                وما يستطيع الزبون نسخه ولصقه في تتبّع الشحنة
*/

const AR_LOCALE = 'ar-SA-u-ca-gregory-nu-latn';

/**
 * مبلغ مالي. يفضَّل تمرير كائن Money القادم من الخادم لأن التنسيق يكون
 * قد تمّ هناك بعملة الطلب نفسها؛ والعدد المجرّد بديل احتياطي.
 */
@Pipe({ name: 'money' })
export class MoneyPipe implements PipeTransform {
  transform(value: Money | number | null | undefined, currency = 'SAR'): string {
    if (value === null || value === undefined) return '—';

    if (typeof value === 'number') {
      return new Intl.NumberFormat(AR_LOCALE, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
      }).format(value);
    }

    return value.formatted;
  }
}

/** تاريخ مختصر: 19 أغسطس 2026 */
@Pipe({ name: 'arDate' })
export class ArDatePipe implements PipeTransform {
  transform(value: string | Date | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat(AR_LOCALE, {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(new Date(value));
  }
}

/** تاريخ ووقت. */
@Pipe({ name: 'arDateTime' })
export class ArDateTimePipe implements PipeTransform {
  transform(value: string | Date | null | undefined): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat(AR_LOCALE, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(value));
  }
}

/**
 * مقاس اللوحة: «60 × 90 سم».
 *
 * الترتيب عرض × ارتفاع دائماً، وهو عُرف سوق اللوحات؛ عكسه يجعل الزبون
 * يتخيّل لوحة بأبعاد مختلفة تماماً عمّا سيصله.
 */
@Pipe({ name: 'dimensions' })
export class DimensionsPipe implements PipeTransform {
  private static readonly UNITS: Record<string, string> = {
    cm: 'سم',
    inch: 'بوصة',
    m: 'م',
  };

  transform(value: Dimensions | null | undefined): string {
    if (!value) return '—';

    const unit = DimensionsPipe.UNITS[value.unit] ?? value.unit;
    const parts = [value.width, value.height];
    if (value.depth) parts.push(value.depth);

    return `${parts.join(' × ')} ${unit}`;
  }
}

/** رقم جوال سعودي بصيغة محلية مقروءة: 0501234567 */
@Pipe({ name: 'saudiPhone' })
export class SaudiPhonePipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) return '—';

    const digits = value.replace(/\D+/g, '');

    return digits.startsWith('966') ? '0' + digits.slice(3) : digits;
  }
}

/**
 * حزمة واحدة تُنشر في imports بدل استيراد كل أنبوب على حدة:
 *
 *     imports: [...AR_PIPES]
 */
export const AR_PIPES = [
  MoneyPipe,
  ArDatePipe,
  ArDateTimePipe,
  DimensionsPipe,
  SaudiPhonePipe,
] as const;
