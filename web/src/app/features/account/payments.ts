import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Money } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

interface PaymentRow {
  paymentNumber: string;
  amount: Money;
  kindLabel: string;
  status: string;
  statusLabel: string;
  reference: string | null;
  paidAt: string | null;
  createdAt: string;
}

@Component({
  selector: 'app-account-payments',
  imports: [Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h2 class="font-display text-2xl text-ink-900 mb-7">سجلّ المدفوعات</h2>

    @if (payments.isLoading()) {
      <app-spinner />
    } @else if (payments.value(); as res) {
      @if (res.data.length === 0) {
        <p class="text-sm text-ink-400 py-8">لا توجد عمليات دفع بعد.</p>
      } @else {
        <div class="overflow-x-auto">
          <table class="w-full text-sm min-w-[36rem]">
            <thead>
              <tr class="text-start text-xs text-ink-400 border-b border-ink-100">
                <th class="text-start font-normal pb-3">رقم العملية</th>
                <th class="text-start font-normal pb-3">المرجع</th>
                <th class="text-start font-normal pb-3">النوع</th>
                <th class="text-start font-normal pb-3">المبلغ</th>
                <th class="text-start font-normal pb-3">الحالة</th>
                <th class="text-start font-normal pb-3">التاريخ</th>
              </tr>
            </thead>
            <tbody>
              @for (p of res.data; track p.paymentNumber) {
                <tr class="border-b border-ink-50">
                  <td class="py-3.5 text-ink-700" data-numeric>{{ p.paymentNumber }}</td>
                  <td class="py-3.5 text-ink-500" data-numeric>{{ p.reference || '—' }}</td>
                  <td class="py-3.5 text-ink-500">{{ p.kindLabel }}</td>
                  <td class="py-3.5 text-ink-800" data-numeric>{{ p.amount | money }}</td>
                  <td class="py-3.5">
                    <span class="text-xs px-2 py-0.5"
                          [class]="p.status === 'paid' ? 'bg-success/12 text-success' : 'bg-canvas-200 text-ink-500'">
                      {{ p.statusLabel }}
                    </span>
                  </td>
                  <td class="py-3.5 text-ink-400 text-xs" data-numeric>{{ (p.paidAt || p.createdAt) | arDate }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    }
  `,
})
export class AccountPaymentsScreen {
  private readonly api = inject(Api);

  protected readonly payments = resource({
    loader: () => firstValueFrom(this.api.get<{ data: PaymentRow[] }>('/account/payments')),
  });
}
