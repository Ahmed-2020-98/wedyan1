import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';

import { Api } from '../core/api';
import type { Paginated, User } from '../core/models';
import { Spinner } from '../shared/spinner';
import { AR_PIPES } from '../shared/pipes';

@Component({
  selector: 'app-manage-users',
  imports: [FormsModule, Spinner, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <header class="mb-7">
      <h1 class="font-display text-3xl text-ink-900 mb-4">المستخدمون</h1>
      <div class="flex flex-wrap gap-3">
        <input [(ngModel)]="term" (ngModelChange)="search.set($event)" type="search" placeholder="الاسم أو البريد…"
               class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500 w-60" />
        <select [ngModel]="role()" (ngModelChange)="role.set($event)"
                class="bg-canvas-50 border border-ink-200 px-3.5 py-2.5 text-sm outline-none focus:border-gilt-500">
          <option value="">كل الأدوار</option>
          <option value="customer">زبون</option>
          <option value="artist">فنان</option>
          <option value="admin">مدير</option>
        </select>
      </div>
    </header>

    @if (users.isLoading()) {
      <app-spinner />
    } @else if (users.value(); as res) {
      <div class="frame rounded-card overflow-x-auto">
        <table class="w-full text-sm min-w-[40rem]">
          <thead>
            <tr class="text-xs text-ink-400 border-b border-ink-100">
              <th class="text-start font-normal px-5 py-4">الاسم</th>
              <th class="text-start font-normal px-5 py-4">البريد</th>
              <th class="text-start font-normal px-5 py-4">الجوال</th>
              <th class="text-start font-normal px-5 py-4">الدور</th>
              <th class="text-start font-normal px-5 py-4">الحالة</th>
              <th class="text-start font-normal px-5 py-4">التسجيل</th>
              <th class="text-start font-normal px-5 py-4"></th>
            </tr>
          </thead>
          <tbody>
            @for (u of res.data; track u.id) {
              <tr class="border-b border-ink-50">
                <td class="px-5 py-4 text-ink-800">{{ u.name }}</td>
                <td class="px-5 py-4 text-ink-500 text-xs" dir="ltr">{{ u.email }}</td>
                <td class="px-5 py-4 text-ink-500" data-numeric>{{ u.phone | saudiPhone }}</td>
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5 bg-canvas-200 text-ink-600">{{ roleLabel(u.roles) }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs px-2 py-0.5" [class]="u.isActive ? 'bg-success/12 text-success' : 'bg-danger/10 text-danger'">
                    {{ u.isActive ? 'مفعّل' : 'معطّل' }}
                  </span>
                </td>
                <td class="px-5 py-4 text-ink-400 text-xs" data-numeric>{{ u.createdAt | arDate }}</td>
                <td class="px-5 py-4 text-end">
                  <button type="button" (click)="toggle(u.id)" [disabled]="busy()"
                          class="text-xs hover:underline disabled:opacity-50"
                          [class]="u.isActive ? 'text-danger' : 'text-success'">
                    {{ u.isActive ? 'تعطيل' : 'تفعيل' }}
                  </button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      <p class="text-xs text-ink-400 mt-4 leading-relaxed">
        تعطيل الحساب يُلغي رموز دخوله فوراً، ولا يحذف طلباته أو فواتيره.
      </p>
    }
  `,
})
export class ManageUsersScreen {
  private readonly api = inject(Api);

  protected term = '';
  protected readonly search = signal('');
  protected readonly role = signal('');
  protected readonly busy = signal(false);

  protected readonly users = resource({
    params: () => ({ search: this.search(), role: this.role() }),
    loader: ({ params }) =>
      firstValueFrom(
        this.api.get<Paginated<User>>('/admin/users', {
          search: params.search || undefined,
          role: params.role || undefined,
        }),
      ),
  });

  protected toggle(id: number): void {
    this.busy.set(true);
    this.api.put(`/admin/users/${id}/toggle-active`).subscribe({
      next: () => { this.busy.set(false); this.users.reload(); },
      error: () => this.busy.set(false),
    });
  }

  protected roleLabel(roles: string[]): string {
    if (roles.includes('admin')) return 'مدير';
    if (roles.includes('artist')) return 'فنان';
    return 'زبون';
  }
}
