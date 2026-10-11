import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

import { AuthStore } from '../core/auth.store';
import { CartStore } from '../core/cart.store';

/**
 * شريط التنقّل السفلي — للجوال وحده.
 *
 * الوجهات الأربع الأكثر استخداماً في متناول الإبهام مباشرةً، بدل قائمة
 * هامبرغر تُخفي التنقّل خلف نقرة وتضع الأيقونات في أعلى الشاشة حيث يصعب
 * بلوغها بيد واحدة.
 *
 * يختفي تماماً من عرض md فصاعداً حيث تتولّى الترويسة التنقّل.
 */
@Component({
  selector: 'app-bottom-nav',
  imports: [RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <nav
      class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-canvas-50/95 backdrop-blur-md
             border-t border-ink-100"
      style="padding-bottom: env(safe-area-inset-bottom)"
      aria-label="التنقل السريع"
    >
      <ul class="grid grid-cols-4">
        <li>
          <a routerLink="/" routerLinkActive="text-ink-900" [routerLinkActiveOptions]="{ exact: true }"
             class="flex flex-col items-center gap-1 py-2.5 text-ink-400 transition-colors">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M3 10l9-7 9 7v10a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="text-[0.7rem]">الرئيسية</span>
          </a>
        </li>

        <li>
          <a routerLink="/paintings" routerLinkActive="text-ink-900"
             class="flex flex-col items-center gap-1 py-2.5 text-ink-400 transition-colors">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
              <rect x="3" y="3" width="18" height="18" rx="1"/>
              <path d="M3 15l5-5 4 4 3-3 6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="text-[0.7rem]">المعرض</span>
          </a>
        </li>

        <li>
          <a routerLink="/cart" routerLinkActive="text-ink-900"
             class="relative flex flex-col items-center gap-1 py-2.5 text-ink-400 transition-colors">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4H6z" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M3 6h18M16 10a4 4 0 01-8 0" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            @if (cart.itemCount() > 0) {
              <span class="absolute top-1.5 left-[calc(50%-1.15rem)] min-w-4 h-4 px-1 grid place-items-center
                           bg-gilt-600 text-canvas-50 text-[0.65rem] rounded-full" data-numeric>
                {{ cart.itemCount() }}
              </span>
            }
            <span class="text-[0.7rem]">السلة</span>
          </a>
        </li>

        <li>
          <a [routerLink]="auth.isAuthenticated() ? '/account' : '/login'" routerLinkActive="text-ink-900"
             class="flex flex-col items-center gap-1 py-2.5 text-ink-400 transition-colors">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke-linecap="round"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="text-[0.7rem]">{{ auth.isAuthenticated() ? 'حسابي' : 'دخول' }}</span>
          </a>
        </li>
      </ul>
    </nav>
  `,
})
export class BottomNav {
  protected readonly auth = inject(AuthStore);
  protected readonly cart = inject(CartStore);
}
