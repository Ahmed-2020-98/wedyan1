import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { Api } from '../core/api';

@Component({
  selector: 'app-footer',
  imports: [RouterLink, FormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <footer class="mt-28 border-t border-ink-100 bg-canvas-200/50">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 py-16">
        <div class="grid gap-12 md:grid-cols-4">

          <div class="md:col-span-2 max-w-sm">
            <p class="font-display text-2xl text-ink-900 mb-3">وديان</p>
            <p class="text-sm text-ink-500 leading-relaxed mb-6">
              معرض إلكتروني للوحات فنية أصلية. كل عمل قطعة واحدة لا تتكرّر،
              موقّعة من الفنانة ومشحونة مع شهادة أصالة.
            </p>

            <form (ngSubmit)="subscribe()" class="flex gap-2 max-w-xs">
              <label for="newsletter" class="sr-only">بريدك الإلكتروني</label>
              <input
                id="newsletter"
                name="email"
                [(ngModel)]="email"
                type="email"
                required
                placeholder="بريدك الإلكتروني"
                class="flex-1 min-w-0 bg-canvas-50 border border-ink-200 px-3 py-2.5 text-sm outline-none
                       focus:border-gilt-500 transition-colors placeholder:text-ink-300"
              />
              <button type="submit" class="bg-ink-900 text-canvas-50 px-4 py-2.5 text-sm hover:bg-ink-800 transition-colors">
                اشترك
              </button>
            </form>
            @if (subscribed()) {
              <p class="text-xs text-success mt-2">شكراً لك — سنوافيك بكل جديد.</p>
            }
          </div>

          <div>
            <p class="text-xs text-ink-400 mb-4">المعرض</p>
            <ul class="space-y-2.5 text-sm">
              <li><a routerLink="/paintings" class="text-ink-600 hover:text-ink-900 transition-colors">كل اللوحات</a></li>
              <li><a routerLink="/paintings" [queryParams]="{ type: 'reservable' }" class="text-ink-600 hover:text-ink-900 transition-colors">اطلب لوحتك</a></li>
              <li><a routerLink="/about" class="text-ink-600 hover:text-ink-900 transition-colors">عن الفنانة</a></li>
            </ul>
          </div>

          <div>
            <p class="text-xs text-ink-400 mb-4">حسابي</p>
            <ul class="space-y-2.5 text-sm">
              <li><a routerLink="/account/orders" class="text-ink-600 hover:text-ink-900 transition-colors">طلباتي</a></li>
              <li><a routerLink="/account/reservations" class="text-ink-600 hover:text-ink-900 transition-colors">حجوزاتي</a></li>
              <li><a routerLink="/account/wishlist" class="text-ink-600 hover:text-ink-900 transition-colors">المفضّلة</a></li>
            </ul>
          </div>
        </div>

        <div class="mt-14 pt-6 border-t border-ink-100 flex flex-wrap items-center justify-between gap-3 text-xs text-ink-400">
          <p data-numeric>© {{ year }} وديان — جميع الحقوق محفوظة</p>
          <p>صُنع بعناية في السعودية</p>
        </div>
      </div>
    </footer>
  `,
})
export class Footer {
  private readonly api = inject(Api);

  protected readonly year = new Date().getFullYear();
  protected email = '';
  protected readonly subscribed = signal(false);

  protected subscribe(): void {
    if (!this.email.trim()) return;

    this.api.post('/newsletter', { email: this.email }).subscribe({
      next: () => { this.subscribed.set(true); this.email = ''; },
      error: () => { this.subscribed.set(true); this.email = ''; },
    });
  }
}
