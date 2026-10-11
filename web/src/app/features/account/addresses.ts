import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Address } from '../../core/models';
import { Spinner } from '../../shared/spinner';

@Component({
  selector: 'app-account-addresses',
  imports: [ReactiveFormsModule, Spinner],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="flex items-center justify-between mb-7">
      <h2 class="font-display text-2xl text-ink-900">العناوين</h2>
      <button type="button" (click)="toggleForm()"
              class="text-sm border border-ink-300 px-4 py-2 hover:border-ink-900 transition-colors">
        @if (showForm()) { إلغاء } @else { + عنوان جديد }
      </button>
    </div>

    @if (showForm()) {
      <form [formGroup]="form" (ngSubmit)="submit()" class="frame rounded-card p-6 mb-7 grid sm:grid-cols-2 gap-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">تسمية العنوان</span>
          <input formControlName="label" type="text" placeholder="المنزل، المكتب…"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">اسم المستلم <span class="text-danger">*</span></span>
          <input formControlName="recipient_name" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الجوال <span class="text-danger">*</span></span>
          <input formControlName="phone" type="tel" placeholder="05xxxxxxxx"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">المدينة <span class="text-danger">*</span></span>
          <input formControlName="city" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الحي</span>
          <input formControlName="district" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الرمز البريدي</span>
          <input formControlName="postal_code" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block sm:col-span-2">
          <span class="block text-sm text-ink-600 mb-2">الشارع والمبنى <span class="text-danger">*</span></span>
          <input formControlName="street" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="flex items-center gap-2.5 sm:col-span-2 text-sm text-ink-600">
          <input formControlName="is_default" type="checkbox" class="accent-gilt-600" />
          اجعله العنوان الافتراضي
        </label>

        @if (error()) { <p class="text-sm text-danger sm:col-span-2">{{ error() }}</p> }

        <button type="submit" [disabled]="busy()"
                class="sm:col-span-2 bg-ink-900 text-canvas-50 py-3 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">
          حفظ العنوان
        </button>
      </form>
    }

    @if (addresses.isLoading()) {
      <app-spinner />
    } @else if (addresses.value(); as res) {
      @if (res.data.length === 0 && !showForm()) {
        <p class="text-sm text-ink-400 py-8">لا توجد عناوين محفوظة.</p>
      } @else {
        <ul class="grid sm:grid-cols-2 gap-4">
          @for (a of res.data; track a.id) {
            <li class="frame rounded-card p-5">
              <div class="flex items-start justify-between gap-3 mb-3">
                <span class="text-sm text-ink-800">{{ a.label || a.recipientName }}</span>
                @if (a.isDefault) {
                  <span class="text-[0.65rem] px-2 py-0.5 bg-gilt-500/15 text-gilt-700 shrink-0">افتراضي</span>
                }
              </div>
              <p class="text-sm text-ink-500 leading-relaxed">
                {{ a.recipientName }}<br />
                {{ a.street }}@if (a.district) {، {{ a.district }}}<br />
                {{ a.city }}<br />
                <span data-numeric>{{ a.phone }}</span>
              </p>
              <button type="button" (click)="remove(a.id)"
                      class="text-xs text-ink-300 hover:text-danger mt-4 transition-colors">حذف</button>
            </li>
          }
        </ul>
      }
    }
  `,
})
export class AccountAddressesScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);

  protected readonly showForm = signal(false);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    label: [''],
    recipient_name: ['', [Validators.required]],
    phone: ['', [Validators.required, Validators.pattern(/^(\+?966|0)?5\d{8}$/)]],
    country: ['SA'],
    city: ['', [Validators.required]],
    district: [''],
    street: ['', [Validators.required]],
    postal_code: [''],
    is_default: [false],
  });

  protected readonly addresses = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Address[] }>('/addresses')),
  });

  protected toggleForm(): void {
    this.showForm.set(!this.showForm());
    this.error.set(null);
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('راجع الحقول المطلوبة.');
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    this.api.post('/addresses', this.form.getRawValue()).subscribe({
      next: () => {
        this.busy.set(false);
        this.showForm.set(false);
        this.form.reset({ country: 'SA', is_default: false });
        this.addresses.reload();
      },
      error: (err) => {
        this.busy.set(false);
        this.error.set(err?.error?.message ?? 'تعذّر حفظ العنوان.');
      },
    });
  }

  protected remove(id: number): void {
    this.api.delete(`/addresses/${id}`).subscribe(() => this.addresses.reload());
  }
}
