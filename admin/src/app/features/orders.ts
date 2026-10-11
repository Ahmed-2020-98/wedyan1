import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Order, Paginated } from '../core/models';
import { EmptyState } from '../shared/empty-state';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-orders',
  imports: [RouterLink, FormsModule, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-7">
      <h1 class="font-display text-3xl text-ink-900 mb-4">الطلبات</h1>

      <div class="flex flex-wrap gap-3 items-center">
        <input [(ngModel)]="term" (ngModelChange)="search.set($event)" type="search"
               placeholder="رقم الطلب أو اسم الزبون…"
               class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500 w-64" />

        <select [ngModel]="status()" (ngModelChange)="status.set($event)"
                class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500">
          <option value="">كل الحالات</option>
          @for (s of statuses; track s.value) { <option [value]="s.value">{{ s.label }}</option> }
        </select>
      </div>
    </header>

    @if (orders.isLoading()) {
      <app-spinner />
    } @else if (orders.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد طلبات" />
      } @else {
        <div class="frame rounded-card overflow-x-auto">
          <table class="w-full text-sm min-w-[42rem]">
            <thead>
              <tr class="text-xs text-ink-400 border-b border-ink-100">
                <th class="text-start font-normal px-5 py-4">رقم الطلب</th>
                <th class="text-start font-normal px-5 py-4">الزبون</th>
                <th class="text-start font-normal px-5 py-4">الإجمالي</th>
                <th class="text-start font-normal px-5 py-4">الدفع</th>
                <th class="text-start font-normal px-5 py-4">الحالة</th>
                <th class="text-start font-normal px-5 py-4">التاريخ</th>
              </tr>
            </thead>
            <tbody>
              @for (o of res.data; track o.orderNumber) {
                <tr class="border-b border-ink-50 hover:bg-canvas-200/40 transition-colors">
                  <td class="px-5 py-4">
                    <a [routerLink]="['/orders', o.orderNumber]" class="text-ink-800 hover:text-gilt-700" data-numeric>
                      {{ o.orderNumber }}
                    </a>
                  </td>
                  <td class="px-5 py-4 text-ink-600">{{ o.customer?.name }}</td>
                  <td class="px-5 py-4 text-ink-800" data-numeric>{{ o.totals.total | money }}</td>
                  <td class="px-5 py-4">
                    <span class="text-xs px-2 py-0.5"
                          [class]="o.paymentStatus === 'paid' ? 'bg-success/12 text-success' : 'bg-warning/12 text-warning'">
                      {{ o.paymentStatusLabel }}
                    </span>
                  </td>
                  <td class="px-5 py-4"><span class="text-xs px-2 py-0.5 bg-canvas-200 text-ink-600">{{ o.statusLabel }}</span></td>
                  <td class="px-5 py-4 text-ink-400 text-xs" data-numeric>{{ o.timeline.placedAt | arDate }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    }
  `,
})
export class ManageOrdersScreen {
  private readonly api = inject(Api);

  protected term = '';
  protected readonly search = signal('');
  protected readonly status = signal('');

  protected readonly statuses = [
    { value: 'payment_pending', label: 'بانتظار الدفع' },
    { value: 'paid', label: 'مدفوع' },
    { value: 'processing', label: 'قيد المعالجة' },
    { value: 'preparing', label: 'قيد التجهيز' },
    { value: 'shipped', label: 'تم الشحن' },
    { value: 'delivered', label: 'تم التسليم' },
    { value: 'cancelled', label: 'ملغى' },
  ];

  protected readonly orders = resource({
    params: () => ({ status: this.status(), search: this.search() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.api.get<Paginated<Order>>('/manage/orders', {
          status: params.status || undefined,
          search: params.search || undefined,
        }),
      ),
  });
}
