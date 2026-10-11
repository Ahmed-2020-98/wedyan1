import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { AccountOverview, Resource } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-account-overview',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (overview.isLoading()) {
      <app-spinner />
    } @else if (overview.value(); as res) {
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-10">
        @for (stat of stats(res.data); track stat.label) {
          <a [routerLink]="stat.link" class="frame rounded-card p-6 block">
            <p class="text-xs text-ink-400 mb-2">{{ stat.label }}</p>
            <p class="font-display text-3xl text-ink-900" data-numeric>{{ stat.value }}</p>
          </a>
        }
      </div>

      <div class="frame rounded-card p-7">
        <h2 class="font-display text-lg text-ink-900 mb-5">إجمالي ما اقتنيته</h2>
        <p class="font-display text-4xl text-gilt-600 mb-2" data-numeric>{{ res.data.totalSpent | money }}</p>
        <p class="text-sm text-ink-400">شكراً لدعمك الفن الأصيل.</p>
      </div>
    }
  `,
})
export class AccountOverviewScreen {
  private readonly api = inject(Api);

  protected readonly overview = resource({
    loader: () => firstValueFrom(this.api.get<Resource<AccountOverview>>('/account/overview')),
  });

  protected stats(d: AccountOverview) {
    return [
      { label: 'إجمالي الطلبات', value: d.orders.total, link: '/account/orders' },
      { label: 'طلبات جارية', value: d.orders.active, link: '/account/orders' },
      { label: 'طلبات مكتملة', value: d.orders.completed, link: '/account/orders' },
      { label: 'حجوزات نشطة', value: d.reservations.active, link: '/account/reservations' },
      { label: 'قيد التنفيذ', value: d.reservations.inProduction, link: '/account/reservations' },
      { label: 'المفضّلة', value: d.wishlistCount, link: '/account/wishlist' },
    ];
  }
}
