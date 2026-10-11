import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Order, Paginated } from '../../core/models';
import { EmptyState } from '../../shared/empty-state';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-account-orders',
  imports: [RouterLink, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h2 class="font-display text-2xl text-ink-900 mb-7">طلباتي</h2>

    @if (orders.isLoading()) {
      <app-spinner />
    } @else if (orders.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد طلبات بعد" note="حين تقتني عملاً سيظهر هنا مع حالته وتتبّعه.">
          <a routerLink="/paintings" class="inline-block mt-6 bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 transition-colors">
            تصفّح المعرض
          </a>
        </app-empty-state>
      } @else {
        <ul class="space-y-4">
          @for (o of res.data; track o.orderNumber) {
            <li>
              <a [routerLink]="['/account/orders', o.orderNumber]"
                 class="frame rounded-card p-5 flex flex-wrap items-center justify-between gap-4 block">
                <div>
                  <p class="text-sm text-ink-800 mb-1" data-numeric>{{ o.orderNumber }}</p>
                  <p class="text-xs text-ink-400" data-numeric>{{ o.timeline.placedAt | arDate }}</p>
                </div>

                <div class="flex items-center gap-5">
                  <span class="text-xs px-2.5 py-1" [class]="badge(o.status)">{{ o.statusLabel }}</span>
                  <span class="text-sm text-ink-800" data-numeric>{{ o.totals.total | money }}</span>
                </div>
              </a>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class AccountOrdersScreen {
  private readonly api = inject(Api);

  protected readonly orders = resource({
    loader: () => firstValueFrom(this.api.get<Paginated<Order>>('/orders')),
  });

  /** لون الشارة يتبع معنى الحالة لا ترتيبها. */
  protected badge(status: string): string {
    if (['delivered'].includes(status)) return 'bg-success/12 text-success';
    if (['cancelled', 'refunded'].includes(status)) return 'bg-danger/10 text-danger';
    if (['pending', 'payment_pending'].includes(status)) return 'bg-warning/12 text-warning';
    return 'bg-canvas-200 text-ink-600';
  }
}
