import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { AuthStore } from '../core/auth.store';

@Component({
  selector: 'app-forbidden',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="min-h-dvh grid place-items-center px-5 text-center">
      <div>
        <p class="font-display text-5xl text-ink-200 mb-5" data-numeric>403</p>
        <h1 class="font-display text-2xl text-ink-900 mb-3">لا صلاحية لك</h1>
        <p class="text-sm text-ink-400 mb-8">هذا القسم مخصّص لإدارة المنصة.</p>
        <button type="button" (click)="auth.logout('/login')"
                class="border border-ink-300 px-6 py-2.5 text-sm hover:border-ink-900 transition-colors">
          تسجيل الخروج
        </button>
      </div>
    </div>
  `,
})
export class ForbiddenScreen {
  protected readonly auth = inject(AuthStore);
}
