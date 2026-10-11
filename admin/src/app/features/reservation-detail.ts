import { ChangeDetectionStrategy, Component, inject, input, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Reservation, Resource } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

/**
 * شاشة الفنانة لإدارة حجز واحد.
 *
 * الإجراءات المتاحة تتبع حالة الحجز لا تُعرض كلها دائماً: عرض زرّ «اكتملت
 * اللوحة» على حجز لم يُدفع عربونه بعد يدعو إلى خطأ يرفضه الخادم.
 */
@Component({
  selector: 'app-manage-reservation-detail',
  imports: [ReactiveFormsModule, RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './reservation-detail.html',
})
export class ManageReservationDetailScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);

  readonly number = input.required<string>();

  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly panel = signal<'none' | 'accept' | 'reject' | 'update'>('none');

  protected readonly acceptForm = this.fb.nonNullable.group({
    price: [0, [Validators.required, Validators.min(1)]],
    note: [''],
    due_date: [''],
  });

  protected readonly rejectForm = this.fb.nonNullable.group({
    reason: ['', [Validators.required, Validators.minLength(5)]],
  });

  protected readonly updateForm = this.fb.nonNullable.group({
    title: ['', [Validators.required]],
    note: [''],
    progress_percentage: [0, [Validators.min(0), Validators.max(100)]],
    visible_to_customer: [true],
  });

  protected readonly reservation = resource({
    params: () => this.number(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<Resource<Reservation>>(`/manage/reservations/${params}`)),
  });

  protected accept(): void {
    if (this.acceptForm.invalid) {
      this.acceptForm.markAllAsTouched();
      return;
    }

    const raw = this.acceptForm.getRawValue();

    this.run(`/manage/reservations/${this.number()}/accept`, {
      price: raw.price,
      note: raw.note || null,
      due_date: raw.due_date || null,
    });
  }

  protected reject(): void {
    if (this.rejectForm.invalid) {
      this.rejectForm.markAllAsTouched();
      return;
    }

    this.run(`/manage/reservations/${this.number()}/reject`, this.rejectForm.getRawValue());
  }

  protected addUpdate(): void {
    if (this.updateForm.invalid) {
      this.updateForm.markAllAsTouched();
      return;
    }

    this.run(`/manage/reservations/${this.number()}/updates`, this.updateForm.getRawValue());
  }

  protected complete(): void {
    this.run(`/manage/reservations/${this.number()}/complete`, {});
  }

  protected transition(status: string): void {
    this.busy.set(true);
    this.error.set(null);

    this.api.put(`/manage/reservations/${this.number()}/status`, { status }).subscribe({
      next: () => { this.busy.set(false); this.reservation.reload(); },
      error: (err) => this.fail(err),
    });
  }

  private run(path: string, body: unknown): void {
    this.busy.set(true);
    this.error.set(null);

    this.api.post(path, body).subscribe({
      next: () => {
        this.busy.set(false);
        this.panel.set('none');
        this.updateForm.reset({ visible_to_customer: true, progress_percentage: 0, title: '', note: '' });
        this.reservation.reload();
      },
      error: (err) => this.fail(err),
    });
  }

  private fail(err: { error?: { message?: string; errors?: Record<string, string[]> } }): void {
    this.busy.set(false);
    const errors = err?.error?.errors ?? {};
    this.error.set(errors['status']?.[0] ?? err?.error?.message ?? 'تعذّر تنفيذ الإجراء.');
  }
}
