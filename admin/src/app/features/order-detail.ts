import { ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Order, Resource } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-order-detail',
  imports: [FormsModule, RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a routerLink="/orders" class="text-sm text-ink-400 hover:text-ink-700 mb-6 inline-block transition-colors">
      → كل الطلبات
    </a>

    @if (order.isLoading()) {
      <app-spinner />
    } @else if (order.value(); as res) {
      @if (res.data; as o) {
        <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
          <div>
            <h1 class="font-display text-2xl text-ink-900 mb-1" data-numeric>{{ o.orderNumber }}</h1>
            <p class="text-sm text-ink-500">
              {{ o.customer?.name }} · <span dir="ltr">{{ o.customer?.email }}</span>
            </p>
          </div>
          <div class="flex gap-2">
            <span class="text-xs px-3 py-1.5"
                  [class]="o.paymentStatus === 'paid' ? 'bg-success/12 text-success' : 'bg-warning/12 text-warning'">
              {{ o.paymentStatusLabel }}
            </span>
            <span class="text-xs px-3 py-1.5 bg-canvas-200 text-ink-700">{{ o.statusLabel }}</span>
          </div>
        </div>

        @if (error()) {
          <p class="bg-danger/8 border-s-2 border-danger px-4 py-3 text-sm text-danger mb-6" role="alert">{{ error() }}</p>
        }

        <div class="grid lg:grid-cols-[1fr_20rem] gap-6 items-start">
          <div class="space-y-6">
            <section class="frame rounded-card p-6">
              <h2 class="font-display text-lg text-ink-900 mb-5">الأعمال</h2>
              <ul class="space-y-4">
                @for (item of o.items; track item.title) {
                  <li class="flex gap-4">
                    @if (item.imageUrl) {
                      <img [src]="item.imageUrl" [alt]="item.title" class="w-14 h-auto bg-canvas-200 shrink-0" />
                    }
                    <div class="flex-1 min-w-0">
                      <p class="text-sm text-ink-800">{{ item.title }}</p>
                      <p class="text-xs text-ink-400 mt-0.5" data-numeric>{{ item.sku }} · ×{{ item.quantity }}</p>
                    </div>
                    <span class="text-sm text-ink-800 shrink-0" data-numeric>{{ item.lineTotal | money }}</span>
                  </li>
                }
              </ul>

              <dl class="mt-5 pt-5 border-t border-ink-100 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-ink-400">الفرعي</dt><dd data-numeric>{{ o.totals.subtotal | money }}</dd></div>
                @if (o.totals.discount.amount > 0) {
                  <div class="flex justify-between"><dt class="text-ink-400">الخصم</dt><dd class="text-success" data-numeric>− {{ o.totals.discount | money }}</dd></div>
                }
                <div class="flex justify-between"><dt class="text-ink-400">الشحن</dt><dd data-numeric>{{ o.totals.shipping | money }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-400">الضريبة</dt><dd data-numeric>{{ o.totals.tax | money }}</dd></div>
                <div class="flex justify-between pt-3 border-t border-ink-100 font-display text-lg text-ink-900">
                  <dt>الإجمالي</dt><dd data-numeric>{{ o.totals.total | money }}</dd>
                </div>
              </dl>
            </section>

            <section class="frame rounded-card p-6">
              <h2 class="font-display text-lg text-ink-900 mb-4">الشحن</h2>
              <p class="text-sm text-ink-600 leading-relaxed">
                {{ o.shipping.name }}<br />
                {{ o.shipping.street }}@if (o.shipping.district) {، {{ o.shipping.district }}}<br />
                {{ o.shipping.city }}، {{ o.shipping.country }}<br />
                <span data-numeric>{{ o.shipping.phone }}</span>
              </p>
              @if (o.shipping.notes) {
                <p class="text-sm text-ink-500 mt-3 pt-3 border-t border-ink-100">{{ o.shipping.notes }}</p>
              }
            </section>
          </div>

          <aside class="frame rounded-card p-5 lg:sticky lg:top-6">
            <h2 class="font-display text-base text-ink-900 mb-4">تغيير الحالة</h2>

            @if (transitions.value(); as t) {
              @if (t.data.length === 0) {
                <p class="text-sm text-ink-400">لا انتقالات متاحة من هذه الحالة.</p>
              } @else {
                <div class="space-y-2.5">
                  @if (needsTracking()) {
                    <label class="block">
                      <span class="block text-sm text-ink-600 mb-2">رقم التتبّع</span>
                      <input [(ngModel)]="tracking" type="text" placeholder="SA123456789"
                             class="w-full bg-canvas-50 border border-ink-200 px-3 py-2.5 text-sm outline-none focus:border-gilt-500" data-numeric />
                    </label>
                  }

                  @for (s of t.data; track s.value) {
                    <button type="button" (click)="apply(s.value)" [disabled]="busy()"
                            class="w-full text-sm py-2.5 border transition-colors disabled:opacity-50"
                            [class]="s.value === 'cancelled' || s.value === 'refunded'
                              ? 'border-danger/40 text-danger hover:bg-danger/5'
                              : 'border-ink-300 text-ink-700 hover:border-ink-900'">
                      {{ s.label }}
                    </button>
                  }
                </div>
              }
            }
          </aside>
        </div>
      }
    }
  `,
})
export class ManageOrderDetailScreen {
  private readonly api = inject(Api);

  readonly orderNumber = input.required<string>();

  protected tracking = '';
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly needsTracking = signal(true);

  protected readonly order = resource({
    params: () => this.orderNumber(),
    loader: ({ params }) => firstValueFrom(this.api.get<Resource<Order>>(`/manage/orders/${params}`)),
  });

  protected readonly transitions = resource({
    params: () => this.orderNumber(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<{ data: { value: string; label: string }[] }>(`/manage/orders/${params}/transitions`)),
  });

  protected apply(status: string): void {
    this.busy.set(true);
    this.error.set(null);

    this.api
      .put(`/manage/orders/${this.orderNumber()}/status`, {
        status,
        tracking_number: this.tracking || null,
      })
      .subscribe({
        next: () => {
          this.busy.set(false);
          this.order.reload();
          this.transitions.reload();
        },
        error: (err) => {
          this.busy.set(false);
          this.error.set(err?.error?.errors?.status?.[0] ?? err?.error?.message ?? 'تعذّر تغيير الحالة.');
        },
      });
  }
}
