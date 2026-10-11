import { ChangeDetectionStrategy, Component, inject, input, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import { CartStore } from '../../core/cart.store';
import type { Order, Resource } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

/**
 * صفحة العودة من بوابة الدفع.
 *
 * لا تعتمد على المعامل `payment` في العنوان لتقرّر النجاح: من يعود من
 * المتصفح يستطيع تحرير العنوان. الحقيقة تأتي من الخادم، الذي يستجوب المزوّد
 * إن لم يكن الخطّاف قد وصل بعد.
 */
@Component({
  selector: 'app-checkout-result',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-lg px-5 py-24 text-center">
      @if (order.isLoading()) {
        <app-spinner label="…جارٍ التأكّد من حالة الدفع" />
      } @else if (order.error()) {
        <p class="font-display text-2xl text-ink-800 mb-3">لم نجد هذا الطلب</p>
        <a routerLink="/account/orders" class="text-sm text-gilt-700 hover:underline">عرض طلباتي</a>
      } @else if (order.value(); as o) {

        @if (o.data.paymentStatus === 'paid') {
          <div class="w-14 h-14 mx-auto mb-7 rounded-full bg-success/12 grid place-items-center">
            <svg viewBox="0 0 24 24" class="w-7 h-7 text-success" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h1 class="font-display text-3xl text-ink-900 mb-3">تم استلام طلبك</h1>
          <p class="text-ink-500 leading-relaxed mb-2">
            شكراً لاقتنائك عملاً أصلياً. أرسلنا تفاصيل الطلب إلى بريدك.
          </p>
          <p class="text-sm text-ink-400 mb-9" data-numeric>رقم الطلب: {{ o.data.orderNumber }}</p>

        } @else if (o.data.paymentStatus === 'failed' || o.data.paymentStatus === 'cancelled') {
          <div class="w-14 h-14 mx-auto mb-7 rounded-full bg-danger/12 grid place-items-center">
            <svg viewBox="0 0 24 24" class="w-7 h-7 text-danger" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round"/>
            </svg>
          </div>
          <h1 class="font-display text-3xl text-ink-900 mb-3">لم يكتمل الدفع</h1>
          <p class="text-ink-500 leading-relaxed mb-9">
            لم يُخصم أي مبلغ. لوحتك ما تزال محجوزة لك — يمكنك المحاولة من صفحة الطلب.
          </p>

        } @else {
          <div class="w-14 h-14 mx-auto mb-7 rounded-full bg-warning/12 grid place-items-center">
            <span class="w-5 h-5 border-2 border-warning border-t-transparent rounded-full animate-spin"></span>
          </div>
          <h1 class="font-display text-3xl text-ink-900 mb-3">الدفع قيد التأكيد</h1>
          <p class="text-ink-500 leading-relaxed mb-9">
            وصلنا طلبك وننتظر تأكيد المزوّد. سنُعلمك فور اكتماله.
          </p>
        }

        <div class="frame rounded-card p-5 mb-8 text-start">
          <dl class="space-y-2.5 text-sm">
            <div class="flex justify-between">
              <dt class="text-ink-400">الحالة</dt>
              <dd class="text-ink-800">{{ o.data.statusLabel }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-ink-400">حالة الدفع</dt>
              <dd class="text-ink-800">{{ o.data.paymentStatusLabel }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-ink-400">الإجمالي</dt>
              <dd class="text-ink-800" data-numeric>{{ o.data.totals.total | money }}</dd>
            </div>
          </dl>
        </div>

        <div class="flex flex-wrap gap-3 justify-center">
          <a [routerLink]="['/account/orders', o.data.orderNumber]"
             class="bg-ink-900 text-canvas-50 px-6 py-3 text-sm hover:bg-ink-800 transition-colors">
            تتبّع الطلب
          </a>
          <a routerLink="/paintings"
             class="border border-ink-300 px-6 py-3 text-sm hover:border-ink-900 transition-colors">
            متابعة التصفّح
          </a>
        </div>
      }
    </div>
  `,
})
export class CheckoutResultScreen {
  private readonly api = inject(Api);
  private readonly cart = inject(CartStore);

  /** من ?order=... في العنوان */
  readonly order_ = input<string>('', { alias: 'order' });

  protected readonly order = resource({
    params: () => this.order_(),
    loader: async ({ params }) => {
      const res = await firstValueFrom(this.api.get<Resource<Order>>(`/orders/${params}`));
      this.cart.load();
      return res;
    },
  });
}
