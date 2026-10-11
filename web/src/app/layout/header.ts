import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';

import { AuthStore } from '../core/auth.store';
import { CartStore } from '../core/cart.store';

/**
 * الترويسة.
 *
 * على الجوال تحمل الاسم والبحث فقط — بقية التنقّل في الشريط السفلي حيث
 * يصله الإبهام. تكديس خمس أيقونات في أعلى الشاشة يجعلها جميعاً بعيدة
 * المنال ويزاحم مساحة العرض.
 */
@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, FormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="sticky top-0 z-40 bg-canvas-100/90 backdrop-blur-md border-b border-ink-100">
      <div class="mx-auto max-w-7xl px-4 sm:px-8">
        <div class="flex items-center justify-between gap-4 h-14 sm:h-16">

          <a routerLink="/" class="font-display text-xl sm:text-2xl text-ink-900 shrink-0"
             aria-label="وديان — الصفحة الرئيسية">
            وديان
          </a>

          <!-- تنقّل سطح المكتب -->
          <nav class="hidden md:flex items-center gap-7 text-sm" aria-label="التنقل الرئيسي">
            <a routerLink="/paintings" routerLinkActive="text-ink-900"
               class="text-ink-500 hover:text-ink-900 transition-colors">المعرض</a>
            <a routerLink="/paintings" [queryParams]="{ type: 'reservable' }"
               class="text-ink-500 hover:text-ink-900 transition-colors">اطلب لوحتك</a>
            <a routerLink="/about" routerLinkActive="text-ink-900"
               class="text-ink-500 hover:text-ink-900 transition-colors">عن الفنانة</a>
          </nav>

          <div class="flex items-center gap-1">
            <!-- بحث سطح المكتب -->
            <form (ngSubmit)="search()" class="hidden lg:block">
              <label for="site-search" class="sr-only">ابحث في المعرض</label>
              <input id="site-search" name="q" [(ngModel)]="term" type="search"
                     placeholder="ابحث عن لوحة…"
                     class="w-48 bg-canvas-200/70 border border-transparent focus:border-ink-200
                            focus:bg-canvas-50 px-3.5 py-2 text-sm outline-none transition-colors
                            placeholder:text-ink-300" />
            </form>

            <!-- بحث الجوال -->
            <button type="button" (click)="searchOpen.set(!searchOpen())"
                    class="lg:hidden p-2.5 text-ink-500" [attr.aria-expanded]="searchOpen()"
                    aria-label="بحث">
              <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5" stroke-linecap="round"/>
              </svg>
            </button>

            @if (!auth.isAuthenticated()) {
              <a routerLink="/login" class="hidden md:inline-flex text-sm text-ink-500 hover:text-ink-900 px-3 py-2 transition-colors">
                دخول
              </a>
            }

            <!-- السلة: ظاهرة دائماً، فهي الإجراء الأهم -->
            <a routerLink="/cart" class="relative p-2.5 text-ink-600 hover:text-ink-900 transition-colors"
               aria-label="السلة">
              <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6z" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 6h18M16 10a4 4 0 01-8 0" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              @if (cart.itemCount() > 0) {
                <span class="absolute top-1 left-1 min-w-4 h-4 px-1 grid place-items-center
                             bg-gilt-600 text-canvas-50 text-[0.65rem] rounded-full" data-numeric>
                  {{ cart.itemCount() }}
                </span>
              }
            </a>

            <a routerLink="/account" class="hidden md:inline-flex p-2.5 text-ink-600 hover:text-ink-900 transition-colors"
               aria-label="حسابي">
              <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-linecap="round"/>
                <circle cx="12" cy="7" r="4"/>
              </svg>
            </a>
          </div>
        </div>

        @if (searchOpen()) {
          <form (ngSubmit)="search()" class="lg:hidden pb-3">
            <label for="mobile-search" class="sr-only">ابحث في المعرض</label>
            <input id="mobile-search" name="q" [(ngModel)]="term" type="search" autofocus
                   placeholder="ابحث عن لوحة…" class="field text-sm" />
          </form>
        }
      </div>
    </header>
  `,
})
export class Header {
  protected readonly auth = inject(AuthStore);
  protected readonly cart = inject(CartStore);
  private readonly router = inject(Router);

  protected term = '';
  protected readonly searchOpen = signal(false);

  protected search(): void {
    const q = this.term.trim();
    if (!q) return;

    this.searchOpen.set(false);
    this.router.navigate(['/paintings'], { queryParams: { search: q } });
  }
}
