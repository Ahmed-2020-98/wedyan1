import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Category } from '../core/models';
import { Spinner } from '../shared/spinner';

@Component({
  selector: 'app-manage-categories',
  imports: [ReactiveFormsModule, Spinner],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="flex items-center justify-between mb-7">
      <h1 class="font-display text-3xl text-ink-900">التصنيفات</h1>
      <button type="button" (click)="openForm(null)"
              class="bg-ink-900 text-canvas-50 px-5 py-2.5 text-sm hover:bg-ink-800 transition-colors">
        + تصنيف جديد
      </button>
    </header>

    @if (editing() !== undefined) {
      <form [formGroup]="form" (ngSubmit)="save()" class="frame rounded-card p-6 mb-6 grid sm:grid-cols-2 gap-5">
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الاسم <span class="text-danger">*</span></span>
          <input formControlName="name" type="text"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="block">
          <span class="block text-sm text-ink-600 mb-2">الترتيب</span>
          <input formControlName="sort_order" type="number" min="0"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" data-numeric />
        </label>
        <label class="block sm:col-span-2">
          <span class="block text-sm text-ink-600 mb-2">الوصف</span>
          <textarea formControlName="description" rows="2"
                    class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500 resize-y"></textarea>
        </label>
        <label class="block sm:col-span-2">
          <span class="block text-sm text-ink-600 mb-2">وصف الصفحة (SEO)</span>
          <input formControlName="seo_description" type="text" maxlength="500"
                 class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-3 text-sm outline-none focus:border-gilt-500" />
        </label>
        <label class="flex items-center gap-2.5 text-sm text-ink-600 sm:col-span-2">
          <input formControlName="is_active" type="checkbox" class="accent-gilt-600" />
          مفعّل
        </label>

        @if (error()) { <p class="text-sm text-danger sm:col-span-2">{{ error() }}</p> }

        <div class="flex gap-3 sm:col-span-2">
          <button type="submit" [disabled]="busy()"
                  class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 disabled:opacity-50 transition-colors">حفظ</button>
          <button type="button" (click)="editing.set(undefined)"
                  class="border border-ink-300 px-6 py-3 text-sm hover:border-ink-900 transition-colors">إلغاء</button>
        </div>
      </form>
    }

    @if (categories.isLoading()) {
      <app-spinner />
    } @else if (categories.value(); as res) {
      <div class="frame rounded-card overflow-x-auto">
        <table class="w-full text-sm min-w-[32rem]">
          <thead>
            <tr class="text-xs text-ink-400 border-b border-ink-100">
              <th class="text-start font-normal px-5 py-4">الاسم</th>
              <th class="text-start font-normal px-5 py-4">المُعرِّف</th>
              <th class="text-start font-normal px-5 py-4">اللوحات</th>
              <th class="text-start font-normal px-5 py-4">الحالة</th>
              <th class="text-start font-normal px-5 py-4"></th>
            </tr>
          </thead>
          <tbody>
            @for (c of res.data; track c.id) {
              <tr class="border-b border-ink-50">
                <td class="px-5 py-4 text-ink-800">{{ c.name }}</td>
                <td class="px-5 py-4 text-ink-400 text-xs">{{ c.slug }}</td>
                <td class="px-5 py-4 text-ink-600" data-numeric>{{ c.paintingsCount }}</td>
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5" [class]="c.isActive ? 'bg-success/12 text-success' : 'bg-ink-100 text-ink-500'">
                    {{ c.isActive ? 'مفعّل' : 'معطّل' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-end whitespace-nowrap">
                  <button type="button" (click)="openForm(c)" class="text-xs text-ink-500 hover:text-ink-900 me-3">تعديل</button>
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
export class ManageCategoriesScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);

  /** undefined = مغلق، null = إنشاء، كائن = تعديل */
  protected readonly editing = signal<Category | null | undefined>(undefined);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required]],
    description: [''],
    seo_description: [''],
    sort_order: [0],
    is_active: [true],
  });

  protected readonly categories = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Category[] }>('/manage/categories')),
  });

  protected openForm(category: Category | null): void {
    this.editing.set(category);
    this.error.set(null);

    this.form.reset({
      name: category?.name ?? '',
      description: category?.description ?? '',
      seo_description: category?.seo?.description ?? '',
      sort_order: category?.sortOrder ?? 0,
      is_active: category?.isActive ?? true,
    });
  }

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    const current = this.editing();
    const payload = this.form.getRawValue();

    const request = current
      ? this.api.put(`/manage/categories/${current.id}`, payload)
      : this.api.post('/manage/categories', payload);

    request.subscribe({
      next: () => { this.busy.set(false); this.editing.set(undefined); this.categories.reload(); },
      error: (err) => {
        this.busy.set(false);
        this.error.set(err?.error?.message ?? 'تعذّر الحفظ.');
      },
    });
  }

  protected remove(id: number): void {
    this.api.delete(`/manage/categories/${id}`).subscribe(() => this.categories.reload());
  }
}
