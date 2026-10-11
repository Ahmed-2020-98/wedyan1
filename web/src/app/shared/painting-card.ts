import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

import type { Painting } from '../core/models';
import { AR_PIPES } from './pipes';

/**
 * بطاقة اللوحة.
 *
 * الصورة داخل «باسبارتو» بنسبة ثابتة (انظر .mat في styles.css): الأعمال
 * بمقاسات مختلفة جداً، فإما شبكة غير منتظمة أو قصّ يشوّه التكوين. الإطار
 * الثابت يحلّ الاثنين — كما تفعل صالات العرض تماماً.
 */
@Component({
  selector: 'app-painting-card',
  imports: [RouterLink, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a [routerLink]="['/paintings', painting().slug]" class="group block card overflow-hidden"
       [attr.aria-label]="painting().title">

      <div class="mat">
        @if (painting().mainImage; as img) {
          <img
            [src]="img.medium"
            [srcset]="img.thumb + ' 400w, ' + img.medium + ' 900w'"
            sizes="(max-width: 640px) 45vw, (max-width: 1024px) 30vw, 22vw"
            [alt]="img.alt || painting().title"
            loading="lazy"
            decoding="async"
          />
        } @else {
          <span class="text-ink-300 text-xs">بلا صورة</span>
        }
      </div>

      <div class="p-3 sm:p-4">
        <div class="flex items-start justify-between gap-2 mb-1">
          <h3 class="font-display text-sm sm:text-base text-ink-900 leading-snug truncate">
            {{ painting().title }}
          </h3>

          @if (!painting().isPurchasable && painting().type === 'ready_made') {
            <span class="shrink-0 text-[0.65rem] px-1.5 py-0.5 bg-ink-100 text-ink-500">
              {{ painting().availabilityLabel }}
            </span>
          } @else if (painting().isReservable) {
            <span class="shrink-0 text-[0.65rem] px-1.5 py-0.5 bg-gilt-500/12 text-gilt-700">
              حجز
            </span>
          }
        </div>

        @if (painting().dimensions; as d) {
          <p class="text-[0.7rem] text-ink-400 mb-2" data-numeric>{{ d | dimensions }}</p>
        }

        <!--
          يلتفّ السعران عند الحاجة بدل أن يخرج المشطوب من حدود البطاقة:
          العمود على الجوال أضيق من أن يسع سعرين بالريال في سطر واحد.
        -->
        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 min-w-0">
          <span class="text-sm text-ink-800" data-numeric>{{ painting().effectivePrice | money }}</span>
          @if (painting().hasDiscount) {
            <span class="text-[0.7rem] text-ink-300 line-through" data-numeric>
              {{ painting().price | money }}
            </span>
          }
        </div>
      </div>
    </a>
  `,
})
export class PaintingCard {
  readonly painting = input.required<Painting>();
}
