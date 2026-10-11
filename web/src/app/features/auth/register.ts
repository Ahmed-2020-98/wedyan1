import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthStore } from '../../core/auth.store';
import { CartStore } from '../../core/cart.store';
import { Seo } from '../../core/seo.service';

/** تطابق كلمتي المرور — يُتحقق منه في المجموعة لا في حقل واحد. */
function passwordsMatch(group: AbstractControl): ValidationErrors | null {
  const password = group.get('password')?.value;
  const confirmation = group.get('password_confirmation')?.value;

  return password && confirmation && password !== confirmation ? { mismatch: true } : null;
}

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-md px-5 py-20">
      <h1 class="font-display text-3xl text-ink-900 mb-2">حساب جديد</h1>
      <p class="text-sm text-ink-400 mb-9">لمتابعة طلباتك وحجوزاتك في مكان واحد.</p>

      <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الاسم</span>
          <input formControlName="name" type="text" autocomplete="name"
                 class="w-full bg-canvas-50 border px-3.5 py-3 text-sm outline-none transition-colors"
                 [class]="invalid('name') ? 'border-danger' : 'border-ink-200 focus:border-gilt-500'" />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">البريد الإلكتروني</span>
          <input formControlName="email" type="email" autocomplete="email" dir="ltr"
                 class="w-full bg-canvas-50 border px-3.5 py-3 text-sm outline-none transition-colors"
                 [class]="invalid('email') ? 'border-danger' : 'border-ink-200 focus:border-gilt-500'" />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">رقم الجوال</span>
          <input formControlName="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="05xxxxxxxx"
                 class="w-full bg-canvas-50 border px-3.5 py-3 text-sm outline-none transition-colors"
                 [class]="invalid('phone') ? 'border-danger' : 'border-ink-200 focus:border-gilt-500'" data-numeric />
          @if (invalid('phone')) { <span class="text-xs text-danger mt-1 block">أدخل رقم جوال سعودي صحيح</span> }
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">كلمة المرور</span>
          <input formControlName="password" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border px-3.5 py-3 text-sm outline-none transition-colors"
                 [class]="invalid('password') ? 'border-danger' : 'border-ink-200 focus:border-gilt-500'" />
          <span class="text-xs text-ink-400 mt-1 block">٨ أحرف على الأقل، وتشمل حرفاً ورقماً.</span>
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">تأكيد كلمة المرور</span>
          <input formControlName="password_confirmation" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 transition-colors" />
          @if (form.errors?.['mismatch'] && form.get('password_confirmation')?.touched) {
            <span class="text-xs text-danger mt-1 block">كلمتا المرور غير متطابقتين</span>
          }
        </label>

        @if (error()) { <p class="text-sm text-danger" role="alert">{{ error() }}</p> }

        <button type="submit" [disabled]="busy()"
                class="w-full bg-ink-900 text-canvas-50 py-3.5 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          @if (busy()) { …جارٍ الإنشاء } @else { إنشاء الحساب }
        </button>
      </form>

      <p class="mt-7 text-sm text-ink-400 text-center">
        لديك حساب؟
        <a routerLink="/login" [queryParams]="{ returnUrl: returnUrl() }" class="text-gilt-700 hover:underline">سجّل الدخول</a>
      </p>
    </div>
  `,
})
export class RegisterScreen {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthStore);
  private readonly cart = inject(CartStore);
  private readonly router = inject(Router);

  readonly returnUrl = input<string>('/account');

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group(
    {
      name: ['', [Validators.required, Validators.maxLength(255)]],
      email: ['', [Validators.required, Validators.email]],
      phone: ['', [Validators.required, Validators.pattern(/^(\+?966|0)?5\d{8}$/)]],
      password: ['', [Validators.required, Validators.minLength(8)]],
      password_confirmation: ['', [Validators.required]],
    },
    { validators: passwordsMatch },
  );

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    this.auth.register(this.form.getRawValue()).subscribe({
      next: () => {
        this.cart.load();
        this.router.navigateByUrl(this.returnUrl() || '/account');
      },
      error: (err) => {
        this.busy.set(false);
        const errors = err?.error?.errors ?? {};
        this.error.set(
          errors.email?.[0] ?? errors.phone?.[0] ?? errors.password?.[0]
            ?? err?.error?.message ?? 'تعذّر إنشاء الحساب.',
        );
      },
    });
  }

  protected invalid(control: string): boolean {
    const c = this.form.get(control);
    return !!c && c.invalid && c.touched;
  }
}
