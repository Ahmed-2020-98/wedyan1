import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import { Spinner } from '../shared/spinner';

type SettingsMap = Record<string, Record<string, unknown>>;

@Component({
  selector: 'app-manage-settings',
  imports: [FormsModule, Spinner],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <h1 class="font-display text-3xl text-ink-900 mb-7">الإعدادات</h1>

    @if (saved()) {
      <p class="bg-success/8 border-s-2 border-success px-4 py-3 text-sm text-success mb-6">حُفظت الإعدادات.</p>
    }

    @if (settings.isLoading()) {
      <app-spinner />
    } @else if (settings.value(); as res) {
      <div class="space-y-5 max-w-2xl">
        @for (group of groups(res.data); track group.name) {
          <section class="frame rounded-card p-6">
            <h2 class="font-display text-lg text-ink-900 mb-5">{{ groupLabel(group.name) }}</h2>

            <div class="space-y-4">
              @for (entry of group.entries; track entry.key) {
                <label class="grid sm:grid-cols-[12rem_1fr] gap-3 items-center">
                  <span class="text-sm text-ink-600">{{ settingLabel(entry.key) }}</span>

                  @if (isBoolean(entry.value)) {
                    <input type="checkbox" [checked]="!!entry.value"
                           (change)="stage(entry.key, $any($event.target).checked)"
                           class="accent-gilt-600 justify-self-start w-4 h-4" />
                  } @else {
                    <input type="text" [value]="entry.value"
                           (change)="stage(entry.key, $any($event.target).value)"
                           class="w-full bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500" />
                  }
                </label>
              }
            </div>
          </section>
        }

        <button type="button" (click)="save()" [disabled]="busy() || pending().size === 0"
                class="bg-ink-900 text-canvas-50 px-7 py-3 text-sm hover:bg-ink-800 disabled:opacity-40 transition-colors">
          @if (busy()) { …جارٍ الحفظ } @else { احفظ التغييرات }
        </button>
      </div>
    }
  `,
})
export class ManageSettingsScreen {
  private readonly api = inject(Api);

  protected readonly busy = signal(false);
  protected readonly saved = signal(false);
  /** ما تغيّر ولم يُحفظ بعد — نرسل المعدَّل فقط لا كل الإعدادات. */
  protected readonly pending = signal(new Map<string, unknown>());

  private readonly labels: Record<string, string> = {
    'store.name': 'اسم المتجر',
    'store.tagline': 'الشعار',
    'store.email': 'بريد التواصل',
    'store.phone': 'رقم التواصل',
    'tax.enabled': 'تفعيل الضريبة',
    'tax.percentage': 'نسبة الضريبة (٪)',
    'reservation.default_deposit_percentage': 'نسبة العربون الافتراضية (٪)',
    'reviews.require_purchase': 'اشتراط الشراء للمراجعة',
  };

  private readonly groupLabels: Record<string, string> = {
    general: 'عام',
    tax: 'الضريبة',
    reservation: 'الحجوزات',
    reviews: 'المراجعات',
  };

  protected readonly settings = resource({
    loader: () => firstValueFrom(this.api.get<{ data: SettingsMap }>('/admin/settings')),
  });

  protected groups(data: SettingsMap) {
    return Object.entries(data).map(([name, entries]) => ({
      name,
      entries: Object.entries(entries).map(([key, value]) => ({ key, value })),
    }));
  }

  protected groupLabel(name: string): string {
    return this.groupLabels[name] ?? name;
  }

  protected settingLabel(key: string): string {
    return this.labels[key] ?? key;
  }

  protected isBoolean(value: unknown): boolean {
    return typeof value === 'boolean';
  }

  protected stage(key: string, value: unknown): void {
    const next = new Map(this.pending());
    next.set(key, value);
    this.pending.set(next);
    this.saved.set(false);
  }

  protected save(): void {
    this.busy.set(true);

    const settings = [...this.pending().entries()].map(([key, value]) => ({ key, value }));

    this.api.put('/admin/settings', { settings }).subscribe({
      next: () => {
        this.busy.set(false);
        this.saved.set(true);
        this.pending.set(new Map());
        this.settings.reload();
      },
      error: () => this.busy.set(false),
    });
  }
}
