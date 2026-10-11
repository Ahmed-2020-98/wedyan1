import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import { CartStore } from '../../core/cart.store';
import type { Painting } from '../../core/models';
import { EmptyState } from '../../shared/empty-state';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-account-wishlist',
  imports: [RouterLink, Spinner, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h2 class="font-display text-2xl text-ink-900 mb-7">المفضّلة</h2>

    @if (items.isLoading()) {
      <app-spinner />
    } @else if (items.value(); as res) {
      @if (res.data.length === 0) {
        <app-empty-state title="لا توجد أعمال محفوظة" note="اضغط القلب على أي لوحة لحفظها هنا.">
          <a routerLink="/paintings" class="inline-block mt-6 bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 transition-colors">
            تصفّح المعرض
          </a>
        </app-empty-state>
      } @else {
        <ul class="space-y-4">
          @for (p of res.data; track p.id) {
            <li class="frame rounded-card p-4 flex gap-5">
              <a [routerLink]="['/paintings', p.slug]" class="w-24 shrink-0 bg-canvas-200">
                @if (p.mainImage; as img) {
                  <img [src]="img.thumb" [alt]="p.title" loading="lazy" class="w-full h-auto" />
                }
              </a>

              <div class="flex-1 min-w-0 flex flex-col justify-between py-1">
                <div>
                  <a [routerLink]="['/paintings', p.slug]" class="font-display text-lg text-ink-900 hover:text-gilt-700 transition-colors">
                    {{ p.title }}
                  </a>
                  <p class="text-sm text-ink-500 mt-1" data-numeric>{{ p.effectivePrice | money }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-3 mt-3">
                  @if (p.isPurchasable) {
                    <button type="button" (click)="moveToCart(p.id)" [disabled]="busy()"
                            class="text-xs bg-ink-900 text-canvas-50 px-4 py-2 hover:bg-ink-800 disabled:opacity-50 transition-colors">
                      انقل إلى السلة
                    </button>
                  } @else {
                    <span class="text-xs text-ink-400">{{ p.availabilityLabel }}</span>
                  }
                  <button type="button" (click)="remove(p.id)" [disabled]="busy()"
                          class="text-xs text-ink-300 hover:text-danger transition-colors">إزالة</button>
                </div>
              </div>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class AccountWishlistScreen {
  private readonly api = inject(Api);
  private readonly cart = inject(CartStore);

  protected readonly busy = signal(false);

  protected readonly items = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Painting[] }>('/wishlist')),
  });

  protected remove(id: number): void {
    this.busy.set(true);
    this.api.delete(`/wishlist/${id}`).subscribe({
      next: () => { this.busy.set(false); this.items.reload(); },
      error: () => this.busy.set(false),
    });
  }

  protected moveToCart(id: number): void {
    this.busy.set(true);
    this.api.post(`/wishlist/${id}/move-to-cart`).subscribe({
      next: () => { this.busy.set(false); this.items.reload(); this.cart.load(); },
      error: () => this.busy.set(false),
    });
  }
}
