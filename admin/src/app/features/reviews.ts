import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Paginated, Review } from '../core/models';
import { EmptyState } from '../shared/empty-state';
import { Spinner } from '../shared/spinner';
import { StarRating } from '../shared/star-rating';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-reviews',
  imports: [Spinner, EmptyState, StarRating, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-7">
      <h1 class="font-display text-3xl text-ink-900 mb-4">المراجعات</h1>
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

    @if (reviews.isLoading()) {
      <app-spinner />
    } @else if (reviews.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد مراجعات" />
      } @else {
        <ul class="space-y-4">
          @for (r of res.data; track r.id) {
            <li class="frame rounded-card p-5">
              <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
                <div>
                  <div class="flex items-center gap-3 mb-1">
                    <app-star-rating [rating]="r.rating" />
                    <span class="text-sm text-ink-700">{{ r.authorName }}</span>
                  </div>
                  <p class="text-xs text-ink-400">
                    {{ r.painting?.title }} · <span data-numeric>{{ r.createdAt | arDate }}</span>
                  </p>
                </div>
                <span class="text-xs px-2.5 py-1" [class]="badge(r.status)">{{ r.statusLabel }}</span>
              </div>

              @if (r.title) { <p class="text-sm text-ink-800 mb-1">{{ r.title }}</p> }
              @if (r.body) { <p class="text-sm text-ink-600 leading-relaxed mb-4">{{ r.body }}</p> }

              <div class="flex gap-3">
                @if (r.status !== 'approved') {
                  <button type="button" (click)="moderate(r.id, 'approved')" [disabled]="busy()"
                          class="text-xs border border-success/40 text-success px-4 py-2 hover:bg-success/5 disabled:opacity-50 transition-colors">
                    انشرها
                  </button>
                }
                @if (r.status !== 'rejected') {
                  <button type="button" (click)="moderate(r.id, 'rejected')" [disabled]="busy()"
                          class="text-xs border border-danger/40 text-danger px-4 py-2 hover:bg-danger/5 disabled:opacity-50 transition-colors">
                    ارفضها
                  </button>
                }
                <button type="button" (click)="remove(r.id)" [disabled]="busy()"
                        class="text-xs text-ink-300 hover:text-danger px-2 py-2 transition-colors">حذف</button>
              </div>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class ManageReviewsScreen {
  private readonly api = inject(Api);

  protected readonly status = signal('pending');
  protected readonly busy = signal(false);

  protected readonly filters = [
    { value: 'pending', label: 'بانتظار المراجعة' },
    { value: 'approved', label: 'منشورة' },
    { value: 'rejected', label: 'مرفوضة' },
    { value: '', label: 'الكل' },
  ];

  protected readonly reviews = resource({
    params: () => this.status(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<Paginated<Review>>('/manage/reviews', params ? { status: params } : undefined)),
  });

  protected moderate(id: number, status: string): void {
    this.busy.set(true);
    this.api.put(`/manage/reviews/${id}/moderate`, { status }).subscribe({
      next: () => { this.busy.set(false); this.reviews.reload(); },
      error: () => this.busy.set(false),
    });
  }

  protected remove(id: number): void {
    this.busy.set(true);
    this.api.delete(`/manage/reviews/${id}`).subscribe({
      next: () => { this.busy.set(false); this.reviews.reload(); },
      error: () => this.busy.set(false),
    });
  }

  protected badge(status: string): string {
    if (status === 'approved') return 'bg-success/12 text-success';
    if (status === 'rejected') return 'bg-danger/10 text-danger';
    return 'bg-warning/12 text-warning';
  }
}
