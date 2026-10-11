import { ChangeDetectionStrategy, Component, inject, input, signal, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Order, Resource } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-order-detail',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a routerLink="/account/orders" class="text-sm text-ink-400 hover:text-ink-700 mb-6 inline-block transition-colors">
      → كل الطلبات
    </a>

    @if (order.isLoading()) {
      <app-spinner />
    } @else if (order.value(); as res) {
      @if (res.data; as o) {
        <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
          <div>
            <h2 class="font-display text-2xl text-ink-900 mb-1" data-numeric>{{ o.orderNumber }}</h2>
            <p class="text-xs text-ink-400" data-numeric>{{ o.timeline.placedAt | arDateTime }}</p>
          </div>
          <div class="flex gap-2">
            <span class="text-xs px-3 py-1.5 bg-canvas-200 text-ink-700">{{ o.statusLabel }}</span>
            <span class="text-xs px-3 py-1.5"
                  [class]="o.paymentStatus === 'paid' ? 'bg-success/12 text-success' : 'bg-warning/12 text-warning'">
              {{ o.paymentStatusLabel }}
            </span>
          </div>
        </div>

        <!-- مسار الطلب -->
        <ol class="flex items-center gap-1 mb-9 text-[0.7rem]">
          @for (step of timeline(o); track step.label) {
            <li class="flex-1">
              <div class="h-1 mb-2" [class]="step.done ? 'bg-gilt-500' : 'bg-ink-100'"></div>
              <span [class]="step.done ? 'text-ink-700' : 'text-ink-300'">{{ step.label }}</span>
            </li>
          }
        </ol>

        <section class="frame rounded-card p-6 mb-6">
          <h3 class="font-display text-lg text-ink-900 mb-5">الأعمال</h3>
          <ul class="space-y-4">
            @for (item of o.items; track item.title) {
              <li class="flex gap-4">
                @if (item.imageUrl) {
                  <img [src]="item.imageUrl" [alt]="item.title" loading="lazy" class="w-16 h-auto bg-canvas-200 shrink-0" />
                }
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-ink-800">{{ item.title }}</p>
                  @if (item.dimensions) {
                    <p class="text-xs text-ink-400 mt-0.5" data-numeric>{{ item.dimensions | dimensions }}</p>
                  }
                  <p class="text-xs text-ink-400 mt-0.5" data-numeric>×{{ item.quantity }}</p>
                </div>
                <span class="text-sm text-ink-800 shrink-0" data-numeric>{{ item.lineTotal | money }}</span>
              </li>
            }
          </ul>

          <dl class="mt-6 pt-5 border-t border-ink-100 space-y-2.5 text-sm">
            <div class="flex justify-between"><dt class="text-ink-400">الإجمالي الفرعي</dt><dd data-numeric>{{ o.totals.subtotal | money }}</dd></div>
            @if (o.totals.discount.amount > 0) {
              <div class="flex justify-between"><dt class="text-ink-400">الخصم @if (o.couponCode) { ({{ o.couponCode }}) }</dt><dd class="text-success" data-numeric>− {{ o.totals.discount | money }}</dd></div>
            }
            <div class="flex justify-between"><dt class="text-ink-400">الشحن</dt><dd data-numeric>{{ o.totals.shipping | money }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-400">الضريبة</dt><dd data-numeric>{{ o.totals.tax | money }}</dd></div>
            <div class="flex justify-between pt-3 border-t border-ink-100 font-display text-lg text-ink-900">
              <dt>الإجمالي</dt><dd data-numeric>{{ o.totals.total | money }}</dd>
            </div>
          </dl>
        </section>

        <section class="frame rounded-card p-6 mb-6">
          <h3 class="font-display text-lg text-ink-900 mb-4">الشحن</h3>
          <p class="text-sm text-ink-700 leading-relaxed">
            {{ o.shipping.name }}<br />
            {{ o.shipping.street }}@if (o.shipping.district) {، {{ o.shipping.district }}}<br />
            {{ o.shipping.city }}<br />
            <span data-numeric>{{ o.shipping.phone }}</span>
          </p>
          @if (o.shipping.trackingNumber) {
            <p class="text-sm text-ink-800 mt-4 pt-4 border-t border-ink-100">
              رقم التتبّع: <span data-numeric>{{ o.shipping.trackingNumber }}</span>
            </p>
          }
        </section>

        @if (o.isCancellable) {
          @if (cancelError()) { <p class="text-sm text-danger mb-3">{{ cancelError() }}</p> }
          <button type="button" (click)="cancel(o.orderNumber)" [disabled]="busy()"
                  class="text-sm text-danger border border-danger/30 px-5 py-2.5 hover:bg-danger/5 disabled:opacity-50 transition-colors">
            إلغاء الطلب
          </button>
        }
      }
    }
  `,
})
export class OrderDetailScreen {
  private readonly api = inject(Api);

  /** معامل المسار — input() لا signal(). */
  readonly orderNumber = input.required<string>();

  protected readonly busy = signal(false);
  protected readonly cancelError = signal<string | null>(null);

  protected readonly order = resource({
    params: () => this.orderNumber(),
    loader: ({ params }) => firstValueFrom(this.api.get<Resource<Order>>(`/orders/${params}`)),
  });

  protected timeline(o: Order) {
    return [
      { label: 'أُنشئ', done: !!o.timeline.placedAt },
      { label: 'دُفع', done: !!o.timeline.paidAt },
      { label: 'شُحن', done: !!o.timeline.shippedAt },
      { label: 'سُلِّم', done: !!o.timeline.deliveredAt },
    ];
  }

  protected cancel(orderNumber: string): void {
    this.busy.set(true);
    this.cancelError.set(null);

    this.api.post(`/orders/${orderNumber}/cancel`).subscribe({
      next: () => { this.busy.set(false); this.order.reload(); },
      error: (err) => {
        this.busy.set(false);
        this.cancelError.set(err?.error?.message ?? 'تعذّر إلغاء الطلب.');
      },
    });
  }
}
