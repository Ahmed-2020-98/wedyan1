import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-not-found',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-28 text-center">
      <p class="font-display text-6xl text-ink-200 mb-6" data-numeric>404</p>
      <h1 class="font-display text-2xl text-ink-900 mb-3">لم نجد هذه الصفحة</h1>
      <p class="text-sm text-ink-400 mb-9">ربما تغيّر الرابط أو أُزيل العمل من المعرض.</p>
      <a routerLink="/paintings" class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 transition-colors">
        تصفّح المعرض
      </a>
    </div>
  `,
})
export class NotFoundScreen {}
