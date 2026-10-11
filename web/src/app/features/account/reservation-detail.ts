import { ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Reservation, Resource } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-reservation-detail',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a routerLink="/account/reservations" class="text-sm text-ink-400 hover:text-ink-700 mb-6 inline-block transition-colors">
      → كل الحجوزات
    </a>

    @if (reservation.isLoading()) {
      <app-spinner />
    } @else if (reservation.value(); as res) {
      @if (res.data; as r) {
        <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
          <div>
            <h2 class="font-display text-2xl text-ink-900 mb-1" data-numeric>{{ r.reservationNumber }}</h2>
            <p class="text-xs text-ink-400" data-numeric>{{ r.timeline.requestedAt | arDateTime }}</p>
          </div>
          <span class="text-xs px-3 py-1.5 bg-canvas-200 text-ink-700">{{ r.statusLabel }}</span>
        </div>

        <!-- ============ الدفع المستحق ============ -->
        @if (r.awaitsPayment && r.amountDueNow.amount > 0) {
          <section class="bg-gilt-500/8 border border-gilt-500/25 rounded-card p-6 mb-6">
            <h3 class="font-display text-lg text-ink-900 mb-2">
              @if (r.paidAmount.amount <= 0) { العربون مستحق } @else { المبلغ المتبقّي مستحق }
            </h3>
            <p class="text-sm text-ink-600 leading-relaxed mb-5">
              @if (r.paidAmount.amount <= 0) {
                قبلت الفنانة طلبك وحدّدت السعر. ادفع العربون ليبدأ التنفيذ.
              } @else {
                اكتملت لوحتك. سدّد المتبقّي لنبدأ إجراءات الشحن.
              }
            </p>

            <p class="font-display text-3xl text-ink-900 mb-5" data-numeric>{{ r.amountDueNow | money }}</p>

            @if (payError()) { <p class="text-sm text-danger mb-3" role="alert">{{ payError() }}</p> }

            <button type="button" (click)="pay(r.reservationNumber)" [disabled]="paying()"
                    class="bg-ink-900 text-canvas-50 px-7 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
              @if (paying()) { …جارٍ التحويل } @else { ادفع الآن }
            </button>
          </section>
        }

        @if (r.status === 'rejected' && r.rejectionReason) {
          <section class="bg-danger/8 border-s-2 border-danger p-5 mb-6">
            <p class="text-sm text-ink-800 mb-1">اعتذرت الفنانة عن هذا الطلب</p>
            <p class="text-sm text-ink-600 leading-relaxed">{{ r.rejectionReason }}</p>
          </section>
        }

        <!-- ============ تفاصيل ============ -->
        <section class="frame rounded-card p-6 mb-6">
          <h3 class="font-display text-lg text-ink-900 mb-5">تفاصيل الطلب</h3>

          @for (item of r.items; track item.title) {
            <div class="mb-5 last:mb-0">
              <p class="text-sm text-ink-800 mb-1">{{ item.title }}</p>
              @if (item.dimensions) {
                <p class="text-xs text-ink-400 mb-2" data-numeric>{{ item.dimensions | dimensions }}</p>
              }
              @if (item.description) {
                <p class="text-sm text-ink-600 leading-relaxed">{{ item.description }}</p>
              }
              @if (item.preferredColors) {
                <p class="text-xs text-ink-400 mt-2">ألوان مفضّلة: {{ item.preferredColors }}</p>
              }
            </div>
          }

          @if (r.artistNote) {
            <div class="mt-5 pt-5 border-t border-ink-100">
              <p class="text-xs text-ink-400 mb-1.5">ملاحظة الفنانة</p>
              <p class="text-sm text-ink-700 leading-relaxed">{{ r.artistNote }}</p>
            </div>
          }

          @if (r.price) {
            <dl class="mt-5 pt-5 border-t border-ink-100 space-y-2.5 text-sm">
              <div class="flex justify-between"><dt class="text-ink-400">السعر</dt><dd data-numeric>{{ r.price | money }}</dd></div>
              <div class="flex justify-between"><dt class="text-ink-400">المدفوع</dt><dd class="text-success" data-numeric>{{ r.paidAmount | money }}</dd></div>
              <div class="flex justify-between"><dt class="text-ink-400">المتبقّي</dt><dd data-numeric>{{ r.remainingAmount | money }}</dd></div>
              @if (r.dueDate) {
                <div class="flex justify-between"><dt class="text-ink-400">التسليم المتوقّع</dt><dd data-numeric>{{ r.dueDate | arDate }}</dd></div>
              }
            </dl>
          }
        </section>

        <!-- ============ مراحل التنفيذ ============ -->
        @if (r.updates?.length) {
          <section class="frame rounded-card p-6">
            <div class="flex items-center justify-between mb-6">
              <h3 class="font-display text-lg text-ink-900">مراحل التنفيذ</h3>
              @if (r.status === 'in_production') {
                <span class="text-sm text-gilt-700" data-numeric>{{ r.progressPercentage }}٪</span>
              }
            </div>

            @if (r.progressPercentage > 0) {
              <div class="h-1.5 bg-ink-100 mb-7 overflow-hidden">
                <div class="h-full bg-gilt-500 transition-all duration-700" [style.width.%]="r.progressPercentage"></div>
              </div>
            }

            <ol class="space-y-6">
              @for (u of r.updates; track u.createdAt) {
                <li class="relative ps-6 border-s border-ink-100 pb-1">
                  <span class="absolute start-0 top-1.5 -translate-x-1/2 rtl:translate-x-1/2 w-2 h-2 rounded-full bg-gilt-500"></span>
                  <p class="text-sm text-ink-800 mb-1">{{ u.title }}</p>
                  @if (u.note) { <p class="text-sm text-ink-500 leading-relaxed mb-2">{{ u.note }}</p> }
                  @if (u.imageUrl) {
                    <img [src]="u.imageUrl" [alt]="u.title" loading="lazy" class="max-w-xs w-full h-auto my-3 bg-canvas-200" />
                  }
                  <p class="text-xs text-ink-300" data-numeric>{{ u.createdAt | arDateTime }}</p>
                </li>
              }
            </ol>
          </section>
        }
      }
    }
  `,
})
export class ReservationDetailScreen {
  private readonly api = inject(Api);

  /** معامل المسار — input() لا signal(). */
  readonly number = input.required<string>();

  protected readonly paying = signal(false);
  protected readonly payError = signal<string | null>(null);

  protected readonly reservation = resource({
    params: () => this.number(),
    loader: ({ params }) => firstValueFrom(this.api.get<Resource<Reservation>>(`/reservations/${params}`)),
  });

  protected pay(number: string): void {
    this.paying.set(true);
    this.payError.set(null);

    this.api.post<{ data: { redirectUrl: string } }>(`/reservations/${number}/pay`).subscribe({
      next: (res) => { window.location.href = res.data.redirectUrl; },
      error: (err) => {
        this.paying.set(false);
        this.payError.set(err?.error?.message ?? 'تعذّر بدء عملية الدفع.');
      },
    });
  }
}
