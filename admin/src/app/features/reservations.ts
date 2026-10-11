import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Paginated, Reservation } from '../core/models';
import { EmptyState } from '../shared/empty-state';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-reservations',
  imports: [RouterLink, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-7">
      <h1 class="font-display text-3xl text-ink-900 mb-4">الحجوزات</h1>

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

    @if (reservations.isLoading()) {
      <app-spinner />
    } @else if (reservations.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد حجوزات" note="طلبات اللوحات المخصّصة ستظهر هنا." />
      } @else {
        <ul class="space-y-3">
          @for (r of res.data; track r.reservationNumber) {
            <li>
              <a [routerLink]="['/reservations', r.reservationNumber]"
                 class="frame rounded-card p-5 flex flex-wrap items-center justify-between gap-4 block">
                <div class="min-w-0">
                  <p class="text-sm text-ink-800 mb-1" data-numeric>{{ r.reservationNumber }}</p>
                  <p class="text-xs text-ink-400">
                    {{ r.customer?.name }} · <span data-numeric>{{ r.timeline.requestedAt | arDate }}</span>
                  </p>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                  @if (r.status === 'in_production') {
                    <span class="text-xs text-gilt-700" data-numeric>{{ r.progressPercentage }}٪</span>
                  }
                  @if (r.price) { <span class="text-sm text-ink-800" data-numeric>{{ r.price | money }}</span> }
                  <span class="text-xs px-2.5 py-1" [class]="badge(r.status)">{{ r.statusLabel }}</span>
                </div>
              </a>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class ManageReservationsScreen {
  private readonly api = inject(Api);

  protected readonly status = signal<string>('');

  protected readonly filters = [
    { value: '', label: 'الكل' },
    { value: 'pending', label: 'جديدة' },
    { value: 'accepted', label: 'مقبولة' },
    { value: 'deposit_pending', label: 'بانتظار العربون' },
    { value: 'in_production', label: 'قيد التنفيذ' },
    { value: 'completed', label: 'مكتملة' },
    { value: 'ready_for_delivery', label: 'جاهزة للتسليم' },
  ];

  protected readonly reservations = resource({
    params: () => this.status(),
    loader: ({ params }) =>
      firstValueFrom(
        this.api.get<Paginated<Reservation>>('/manage/reservations', params ? { status: params } : undefined),
      ),
  });

  protected badge(status: string): string {
    if (['ready_for_delivery', 'completed'].includes(status)) return 'bg-success/12 text-success';
    if (['rejected', 'cancelled'].includes(status)) return 'bg-danger/10 text-danger';
    if (status === 'in_production') return 'bg-gilt-500/15 text-gilt-700';
    if (['pending', 'under_review'].includes(status)) return 'bg-warning/12 text-warning';
    return 'bg-canvas-200 text-ink-600';
  }
}
