import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthStore } from '../../core/auth.store';

@Component({
  selector: 'app-account-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-7xl px-5 sm:px-8 py-12">
      <header class="mb-9">
        <p class="eyebrow mb-2">حسابي</p>
        <h1 class="font-display text-3xl text-ink-900">{{ auth.user()?.name }}</h1>
      </header>

      @if (auth.user(); as u) {
        @if (!u.emailVerified) {
          <div class="bg-warning/10 border-s-2 border-warning px-5 py-4 mb-8 text-sm text-ink-700">
            لم تؤكّد بريدك بعد. تفقّد رسالة التأكيد في صندوق الوارد.
          </div>
        }
      }

      <div class="grid lg:grid-cols-[14rem_1fr] gap-10 items-start">
        <nav class="lg:sticky lg:top-24" aria-label="أقسام الحساب">
          <ul class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible pb-2 lg:pb-0">
            @for (link of links; track link.path) {
              <li class="shrink-0">
                <a [routerLink]="link.path" routerLinkActive="bg-canvas-200 text-ink-900"
                   [routerLinkActiveOptions]="{ exact: link.exact }"
                   class="block whitespace-nowrap px-4 py-2.5 text-sm text-ink-500 hover:text-ink-900 transition-colors">
                  {{ link.label }}
                </a>
              </li>
            }
            <li class="shrink-0">
              <button type="button" (click)="auth.logout()"
                      class="block w-full text-start whitespace-nowrap px-4 py-2.5 text-sm text-danger hover:bg-danger/5 transition-colors">
                تسجيل الخروج
              </button>
            </li>
          </ul>
        </nav>

        <div><router-outlet /></div>
      </div>
    </div>
  `,
})
export class AccountShell {
  protected readonly auth = inject(AuthStore);

  protected readonly links = [
    { path: '/account', label: 'نظرة عامة', exact: true },
    { path: '/account/orders', label: 'طلباتي', exact: false },
    { path: '/account/reservations', label: 'حجوزاتي', exact: false },
    { path: '/account/wishlist', label: 'المفضّلة', exact: false },
    { path: '/account/addresses', label: 'العناوين', exact: false },
    { path: '/account/payments', label: 'المدفوعات', exact: false },
    { path: '/account/notifications', label: 'الإشعارات', exact: false },
    { path: '/account/profile', label: 'بياناتي', exact: false },
  ];
}
