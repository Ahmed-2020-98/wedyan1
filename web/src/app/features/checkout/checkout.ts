import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import { CartStore } from '../../core/cart.store';
import type { Address, Order, ShippingOption } from '../../core/models';
import { Seo } from '../../core/seo.service';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-checkout',
  imports: [ReactiveFormsModule, RouterLink, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './checkout.html',
})
export class CheckoutScreen {
  private readonly api = inject(Api);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly seo = inject(Seo);
  protected readonly cart = inject(CartStore);

  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly selectedRate = signal<ShippingOption | null>(null);

  /**
   * رقم الجوال السعودي: يقبل 05xxxxxxxx و +9665xxxxxxxx و 9665xxxxxxxx.
   * نفس التعبير المستخدم في الخادم، فلا يمرّ شيء من هنا ليُرفض هناك.
   */
  protected readonly form = this.fb.nonNullable.group({
    recipient_name: ['', [Validators.required, Validators.maxLength(255)]],
    phone: ['', [Validators.required, Validators.pattern(/^(\+?966|0)?5\d{8}$/)]],
    country: ['SA', [Validators.required]],
    city: ['', [Validators.required]],
    district: [''],
    street: ['', [Validators.required]],
    postal_code: [''],
    notes: [''],
    shipping_rate_id: [0, [Validators.required, Validators.min(1)]],
  });

  protected readonly addresses = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Address[] }>('/addresses')),
  });

  protected readonly shippingOptions = resource({
    params: () => this.form.controls.country.value,
    loader: ({ params }) =>
      firstValueFrom(
        this.api.get<{ data: ShippingOption[]; zone: string | null }>('/checkout/shipping-options', {
          country: params,
        }),
      ),
  });

  constructor() {
    this.seo.apply({ title: 'إتمام الطلب', path: '/checkout' });
    this.cart.load();
  }

  /** يملأ النموذج من عنوان محفوظ — يختصر أطول جزء في الشاشة. */
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

  protected chooseRate(rate: ShippingOption): void {
    this.selectedRate.set(rate);
    this.form.controls.shipping_rate_id.setValue(rate.id);
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      this.error.set('راجع الحقول المطلوبة.');
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.api
      .post<{ data: { order: Order; payment: { redirectUrl: string } } }>('/checkout', this.form.getRawValue())
      .subscribe({
        next: (res) => {
          this.cart.clear();
          /*
           | إعادة توجيه كاملة لا تنقّل داخلي: الوجهة صفحة الدفع المستضافة
           | لدى المزوّد وتقع خارج التطبيق تماماً.
           */
          window.location.href = res.data.payment.redirectUrl;
        },
        error: (err) => {
          this.submitting.set(false);
          this.error.set(
            err?.error?.errors?.cart?.[0] ?? err?.error?.message ?? 'تعذّر إتمام الطلب. حاول مرّة أخرى.',
          );
        },
      });
  }

  protected invalid(control: string): boolean {
    const c = this.form.get(control);
    return !!c && c.invalid && c.touched;
  }
}
