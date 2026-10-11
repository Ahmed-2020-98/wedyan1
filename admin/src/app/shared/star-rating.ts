import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/** عرض التقييم بالنجوم. للقراءة فقط — الإدخال في نموذج المراجعة. */
@Component({
  selector: 'app-star-rating',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <span
      class="inline-flex items-center gap-0.5 align-middle"
      [attr.aria-label]="'التقييم ' + rating() + ' من 5'"
    >
      @for (star of stars(); track $index) {
        <svg viewBox="0 0 20 20" class="w-3.5 h-3.5" [class]="star ? 'fill-gilt-500' : 'fill-ink-200'" aria-hidden="true">
          <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L1.5 7.7l5.9-.9z"/>
        </svg>
      }
      @if (count(); as c) {
        <span class="text-xs text-ink-400 ms-1.5" data-numeric>({{ c }})</span>
      }
    </span>
  `,
})
export class StarRating {
  readonly rating = input.required<number>();
  readonly count = input<number | null>(null);

  protected readonly stars = computed(() =>
    Array.from({ length: 5 }, (_, i) => i < Math.round(this.rating())),
  );
}
