import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Money } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

interface Zone {
  id: number;
  name: string;
  countries: string[];
  isActive: boolean;
  rates: {
    id: number; name: string; cost: Money;
    freeShippingThreshold: Money | null;
    minDays: number | null; maxDays: number | null; isActive: boolean;
  }[];
}

@Component({
  selector: 'app-manage-shipping',
  imports: [Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h1 class="font-display text-3xl text-ink-900 mb-7">الشحن</h1>

    @if (zones.isLoading()) {
      <app-spinner />
    } @else if (zones.value(); as res) {
      <div class="space-y-5">
        @for (z of res.data; track z.id) {
          <section class="frame rounded-card p-6">
            <div class="flex items-start justify-between gap-4 mb-5">
              <div>
                <h2 class="font-display text-lg text-ink-900 mb-1">{{ z.name }}</h2>
                <p class="text-xs text-ink-400" dir="ltr">{{ z.countries.join('، ') }}</p>
              </div>
              <span class="text-xs px-2.5 py-1" [class]="z.isActive ? 'bg-success/12 text-success' : 'bg-ink-100 text-ink-500'">
                {{ z.isActive ? 'مفعّلة' : 'معطّلة' }}
              </span>
            </div>

            @if (z.rates.length === 0) {
              <p class="text-sm text-ink-400">لا طرق شحن في هذه المنطقة.</p>
            } @else {
              <ul class="space-y-3">
                @for (r of z.rates; track r.id) {
                  <li class="flex flex-wrap items-center justify-between gap-4 border-t border-ink-100 pt-3">
                    <div>
                      <p class="text-sm text-ink-800">{{ r.name }}</p>
                      @if (r.minDays) {
                        <p class="text-xs text-ink-400 mt-0.5" data-numeric>{{ r.minDays }}–{{ r.maxDays }} أيام عمل</p>
                      }
                    </div>
                    <div class="text-end">
                      <p class="text-sm text-ink-800" data-numeric>{{ r.cost | money }}</p>
                      @if (r.freeShippingThreshold) {
                        <p class="text-xs text-success mt-0.5" data-numeric>
                          مجاني فوق {{ r.freeShippingThreshold | money }}
                        </p>
                      }
                    </div>
                  </li>
                }
              </ul>
            }
          </section>
        }
      </div>
    }
  `,
})
export class ManageShippingScreen {
  private readonly api = inject(Api);

  protected readonly zones = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Zone[] }>('/admin/shipping')),
  });
}
