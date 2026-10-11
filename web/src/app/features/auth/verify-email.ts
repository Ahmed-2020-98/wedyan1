import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AuthStore } from '../../core/auth.store';
import { Api } from '../../core/api';

/** صفحة الهبوط بعد الضغط على رابط التأكيد في البريد. */
@Component({
  selector: 'app-verify-email',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-24 text-center">
      <h1 class="font-display text-3xl text-ink-900 mb-4">{{ message().title }}</h1>
      <p class="text-ink-500 leading-relaxed mb-9">{{ message().body }}</p>

      <div class="flex flex-wrap gap-3 justify-center">
        @if (status() === 'invalid' && auth.isAuthenticated()) {
          <button type="button" (click)="resend()"
                  class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 transition-colors">
            أرسل رابطاً جديداً
          </button>
        }
        <a routerLink="/account" class="border border-ink-300 px-6 py-3 text-sm hover:border-ink-900 transition-colors">
          حسابي
        </a>
        <a routerLink="/paintings" class="border border-ink-300 px-6 py-3 text-sm hover:border-ink-900 transition-colors">
          تصفّح المعرض
        </a>
      </div>
    </div>
  `,
})
export class VerifyEmailScreen {
  protected readonly auth = inject(AuthStore);
  private readonly api = inject(Api);

  readonly status = input<string>('');

  protected readonly message = computed(() => {
    switch (this.status()) {
      case 'success':
        return { title: 'تم تأكيد بريدك', body: 'شكراً لك — حسابك مفعّل بالكامل الآن.' };
      case 'already':
        return { title: 'بريدك مؤكَّد', body: 'سبق تأكيد هذا البريد. لا حاجة لأي إجراء.' };
      default:
        return {
          title: 'رابط غير صالح',
          body: 'انتهت صلاحية الرابط أو استُخدم من قبل. اطلب رابطاً جديداً.',
        };
    }
  });

  protected resend(): void {
    this.api.post('/auth/resend-verification').subscribe();
  }
}
