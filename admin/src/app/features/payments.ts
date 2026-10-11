import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Money } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

interface PaymentRow {
  paymentNumber: string;
  gateway: string;
  kindLabel: string;
  amount: Money;
  refundedAmount: Money;
  refundableAmount: Money;
  status: string;
  statusLabel: string;
  failureReason: string | null;
  payableType: string;
  customer: string | null;
  paidAt: string | null;
  createdAt: string;
}

@Component({
  selector: 'app-manage-payments',
  imports: [Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-7">
      <h1 class="font-display text-3xl text-ink-900 mb-4">المدفوعات</h1>
      <div class="flex flex-wrap gap-2">
        @for (f of filters; track f.value) {
          <button type="button" (click)="status.set(f.value)"
                  class="text-sm px-4 py-2 border transition-colors"
                  [class]="status() === f.value ? 'border-ink-900 bg-ink-900 text-canvas-50' : 'border-ink-200 text-ink-600 hover:border-ink-400'">
            {{ f.label }}
          </button>
        }
      </div>
    </header>

    @if (error()) {
      <p class="bg-danger/8 border-s-2 border-danger px-4 py-3 text-sm text-danger mb-6" role="alert">{{ error() }}</p>
    }

    @if (payments.isLoading()) {
      <app-spinner />
    } @else if (payments.value(); as res) {
      <div class="frame rounded-card overflow-x-auto">
        <table class="w-full text-sm min-w-[46rem]">
          <thead>
            <tr class="text-xs text-ink-400 border-b border-ink-100">
              <th class="text-start font-normal px-5 py-4">رقم العملية</th>
              <th class="text-start font-normal px-5 py-4">الزبون</th>
              <th class="text-start font-normal px-5 py-4">النوع</th>
              <th class="text-start font-normal px-5 py-4">المبلغ</th>
              <th class="text-start font-normal px-5 py-4">مسترجع</th>
              <th class="text-start font-normal px-5 py-4">الحالة</th>
              <th class="text-start font-normal px-5 py-4">التاريخ</th>
              <th class="text-start font-normal px-5 py-4"></th>
            </tr>
          </thead>
          <tbody>
            @for (p of res.data; track p.paymentNumber) {
              <tr class="border-b border-ink-50">
                <td class="px-5 py-4 text-ink-700 text-xs" data-numeric>{{ p.paymentNumber }}</td>
                <td class="px-5 py-4 text-ink-600">{{ p.customer }}</td>
                <td class="px-5 py-4 text-ink-500 text-xs">{{ p.kindLabel }}</td>
                <td class="px-5 py-4 text-ink-800" data-numeric>{{ p.amount | money }}</td>
                <td class="px-5 py-4 text-ink-500" data-numeric>
                  {{ p.refundedAmount.amount > 0 ? (p.refundedAmount | money) : '—' }}
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5" [class]="badge(p.status)">{{ p.statusLabel }}</span>
                </td>
                <td class="px-5 py-4 text-ink-400 text-xs" data-numeric>{{ (p.paidAt || p.createdAt) | arDate }}</td>
                <td class="px-5 py-4 text-end">
                  @if (p.refundableAmount.amount > 0 && p.status === 'paid') {
                    <button type="button" (click)="refund(p)" [disabled]="busy()"
                            class="text-xs text-danger hover:underline disabled:opacity-50">استرجاع</button>
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      <p class="text-xs text-ink-400 mt-4 leading-relaxed">
        الاسترجاع يُنفَّذ لدى مزوّد الدفع. كل حدث يصل منه مسجَّل بحمولته الخام في
        <span data-numeric>payment_transactions</span> ولا يُحذف.
      </p>
    }
  `,
})
export class ManagePaymentsScreen {
  private readonly api = inject(Api);

  protected readonly status = signal('');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly filters = [
    { value: '', label: 'الكل' },
    { value: 'paid', label: 'مدفوعة' },
    { value: 'pending', label: 'معلّقة' },
    { value: 'failed', label: 'فاشلة' },
    { value: 'refunded', label: 'مسترجعة' },
  ];

  protected readonly payments = resource({
    params: () => this.status(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<{ data: PaymentRow[] }>('/admin/payments', params ? { status: params } : undefined)),
  });

  protected refund(payment: PaymentRow): void {
    this.busy.set(true);
    this.error.set(null);

    this.api.post(`/admin/payments/${payment.paymentNumber}/refund`, {}).subscribe({
      next: () => { this.busy.set(false); this.payments.reload(); },
      error: (err) => {
        this.busy.set(false);
        this.error.set(err?.error?.message ?? 'تعذّر تنفيذ الاسترجاع.');
      },
    });
  }

  protected badge(status: string): string {
    if (status === 'paid') return 'bg-success/12 text-success';
    if (status === 'failed' || status === 'cancelled') return 'bg-danger/10 text-danger';
    if (status.includes('refunded')) return 'bg-info/12 text-info';
    return 'bg-warning/12 text-warning';
  }
}
