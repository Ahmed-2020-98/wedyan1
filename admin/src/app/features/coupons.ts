import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Money } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

interface Coupon {
  id: number;
  code: string;
  description: string | null;
  type: string;
  typeLabel: string;
  value: number;
  displayValue: string;
  minOrderAmount: Money | null;
  maxDiscountAmount: Money | null;
  startsAt: string | null;
  expiresAt: string | null;
  usageLimit: number | null;
  usageLimitPerUser: number | null;
  usedCount: number;
  isActive: boolean;
  isCurrentlyValid: boolean;
}

@Component({
  selector: 'app-manage-coupons',
  imports: [ReactiveFormsModule, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="flex items-center justify-between mb-7">
      <h1 class="font-display text-3xl text-ink-900">الكوبونات</h1>
      <button type="button" (click)="open()"
              class="bg-ink-900 text-canvas-50 px-5 py-2.5 text-sm hover:bg-ink-800 transition-colors">
        + كوبون جديد
      </button>
    </header>

    @if (showForm()) {
      <form [formGroup]="form" (ngSubmit)="save()" class="frame rounded-card p-6 mb-6 grid sm:grid-cols-2 gap-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الرمز <span class="text-danger">*</span></span>
          <input formControlName="code" type="text" placeholder="WELCOME10"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">النوع <span class="text-danger">*</span></span>
          <select formControlName="type"
                  class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500">
            <option value="percentage">نسبة مئوية</option>
            <option value="fixed">مبلغ ثابت</option>
          </select>
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">القيمة <span class="text-danger">*</span></span>
          <input formControlName="value" type="number" min="0.01" step="1"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">أقل مبلغ للطلب</span>
          <input formControlName="min_order_amount" type="number" min="0"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">سقف الخصم</span>
          <input formControlName="max_discount_amount" type="number" min="0"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
          <span class="text-xs text-ink-400 mt-1 block">يحمي من نسبة مئوية على لوحة باهظة.</span>
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">ينتهي في</span>
          <input formControlName="expires_at" type="date"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">حدّ الاستخدام الكلّي</span>
          <input formControlName="usage_limit" type="number" min="1"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">حدّ الاستخدام لكل زبون</span>
          <input formControlName="usage_limit_per_user" type="number" min="1"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block sm:col-span-2">
          <span class="block text-sm text-ink-600 mb-2">الوصف</span>
          <input formControlName="description" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>

        @if (error()) { <p class="text-sm text-danger sm:col-span-2">{{ error() }}</p> }

        <div class="flex gap-3 sm:col-span-2">
          <button type="submit" [disabled]="busy()"
                  class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">حفظ</button>
          <button type="button" (click)="showForm.set(false)"
                  class="border border-ink-300 px-6 py-3 text-sm hover:border-ink-900 transition-colors">إلغاء</button>
        </div>
      </form>
    }

    @if (coupons.isLoading()) {
      <app-spinner />
    } @else if (coupons.value(); as res) {
      <div class="frame rounded-card overflow-x-auto">
        <table class="w-full text-sm min-w-[40rem]">
          <thead>
            <tr class="text-xs text-ink-400 border-b border-ink-100">
              <th class="text-start font-normal px-5 py-4">الرمز</th>
              <th class="text-start font-normal px-5 py-4">الخصم</th>
              <th class="text-start font-normal px-5 py-4">أقل طلب</th>
              <th class="text-start font-normal px-5 py-4">الاستخدام</th>
              <th class="text-start font-normal px-5 py-4">ينتهي</th>
              <th class="text-start font-normal px-5 py-4">الحالة</th>
              <th class="text-start font-normal px-5 py-4"></th>
            </tr>
          </thead>
          <tbody>
            @for (c of res.data; track c.id) {
              <tr class="border-b border-ink-50">
                <td class="px-5 py-4 text-ink-800" data-numeric>{{ c.code }}</td>
                <td class="px-5 py-4 text-ink-700" data-numeric>{{ c.displayValue }}</td>
                <td class="px-5 py-4 text-ink-500" data-numeric>{{ c.minOrderAmount ? (c.minOrderAmount | money) : '—' }}</td>
                <td class="px-5 py-4 text-ink-500" data-numeric>{{ c.usedCount }}{{ c.usageLimit ? ' / ' + c.usageLimit : '' }}</td>
                <td class="px-5 py-4 text-ink-400 text-xs" data-numeric>{{ c.expiresAt ? (c.expiresAt | arDate) : '—' }}</td>
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5" [class]="c.isCurrentlyValid ? 'bg-success/12 text-success' : 'bg-ink-100 text-ink-500'">
                    {{ c.isCurrentlyValid ? 'صالح' : 'غير صالح' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-end">
                  <button type="button" (click)="remove(c.id)" class="text-xs text-ink-300 hover:text-danger">حذف</button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class ManageCouponsScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);

  protected readonly showForm = signal(false);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    code: ['', [Validators.required]],
    description: [''],
    type: ['percentage', [Validators.required]],
    value: [10, [Validators.required, Validators.min(0.01)]],
    min_order_amount: [null as number | null],
    max_discount_amount: [null as number | null],
    expires_at: [''],
    usage_limit: [null as number | null],
    usage_limit_per_user: [null as number | null],
  });

  protected readonly coupons = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Coupon[] }>('/admin/coupons')),
  });

  protected open(): void {
    this.showForm.set(true);
    this.error.set(null);
    this.form.reset({ type: 'percentage', value: 10, code: '' });
  }

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    const payload = Object.fromEntries(
      Object.entries(this.form.getRawValue()).filter(([, v]) => v !== '' && v !== null),
    );

    this.api.post('/admin/coupons', payload).subscribe({
      next: () => { this.busy.set(false); this.showForm.set(false); this.coupons.reload(); },
      error: (err) => {
        this.busy.set(false);
        const errors = err?.error?.errors ?? {};
        this.error.set(Object.values(errors).flat()[0] as string ?? err?.error?.message ?? 'تعذّر الحفظ.');
      },
    });
  }

  protected remove(id: number): void {
    this.api.delete(`/admin/coupons/${id}`).subscribe(() => this.coupons.reload());
  }
}
