import { ChangeDetectionStrategy, Component, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { AuthStore } from '../core/auth.store';
import { CartStore } from '../core/cart.store';
import { BottomNav } from './bottom-nav';
import { Footer } from './footer';
import { Header } from './header';

/** الهيكل العام: ترويسة، محتوى، تذييل، وشريط سفلي على الجوال. */
@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, Header, Footer, BottomNav],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:start-3 focus:z-50
                           focus:bg-ink-900 focus:text-canvas-50 focus:px-4 focus:py-2">
      تخطَّ إلى المحتوى
    </a>

    <app-header />

    <!--
      الحشو السفلي يمنع الشريط السفلي من تغطية آخر سطر في الصفحة.
      يختفي مع الشريط نفسه عند md.
    -->
    <main id="main" class="min-h-[60dvh] pb-20 md:pb-0"><router-outlet /></main>

    <app-footer />
    <app-bottom-nav />
  `,
})
export class Shell {
  private readonly auth = inject(AuthStore);
  private readonly cart = inject(CartStore);

  constructor() {
    // السلة تُحمَّل حين تصبح الجلسة جاهزة، لا عند الإقلاع: قبل التحقق من
    // الرمز لا نعرف بعد إن كان المستخدم مسجّلاً.
    effect(() => {
      if (this.auth.ready() && this.auth.isAuthenticated()) {
        this.cart.load();
      }
    });
  }
}
