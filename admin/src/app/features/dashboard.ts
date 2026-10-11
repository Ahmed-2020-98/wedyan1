import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Money, Resource } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

interface DashboardData {
  paintings: { total: number; available: number; sold: number; reserved: number; inProduction: number };
  orders: { total: number; awaitingPayment: number; toPrepare: number; shipped: number; delivered: number };
  reservations: { total: number; pending: number; inProduction: number; awaitingPayment: number };
  revenue: { total: Money; thisMonth: Money };
  customers: { total: number; newThisMonth: number };
  charts: {
    monthlyRevenue: { month: string; revenue: number; orders: number }[];
    bestSellers: { title: string; sold: number }[];
  };
  recentOrders: {
    orderNumber: string; customer: string; total: Money; statusLabel: string; createdAt: string;
  }[];
}

@Component({
  selector: 'app-dashboard',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './dashboard.html',
})
export class DashboardScreen {
  private readonly api = inject(Api);

  protected readonly data = resource({
    loader: () => firstValueFrom(this.api.get<Resource<DashboardData>>('/manage/dashboard')),
  });

  /**
   * أطول عمود في الرسم — تُنسب إليه بقية الأعمدة.
   * الحدّ الأدنى ١ يتفادى القسمة على صفر حين لا تكون هناك مبيعات بعد.
   */
  protected maxRevenue(rows: { revenue: number }[]): number {
    return Math.max(1, ...rows.map((r) => r.revenue));
  }

  protected maxSold(rows: { sold: number }[]): number {
    return Math.max(1, ...rows.map((r) => r.sold));
  }

  protected monthLabel(month: string): string {
    const [, m] = month.split('-');
    return ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'][Number(m)] ?? month;
  }
}
