import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-spinner',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="flex items-center justify-center gap-3 py-12 text-ink-400 text-sm" role="status">
      <span class="w-4 h-4 border-2 border-ink-200 border-t-gilt-500 rounded-full animate-spin"></span>
      <span>{{ label() }}</span>
    </div>
  `,
})
export class Spinner {
  readonly label = input('…جارٍ التحميل');
}
