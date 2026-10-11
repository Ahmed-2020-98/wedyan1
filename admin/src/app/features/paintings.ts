import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Paginated, Painting } from '../core/models';
import { EmptyState } from '../shared/empty-state';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-paintings',
  imports: [RouterLink, FormsModule, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="flex flex-wrap items-start justify-between gap-4 mb-7">
      <div>
        <h1 class="font-display text-3xl text-ink-900 mb-4">اللوحات</h1>
        <div class="flex flex-wrap gap-3">
          <input [(ngModel)]="term" (ngModelChange)="search.set($event)" type="search" placeholder="العنوان أو الرمز…"
                 class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500 w-56" />
          <select [ngModel]="type()" (ngModelChange)="type.set($event)"
                  class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500">
            <option value="">كل الأنواع</option>
            <option value="ready_made">جاهزة</option>
            <option value="reservable">قابلة للحجز</option>
            <option value="custom_order">طلب خاص</option>
          </select>
        </div>
      </div>

      <a routerLink="/paintings/new"
         class="bg-ink-900 text-canvas-50 px-5 py-2.5 text-sm hover:bg-ink-800 transition-colors shrink-0">
        + لوحة جديدة
      </a>
    </header>

    @if (paintings.isLoading()) {
      <app-spinner />
    } @else if (paintings.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد لوحات" note="أضف أول عمل ليظهر في المعرض." />
      } @else {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          @for (p of res.data; track p.id) {
            <a [routerLink]="['/paintings', p.id]" class="frame rounded-card p-4 flex gap-4">
              @if (p.mainImage; as img) {
                <img [src]="img.thumb" [alt]="p.title" loading="lazy" class="w-20 h-auto bg-canvas-200 shrink-0 self-start" />
              } @else {
                <div class="w-20 h-24 bg-canvas-200 shrink-0 grid place-items-center text-[0.65rem] text-ink-300">بلا صورة</div>
              }

              <div class="flex-1 min-w-0">
                <p class="text-sm text-ink-900 mb-1 truncate">{{ p.title }}</p>
                <p class="text-xs text-ink-400 mb-2" data-numeric>{{ p.sku }}</p>
                <p class="text-sm text-ink-700 mb-2" data-numeric>{{ p.effectivePrice | money }}</p>
                <div class="flex flex-wrap gap-1.5">
                  <span class="text-[0.65rem] px-1.5 py-0.5 bg-canvas-200 text-ink-600">{{ p.typeLabel }}</span>
                  <span class="text-[0.65rem] px-1.5 py-0.5"
                        [class]="p.availability === 'available' ? 'bg-success/12 text-success' : 'bg-ink-100 text-ink-500'">
                    {{ p.availabilityLabel }}
                  </span>
                </div>
              </div>
            </a>
          }
        </div>
      }
    }
  `,
})
export class ManagePaintingsScreen {
  private readonly api = inject(Api);

  protected term = '';
  protected readonly search = signal('');
  protected readonly type = signal('');

  protected readonly paintings = resource({
    params: () => ({ search: this.search(), type: this.type() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.api.get<Paginated<Painting>>('/manage/paintings', {
          search: params.search || undefined,
          type: params.type || undefined,
        }),
      ),
  });
}
