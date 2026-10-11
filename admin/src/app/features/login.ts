import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { AuthStore } from '../core/auth.store';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="min-h-dvh grid place-items-center px-5">
      <div class="w-full max-w-sm">
        <div class="text-center mb-10">
          <p class="font-display text-3xl text-ink-900 mb-1">وديان</p>
          <p class="text-xs text-gilt-600">لوحة التحكم</p>
        </div>

        <form [formGroup]="form" (ngSubmit)="submit()" class="frame rounded-card p-7 space-y-5">
          <label class="block">
            <span class="block text-sm text-ink-600 mb-2">البريد الإلكتروني</span>
            <input formControlName="email" type="email" autocomplete="email" dir="ltr"
                   class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
          </label>

          <label class="block">
            <span class="block text-sm text-ink-600 mb-2">كلمة المرور</span>
            <input formControlName="password" type="password" autocomplete="current-password"
                   class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
          </label>

          @if (error()) { <p class="text-sm text-danger" role="alert">{{ error() }}</p> }

          <button type="submit" [disabled]="busy()"
                  class="w-full bg-ink-900 text-canvas-50 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
            @if (busy()) { …جارٍ الدخول } @else { دخول }
          </button>
        </form>
      </div>
    </div>
  `,
})
export class LoginScreen {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthStore);
  private readonly router = inject(Router);

  readonly returnUrl = input<string>('/');

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    const { email, password } = this.form.getRawValue();

    this.auth.login(email, password).subscribe({
      next: (res) => {
        const roles = res.data.user.roles;

        // الزبون قد يعرف عنوان اللوحة ويسجّل دخوله بنجاح — نرفضه هنا
        // بوضوح بدل إدخاله إلى شاشات فارغة.
        if (!roles.includes('admin') && !roles.includes('artist')) {
          this.auth.logout('/login');
          this.busy.set(false);
          this.error.set('هذا الحساب لا يملك صلاحية الدخول إلى لوحة التحكم.');
          return;
        }

        this.router.navigateByUrl(this.returnUrl() || '/');
      },
      error: (err) => {
        this.busy.set(false);
        this.error.set(err?.error?.errors?.email?.[0] ?? err?.error?.message ?? 'تعذّر تسجيل الدخول.');
      },
    });
  }
}
