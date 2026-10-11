import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Category, PaintingDetail, Resource } from '../core/models';
import { Spinner } from '../shared/spinner';

/**
 * إضافة/تعديل لوحة.
 *
 * الصور تُرفع بعد إنشاء اللوحة لا معها: الرفع يحتاج معرّفاً تُنسب إليه
 * الملفات، ومحاولة الجمع بينهما في طلب واحد تعني ملفات يتيمة كلما فشل
 * التحقق من بقية الحقول.
 */
@Component({
  selector: 'app-painting-form',
  imports: [ReactiveFormsModule, RouterLink, Spinner],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './painting-form.html',
})
export class PaintingFormScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  readonly id = input<string>('');

  protected readonly isNew = computed(() => this.id() === '' || this.id() === 'new');
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly saved = signal(false);
  protected readonly uploading = signal(false);

  protected readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    sku: [''],
    category_id: [null as number | null],
    short_description: [''],
    description: [''],
    price: [0, [Validators.required, Validators.min(0)]],
    discount_price: [null as number | null],
    painting_type: ['ready_made', [Validators.required]],
    availability: ['available'],
    stock_quantity: [1],
    width: [null as number | null],
    height: [null as number | null],
    depth: [null as number | null],
    unit: ['cm'],
    weight: [null as number | null],
    material: [''],
    medium: [''],
    frame_info: [''],
    style: [''],
    deposit_percentage: [50],
    production_days: [null as number | null],
    is_featured: [false],
    seo_title: [''],
    seo_description: [''],
    published_at: [''],
  });

  protected readonly categories = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Category[] }>('/manage/categories')),
  });

  protected readonly painting = resource({
    params: () => (this.isNew() ? undefined : this.id()),
    loader: async ({ params }) => {
      const res = await firstValueFrom(
        this.api.get<Resource<PaintingDetail>>(`/manage/paintings/${params}`),
      );
      const p = res.data;

      this.form.patchValue({
        title: p.title,
        sku: p.sku,
        category_id: p.category?.id ?? null,
        short_description: p.shortDescription ?? '',
        description: p.description ?? '',
        price: p.price.amount,
        discount_price: p.discountPrice?.amount ?? null,
        painting_type: p.type,
        availability: p.availability,
        stock_quantity: p.stockQuantity,
        width: p.dimensions?.width ?? null,
        height: p.dimensions?.height ?? null,
        depth: p.dimensions?.depth ?? null,
        unit: p.dimensions?.unit ?? 'cm',
        weight: p.weight,
        material: p.material ?? '',
        medium: p.medium ?? '',
        frame_info: p.frameInfo ?? '',
        style: p.style ?? '',
        deposit_percentage: p.reservation?.depositPercentage ?? 50,
        production_days: p.reservation?.productionDays ?? null,
        is_featured: p.isFeatured,
        seo_title: p.seo.title ?? '',
        seo_description: p.seo.description ?? '',
        published_at: p.publishedAt ? p.publishedAt.slice(0, 10) : '',
      });

      return p;
    },
  });

  protected readonly isReservable = computed(() =>
    this.form.controls.painting_type.value !== 'ready_made',
  );

  protected save(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('راجع الحقول المطلوبة.');
      return;
    }

    this.busy.set(true);
    this.error.set(null);

    const raw = this.form.getRawValue();
    const payload = Object.fromEntries(
      Object.entries(raw).filter(([, v]) => v !== '' && v !== null),
    );

    const request = this.isNew()
      ? this.api.post<Resource<PaintingDetail>>('/manage/paintings', payload)
      : this.api.put<Resource<PaintingDetail>>(`/manage/paintings/${this.id()}`, payload);

    request.subscribe({
      next: (res) => {
        this.busy.set(false);
        this.saved.set(true);

        if (this.isNew()) {
          this.router.navigate(['/paintings', res.data.id]);
        } else {
          this.painting.reload();
          setTimeout(() => this.saved.set(false), 2500);
        }
      },
      error: (err) => {
        this.busy.set(false);
        const errors = err?.error?.errors ?? {};
        this.error.set(Object.values(errors).flat()[0] as string ?? err?.error?.message ?? 'تعذّر الحفظ.');
      },
    });
  }

  protected uploadImages(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = input.files;

    if (!files?.length || this.isNew()) return;

    const data = new FormData();
    for (const file of Array.from(files)) {
      data.append('images[]', file);
    }

    this.uploading.set(true);
    this.error.set(null);

    this.api.post(`/manage/paintings/${this.id()}/images`, data).subscribe({
      next: () => {
        this.uploading.set(false);
        input.value = '';
        this.painting.reload();
      },
      error: (err) => {
        this.uploading.set(false);
        this.error.set(err?.error?.message ?? 'تعذّر رفع الصور.');
      },
    });
  }

  protected deleteImage(imageId: number): void {
    this.api.delete(`/manage/paintings/${this.id()}/images/${imageId}`).subscribe(() => this.painting.reload());
  }

  protected setMain(imageId: number): void {
    this.api.put(`/manage/paintings/${this.id()}/images/${imageId}/main`).subscribe(() => this.painting.reload());
  }

  protected remove(): void {
    this.busy.set(true);

    this.api.delete(`/manage/paintings/${this.id()}`).subscribe({
      next: () => this.router.navigateByUrl('/paintings'),
      error: () => this.busy.set(false),
    });
  }

  protected invalid(control: string): boolean {
    const c = this.form.get(control);
    return !!c && c.invalid && c.touched;
  }
}
