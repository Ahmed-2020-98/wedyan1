import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { CartStore } from '../../core/cart.store';
import { Seo } from '../../core/seo.service';
import { EmptyState } from '../../shared/empty-state';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-cart',
  imports: [RouterLink, FormsModule, EmptyState, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './cart.html',
})
export class CartScreen {
  protected readonly cart = inject(CartStore);
  private readonly seo = inject(Seo);

  protected coupon = '';
  protected readonly couponError = signal<string | null>(null);
  protected readonly busy = signal(false);

  constructor() {
    this.seo.apply({ title: 'سلّة المشتريات', path: '/cart' });
    this.cart.load();
  }

  protected updateQuantity(itemId: number, quantity: number): void {
    if (quantity < 0) return;

    this.busy.set(true);
    this.cart.updateQuantity(itemId, quantity).subscribe({
      next: () => this.busy.set(false),
      error: () => this.busy.set(false),
    });
  }

  protected remove(itemId: number): void {
    this.busy.set(true);
    this.cart.remove(itemId).subscribe({
      next: () => this.busy.set(false),
      error: () => this.busy.set(false),
    });
  }

  protected applyCoupon(): void {
    const code = this.coupon.trim();
    if (!code) return;

    this.busy.set(true);
    this.couponError.set(null);

    this.cart.applyCoupon(code).subscribe({
      next: () => { this.busy.set(false); this.coupon = ''; },
      error: (err) => {
        this.busy.set(false);
        this.couponError.set(err?.error?.errors?.coupon?.[0] ?? err?.error?.message ?? 'كوبون غير صالح.');
      },
    });
  }

  protected removeCoupon(): void {
    this.busy.set(true);
    this.cart.removeCoupon().subscribe({
      next: () => this.busy.set(false),
      error: () => this.busy.set(false),
    });
  }
}
