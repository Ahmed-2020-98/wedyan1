import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { Api } from '../../core/api';
import { AuthStore } from '../../core/auth.store';

@Component({
  selector: 'app-account-profile',
  imports: [ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h2 class="font-display text-2xl text-ink-900 mb-7">بياناتي</h2>

    <section class="frame rounded-card p-7 mb-6 max-w-lg">
      <h3 class="font-display text-lg text-ink-900 mb-5">المعلومات الشخصية</h3>

      <form [formGroup]="profileForm" (ngSubmit)="saveProfile()" class="space-y-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الاسم</span>
          <input formControlName="name" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الجوال</span>
          <input formControlName="phone" type="tel"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">البريد الإلكتروني</span>
          <input [value]="auth.user()?.email" type="email" disabled dir="ltr"
                 class="w-full bg-canvas-200 border border-ink-100 px-3.5 py-3 text-sm text-ink-400" />
          <span class="text-xs text-ink-400 mt-1 block">لتغيير البريد تواصل مع الدعم.</span>
        </label>

        @if (profileMessage()) { <p class="text-sm text-success">{{ profileMessage() }}</p> }
        @if (profileError()) { <p class="text-sm text-danger">{{ profileError() }}</p> }

        <button type="submit" [disabled]="busy()"
                class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          حفظ التغييرات
        </button>
      </form>
    </section>

    <section class="frame rounded-card p-7 max-w-lg">
      <h3 class="font-display text-lg text-ink-900 mb-5">تغيير كلمة المرور</h3>

      <form [formGroup]="passwordForm" (ngSubmit)="savePassword()" class="space-y-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">كلمة المرور الحالية</span>
          <input formControlName="current_password" type="password" autocomplete="current-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">كلمة المرور الجديدة</span>
          <input formControlName="password" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>

        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">تأكيد كلمة المرور</span>
          <input formControlName="password_confirmation" type="password" autocomplete="new-password"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>

        @if (passwordMessage()) { <p class="text-sm text-success">{{ passwordMessage() }}</p> }
        @if (passwordError()) { <p class="text-sm text-danger">{{ passwordError() }}</p> }

        <p class="text-xs text-ink-400 leading-relaxed">
          تغيير كلمة المرور يُنهي جلساتك على الأجهزة الأخرى.
        </p>

        <button type="submit" [disabled]="busy()"
                class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          تغيير كلمة المرور
        </button>
      </form>
    </section>
  `,
})
export class AccountProfileScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);
  protected readonly auth = inject(AuthStore);

  protected readonly busy = signal(false);
  protected readonly profileMessage = signal<string | null>(null);
  protected readonly profileError = signal<string | null>(null);
  protected readonly passwordMessage = signal<string | null>(null);
  protected readonly passwordError = signal<string | null>(null);

  protected readonly profileForm = this.fb.nonNullable.group({
    name: [this.auth.user()?.name ?? '', [Validators.required]],
    phone: [this.auth.user()?.phone ?? '', [Validators.pattern(/^(\+?966|0)?5\d{8}$/)]],
  });

  protected readonly passwordForm = this.fb.nonNullable.group({
    current_password: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  });

  protected saveProfile(): void {
    if (this.profileForm.invalid) return;

    this.busy.set(true);
    this.profileMessage.set(null);
    this.profileError.set(null);

    this.api.put('/auth/profile', this.profileForm.getRawValue()).subscribe({
      next: () => {
        this.busy.set(false);
        this.profileMessage.set('حُفظت التغييرات.');
        this.auth.refresh().subscribe();
      },
      error: (err) => {
        this.busy.set(false);
        this.profileError.set(err?.error?.message ?? 'تعذّر الحفظ.');
      },
    });
  }

  protected savePassword(): void {
    if (this.passwordForm.invalid) return;

    this.busy.set(true);
    this.passwordMessage.set(null);
    this.passwordError.set(null);

    this.api.put('/auth/password', this.passwordForm.getRawValue()).subscribe({
      next: () => {
        this.busy.set(false);
        this.passwordMessage.set('تم تغيير كلمة المرور.');
        this.passwordForm.reset();
      },
      error: (err) => {
        this.busy.set(false);
        this.passwordError.set(
          err?.error?.errors?.current_password?.[0] ?? err?.error?.message ?? 'تعذّر التغيير.',
        );
      },
    });
  }
}
