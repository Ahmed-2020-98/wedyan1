import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { Api } from './api';
import { AuthStore } from './auth.store';
import type { Cart } from './models';

/**
 * حالة السلة.
 *
 * مصدر الحقيقة هو الخادم لا المتصفح: اللوحة قطعة واحدة وقد تُباع بينما هي
 * في سلة الزائر، ونسخة محلية «متفائلة» ستكذب عليه حتى لحظة الدفع. كل عملية
 * تُعيد السلة كاملة من الخادم بإجمالياتها المحسوبة هناك.
 */
@Injectable({ providedIn: 'root' })
export class CartStore {
  private readonly api = inject(Api);
  private readonly auth = inject(AuthStore);

  private readonly _cart = signal<Cart | null>(null);
  private readonly _loading = signal(false);
  private readonly _removed = signal<string[]>([]);

  readonly cart = this._cart.asReadonly();
  readonly loading = this._loading.asReadonly();
  /** أسماء ما أُزيل تلقائياً لأنه لم يعد متاحاً — تعرضه الواجهة كتنبيه. */
  readonly removed = this._removed.asReadonly();

  readonly itemCount = computed(() => this._cart()?.totals.itemCount ?? 0);
  readonly isEmpty = computed(() => (this._cart()?.items.length ?? 0) === 0);

  load(): void {
    if (!this.auth.isAuthenticated()) {
      this._cart.set(null);
      return;
    }

    this._loading.set(true);
    this.api.get<{ data: Cart; removed: string[] }>('/cart').subscribe({
      next: (res) => {
        this._cart.set(res.data);
        this._removed.set(res.removed ?? []);
        this._loading.set(false);
      },
      error: () => this._loading.set(false),
    });
  }

  add(paintingId: number, quantity = 1) {
    return this.mutate(this.api.post<{ data: Cart }>('/cart/items', { painting_id: paintingId, quantity }));
  }

  updateQuantity(itemId: number, quantity: number) {
    return this.mutate(this.api.put<{ data: Cart }>(`/cart/items/${itemId}`, { quantity }));
  }

  remove(itemId: number) {
    return this.mutate(this.api.delete<{ data: Cart }>(`/cart/items/${itemId}`));
  }

  applyCoupon(code: string) {
    return this.mutate(this.api.post<{ data: Cart }>('/cart/coupon', { code }));
  }

  removeCoupon() {
    return this.mutate(this.api.delete<{ data: Cart }>('/cart/coupon'));
  }

  clear(): void {
    this._cart.set(null);
    this._removed.set([]);
  }

  /**
   * كل عملية تُعيد السلة كاملة، فنكتفي بتخزين ما وصل بدل إعادة الحساب
   * محلياً — الإجماليات والضريبة والخصم كلها من الخادم.
   */
  private mutate(request: Observable<{ data: Cart }>): Observable<{ data: Cart }> {
    this._loading.set(true);

    return request.pipe(
      tap({
        next: (res) => {
          this._cart.set(res.data);
          this._loading.set(false);
        },
        error: () => this._loading.set(false),
      }),
    );
  }
}
