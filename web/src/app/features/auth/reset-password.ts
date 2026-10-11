import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { Api } from '../../core/api';
import { Seo } from '../../core/seo.service';

@Component({
  selector: 'app-reset-password',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-20">
      <h1 class="font-display text-3xl text-ink-900 mb-2">كلمة مرور جديدة</h1>
      <p class="text-sm text-ink-400 mb-9" dir="ltr">{{ email() }}</p>

      <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">كلمة المرور الجديدة</span>
          <input formControlName="password" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
          <span class="text-xs text-ink-400 mt-1 block">٨ أحرف على الأقل، وتشمل حرفاً ورقماً.</span>
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">تأكيد كلمة المرور</span>
          <input formControlName="password_confirmation" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
        </label>

        @if (error()) { <p class="text-sm text-danger" role="alert">{{ error() }}</p> }

        <button type="submit" [disabled]="busy() || form.invalid"
                class="w-full bg-ink-900 text-canvas-50 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          @if (busy()) { …جارٍ الحفظ } @else { حفظ كلمة المرور }
        </button>
      </form>

      <a routerLink="/login" class="block mt-7 text-sm text-ink-400 hover:text-ink-700 transition-colors">
        العودة لتسجيل الدخول
      </a>
    </div>
  `,
})
export class ResetPasswordScreen {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(Api);
  private readonly router = inject(Router);

  readonly token = input<string>('');
  readonly email = input<string>('');

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  });

  constructor() {
    inject(Seo).apply({ title: 'تعيين كلمة مرور جديدة', path: '/reset-password' });
  }

  protected submit(): void {
    if (this.form.invalid) return;

    this.busy.set(true);
    this.error.set(null);

    this.api
      .post('/auth/reset-password', {
        token: this.token(),
        email: this.email(),
        ...this.form.getRawValue(),
      })
      .subscribe({
        next: () => this.router.navigate(['/login'], { queryParams: { reset: 'done' } }),
        error: (err) => {
          this.busy.set(false);
          this.error.set(
            err?.error?.errors?.email?.[0] ?? err?.error?.message ?? 'تعذّر تعيين كلمة المرور.',
          );
        },
      });
  }
}
