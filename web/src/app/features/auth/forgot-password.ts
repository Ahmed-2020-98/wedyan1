import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { Api } from '../../core/api';
import { Seo } from '../../core/seo.service';

@Component({
  selector: 'app-forgot-password',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-20">
      <h1 class="font-display text-3xl text-ink-900 mb-2">نسيت كلمة المرور</h1>
      <p class="text-sm text-ink-400 mb-9">أدخل بريدك وسنرسل لك رابط إعادة التعيين.</p>

      @if (sent()) {
        <div class="bg-success/8 border-s-2 border-success p-5 text-sm text-ink-700 leading-relaxed">
          إن كان البريد مسجّلاً لدينا فستصلك رسالة بخطوات إعادة التعيين خلال دقائق.
          تفقّد مجلد الرسائل غير المرغوبة أيضاً.
        </div>
        <a routerLink="/login" class="block mt-7 text-sm text-gilt-700 hover:underline">العودة لتسجيل الدخول</a>
      } @else {
        <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5">
          <label class="block">
            <span class="block text-sm text-ink-600 mb-2">البريد الإلكتروني</span>
            <input formControlName="email" type="email" autocomplete="email" dir="ltr"
                   class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
          </label>

          <button type="submit" [disabled]="busy() || form.invalid"
                  class="w-full bg-ink-900 text-canvas-50 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
            @if (busy()) { …جارٍ الإرسال } @else { أرسل الرابط }
          </button>
        </form>

        <a routerLink="/login" class="block mt-7 text-sm text-ink-400 hover:text-ink-700 transition-colors">
          العودة لتسجيل الدخول
        </a>
      }
    </div>
  `,
})
export class ForgotPasswordScreen {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(Api);

  protected readonly busy = signal(false);
  protected readonly sent = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  constructor() {
    inject(Seo).apply({ title: 'استعادة كلمة المرور', path: '/forgot-password' });
  }

  protected submit(): void {
    if (this.form.invalid) return;

    this.busy.set(true);

    // الرد واحد سواء وُجد البريد أم لا، ولذلك نعرض النجاح في الحالتين.
    this.api.post('/auth/forgot-password', this.form.getRawValue()).subscribe({
      next: () => { this.busy.set(false); this.sent.set(true); },
      error: () => { this.busy.set(false); this.sent.set(true); },
    });
  }
}
