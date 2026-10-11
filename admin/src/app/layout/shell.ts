import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthStore } from '../core/auth.store';

interface NavLink {
  path: string;
  label: string;
  adminOnly?: boolean;
}

@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="min-h-dvh flex">

      <!-- ==================== الشريط الجانبي ==================== -->
      <!--
        إظهار/إخفاء بـ display لا بـ transform.
        صنف lg:flex متغيّر (variant) يأتي بعد hidden في ورقة الأنماط فيتغلّب
        عليه على الشاشات الكبيرة، بينما يتبادل الصنفان على الجوال. أما
        الإزاحة بـ translate فكانت تتصادم مع lg:translate-x-0 لتساوي
        الأولوية، فيختفي الشريط على سطح المكتب.
      -->
      <aside
        class="fixed lg:static inset-y-0 start-0 z-40 w-64 shrink-0 bg-ink-900 text-canvas-200
               lg:flex flex-col"
        [class.flex]="menuOpen()"
        [class.hidden]="!menuOpen()"
      >
        <div class="px-6 py-6 border-b border-ink-700">
          <p class="font-display text-xl text-canvas-50">وديان</p>
          <p class="text-[0.7rem] text-ink-400 mt-0.5">لوحة التحكم</p>
        </div>

        <nav class="flex-1 overflow-y-auto py-4" aria-label="أقسام اللوحة">
          <ul class="space-y-0.5 px-3">
            @for (link of visibleLinks(); track link.path) {
              <li>
                <a [routerLink]="link.path" routerLinkActive="bg-ink-700 text-canvas-50"
                   [routerLinkActiveOptions]="{ exact: link.path === '/' }"
                   (click)="menuOpen.set(false)"
                   class="block px-3.5 py-2.5 text-sm text-ink-200 hover:bg-ink-800 hover:text-canvas-50 transition-colors">
                  {{ link.label }}
                </a>
              </li>
            }
          </ul>
        </nav>

        <div class="px-6 py-5 border-t border-ink-700">
          <p class="text-sm text-canvas-100 mb-0.5">{{ auth.user()?.name }}</p>
          <p class="text-[0.7rem] text-ink-400 mb-3">{{ roleLabel() }}</p>
          <button type="button" (click)="auth.logout('/login')"
                  class="text-xs text-ink-300 hover:text-danger transition-colors">
            تسجيل الخروج
          </button>
        </div>
      </aside>

      @if (menuOpen()) {
        <button type="button" (click)="menuOpen.set(false)"
                class="lg:hidden fixed inset-0 z-30 bg-ink-950/40" aria-label="إغلاق القائمة"></button>
      }

      <!-- ==================== المحتوى ==================== -->
      <div class="flex-1 min-w-0">
        <header class="lg:hidden sticky top-0 z-20 bg-canvas-100 border-b border-ink-100 px-5 py-3.5 flex items-center justify-between">
          <span class="font-display text-lg text-ink-900">وديان</span>
          <button type="button" (click)="menuOpen.set(true)" class="p-2 text-ink-600" aria-label="القائمة">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
            </svg>
          </button>
        </header>

        <main class="px-5 sm:px-8 py-8 max-w-7xl"><router-outlet /></main>
      </div>
    </div>
  `,
})
export class Shell {
  protected readonly auth = inject(AuthStore);
  protected readonly menuOpen = signal(false);

  private readonly links: NavLink[] = [
    { path: '/', label: 'نظرة عامة' },
    { path: '/paintings', label: 'اللوحات' },
    { path: '/reservations', label: 'الحجوزات' },
    { path: '/orders', label: 'الطلبات' },
    { path: '/categories', label: 'التصنيفات' },
    { path: '/reviews', label: 'المراجعات' },
    { path: '/users', label: 'المستخدمون', adminOnly: true },
    { path: '/coupons', label: 'الكوبونات', adminOnly: true },
    { path: '/payments', label: 'المدفوعات', adminOnly: true },
    { path: '/shipping', label: 'الشحن', adminOnly: true },
    { path: '/settings', label: 'الإعدادات', adminOnly: true },
  ];

  /** الفنان لا يرى روابط لا يستطيع فتحها أصلاً. */
  protected readonly visibleLinks = computed(() =>
    this.links.filter((l) => !l.adminOnly || this.auth.hasRole('admin')),
  );

  protected readonly roleLabel = computed(() =>
    this.auth.hasRole('admin') ? 'مدير المنصة' : 'الفنانة',
  );
}
