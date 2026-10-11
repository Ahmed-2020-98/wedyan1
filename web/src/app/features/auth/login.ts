import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthStore } from '../../core/auth.store';
import { CartStore } from '../../core/cart.store';
import { Seo } from '../../core/seo.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-20">
      <h1 class="font-display text-3xl text-ink-900 mb-2">تسجيل الدخول</h1>
      <p class="text-sm text-ink-400 mb-9">أهلاً بعودتك.</p>

      <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5">
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

        @if (error()) {
          <p class="text-sm text-danger" role="alert">{{ error() }}</p>
        }

        <button type="submit" [disabled]="busy()"
                class="w-full bg-ink-900 text-canvas-50 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          @if (busy()) { …جارٍ الدخول } @else { دخول }
        </button>
      </form>

      <div class="mt-7 flex items-center justify-between text-sm">
        <a routerLink="/forgot-password" class="text-ink-400 hover:text-ink-700 transition-colors">نسيت كلمة المرور؟</a>
        <a routerLink="/register" [queryParams]="{ returnUrl: returnUrl() }" class="text-gilt-700 hover:underline">حساب جديد</a>
      </div>
    </div>
  `,
})
export class LoginScreen {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthStore);
  private readonly cart = inject(CartStore);
  private readonly router = inject(Router);

  readonly returnUrl = input<string>('/account');

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  constructor() {
    inject(Seo).apply({ title: 'تسجيل الدخول', path: '/login' });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    const { email, password } = this.form.getRawValue();

    this.auth.login(email, password).subscribe({
      next: () => {
        this.cart.load();
        this.router.navigateByUrl(this.returnUrl() || '/account');
      },
      error: (err) => {
        this.busy.set(false);
        this.error.set(
          err?.error?.errors?.email?.[0] ?? err?.error?.message ?? 'تعذّر تسجيل الدخول.',
        );
      },
    });
  }
}
