import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { AppNotification } from '../../core/models';
import { Spinner } from '../../shared/spinner';
import { AR_PIPES } from '../../shared/pipes';

@Component({
  selector: 'app-account-notifications',
  imports: [RouterLink, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="flex items-center justify-between mb-7">
      <h2 class="font-display text-2xl text-ink-900">الإشعارات</h2>
      <button type="button" (click)="markAllRead()"
              class="text-sm text-ink-400 hover:text-ink-800 transition-colors">
        تحديد الكل كمقروء
      </button>
    </div>

    @if (notifications.isLoading()) {
      <app-spinner />
    } @else if (notifications.value(); as res) {
      @if (res.data.length === 0) {
        <p class="text-sm text-ink-400 py-8">لا توجد إشعارات.</p>
      } @else {
        <ul class="space-y-3">
          @for (n of res.data; track n.id) {
            <li class="frame rounded-card p-5 flex items-start gap-4"
                [class.border-s-2]="!n.readAt" [class.border-gilt-500]="!n.readAt">
              <div class="flex-1 min-w-0">
                <p class="text-sm text-ink-800 mb-1">{{ n.title }}</p>
                <p class="text-sm text-ink-500 leading-relaxed">{{ n.body }}</p>
                <p class="text-xs text-ink-300 mt-2" data-numeric>{{ n.createdAt | arDateTime }}</p>
              </div>

              @if (n.url) {
                <a [routerLink]="n.url" (click)="markRead(n.id)"
                   class="text-xs text-gilt-700 hover:underline shrink-0">عرض</a>
              }
            </li>
          }
        </ul>
      }
    }
  `,
})
export class AccountNotificationsScreen {
  private readonly api = inject(Api);

  protected readonly notifications = resource({
    loader: () => firstValueFrom(this.api.get<{ data: AppNotification[] }>('/notifications')),
  });

  protected markRead(id: string): void {
    this.api.put(`/notifications/${id}/read`).subscribe();
  }

  protected markAllRead(): void {
    this.api.put('/notifications/read-all').subscribe(() => this.notifications.reload());
  }
}
