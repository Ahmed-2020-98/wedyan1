import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Paginated, Reservation } from '../../core/models';
import { EmptyState } from '../../shared/empty-state';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-account-reservations',
  imports: [RouterLink, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h2 class="font-display text-2xl text-ink-900 mb-7">حجوزاتي</h2>

    @if (reservations.isLoading()) {
      <app-spinner />
    } @else if (reservations.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد حجوزات" note="اطلب لوحة تُرسم خصيصاً لك، وتابع مراحل تنفيذها هنا.">
          <a routerLink="/paintings" [queryParams]="{ type: 'reservable' }"
             class="inline-block mt-6 bg-gilt-600 text-canvas-50 px-6 py-3 text-sm hover:bg-gilt-700 transition-colors">
            اطلب لوحتك
          </a>
        </app-empty-state>
      } @else {
        <ul class="space-y-4">
          @for (r of res.data; track r.reservationNumber) {
            <li>
              <a [routerLink]="['/account/reservations', r.reservationNumber]" class="frame rounded-card p-5 block">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-3">
                  <div>
                    <p class="text-sm text-ink-800 mb-1" data-numeric>{{ r.reservationNumber }}</p>
                    <p class="text-xs text-ink-400" data-numeric>{{ r.timeline.requestedAt | arDate }}</p>
                  </div>

                  <div class="flex items-center gap-4">
                    @if (r.awaitsPayment) {
                      <span class="text-xs px-2.5 py-1 bg-gilt-500/15 text-gilt-700">مطلوب دفع</span>
                    }
                    <span class="text-xs px-2.5 py-1" [class]="badge(r.status)">{{ r.statusLabel }}</span>
                    @if (r.price) {
                      <span class="text-sm text-ink-800" data-numeric>{{ r.price | money }}</span>
                    }
                  </div>
                </div>

                @if (r.status === 'in_production') {
                  <div class="h-1.5 bg-ink-100 overflow-hidden">
                    <div class="h-full bg-gilt-500 transition-all duration-700" [style.width.%]="r.progressPercentage"></div>
                  </div>
                  <p class="text-xs text-ink-400 mt-2" data-numeric>اكتمل {{ r.progressPercentage }}٪</p>
                }
              </a>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class AccountReservationsScreen {
  private readonly api = inject(Api);

  protected readonly reservations = resource({
    loader: () => firstValueFrom(this.api.get<Paginated<Reservation>>('/reservations')),
  });

  protected badge(status: string): string {
    if (['ready_for_delivery', 'completed'].includes(status)) return 'bg-success/12 text-success';
    if (['rejected', 'cancelled'].includes(status)) return 'bg-danger/10 text-danger';
    if (['in_production'].includes(status)) return 'bg-gilt-500/15 text-gilt-700';
    return 'bg-canvas-200 text-ink-600';
  }
}
