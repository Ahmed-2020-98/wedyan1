import { ChangeDetectionStrategy, Component, input } from '@angular/core';

@Component({
  selector: 'app-empty-state',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="text-center py-20 px-6">
      <p class="font-display text-xl text-ink-700 mb-2">{{ title() }}</p>
      @if (note()) {
        <p class="text-sm text-ink-400 max-w-sm mx-auto leading-relaxed">{{ note() }}</p>
      }
      <ng-content />
    </div>
  `,
})
export class EmptyState {
  readonly title = input.required<string>();
  readonly note = input<string | null>(null);
}
