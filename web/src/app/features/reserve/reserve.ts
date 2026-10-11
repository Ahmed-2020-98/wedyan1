import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { Address, PaintingDetail, Reservation, Resource } from '../../core/models';
import { Seo } from '../../core/seo.service';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

/**
 * نموذج طلب لوحة مخصّصة.
 *
 * لا يوجد دفع هنا إطلاقاً. الزبون يصف ما يريد فقط، ثم تراجع الفنانة الطلب
 * وتحدّد السعر — ولذلك لا حقل سعر في هذه الشاشة ولا في الطلب المُرسَل.
 */
@Component({
  selector: 'app-reserve',
  imports: [ReactiveFormsModule, RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './reserve.html',
})
export class ReserveScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly seo = inject(Seo);

  /** معامل المسار — input() لا signal()، انظر PaintingScreen. */
  readonly slug = input.required<string>();

  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.nonNullable.group({
    dimension_preset_id: [0],
    width: [0, [Validators.min(10), Validators.max(500)]],
    height: [0, [Validators.min(10), Validators.max(500)]],
    unit: ['cm'],
    description: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(2000)]],
    preferred_colors: [''],
    note: [''],
    recipient_name: ['', [Validators.required]],
    phone: ['', [Validators.required, Validators.pattern(/^(\+?966|0)?5\d{8}$/)]],
    country: ['SA'],
    city: ['', [Validators.required]],
    district: [''],
    street: ['', [Validators.required]],
    postal_code: [''],
  });

  protected readonly painting = resource({
    params: () => this.slug(),
    loader: async ({ params }) => {
      const res = await firstValueFrom(
        this.api.get<Resource<PaintingDetail>>(`/paintings/${encodeURIComponent(params)}`),
      );

      this.seo.apply({ title: `اطلب «${res.data.title}»`, path: `/reserve/${params}` });

      // المقاس الأصلي كنقطة بداية معقولة.
      if (res.data.dimensions) {
        this.form.patchValue({
          width: res.data.dimensions.width,
          height: res.data.dimensions.height,
        });
      }

      return res.data;
    },
  });

  protected readonly addresses = resource({
    loader: async () => {
      const res = await firstValueFrom(this.api.get<{ data: Address[] }>('/addresses'));
      const preferred = res.data.find((a) => a.isDefault) ?? res.data[0];

      if (preferred) this.useAddress(preferred);

      return res;
    },
  });

  /** السعر التقديري = سعر العمل + فرق المقاس المختار. */
  protected readonly estimate = computed(() => {
    const p = this.painting.value();
    if (!p) return null;

    const presetId = this.form.controls.dimension_preset_id.value;
    const preset = p.dimensionPresets.find((d) => d.id === presetId);
    const modifier = preset?.priceModifier?.amount ?? 0;
    const total = p.effectivePrice.amount + modifier;

    return {
      total,
      deposit: p.reservation ? (total * p.reservation.depositPercentage) / 100 : 0,
    };
  });

  protected choosePreset(id: number, width: number, height: number): void {
    this.form.patchValue({ dimension_preset_id: id, width, height });
  }

  protected useAddress(address: Address): void {
    this.form.patchValue({
      recipient_name: address.recipientName,
      phone: address.phone,
      country: address.country,
      city: address.city,
      district: address.district ?? '',
      street: address.street,
      postal_code: address.postalCode ?? '',
    });
  }

  protected submit(): void {
    const p = this.painting.value();
    if (!p) return;

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('راجع الحقول المطلوبة.');
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    const raw = this.form.getRawValue();

    this.api
      .post<Resource<Reservation>>('/reservations', {
        painting_id: p.id,
        title: p.title,
        ...raw,
        dimension_preset_id: raw.dimension_preset_id || null,
      })
      .subscribe({
        next: (res) => this.router.navigate(['/account/reservations', res.data.reservationNumber]),
        error: (err) => {
          this.submitting.set(false);
          this.error.set(err?.error?.message ?? 'تعذّر إرسال الطلب. حاول مرّة أخرى.');
        },
      });
  }

  protected invalid(control: string): boolean {
    const c = this.form.get(control);
    return !!c && c.invalid && c.touched;
  }
}
