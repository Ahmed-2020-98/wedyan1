import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { toSignal } from '@angular/core/rxjs-interop';
import { firstValueFrom } from 'rxjs';
import { resource } from '@angular/core';

import { Api } from '../../core/api';
import type { Category, Paginated, Painting, Resource } from '../../core/models';
import { Seo } from '../../core/seo.service';
import { EmptyState } from '../../shared/empty-state';
import { PaintingCard } from '../../shared/painting-card';
import { Spinner } from '../../shared/spinner';

interface FilterMeta {
  priceRange: { min: number; max: number };
  sizeRange: { minWidth: number; maxWidth: number; minHeight: number; maxHeight: number };
  styles: string[];
  materials: string[];
}

@Component({
  selector: 'app-gallery',
  imports: [FormsModule, PaintingCard, Spinner, EmptyState],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './gallery.html',
})
export class GalleryScreen {
  private readonly api = inject(Api);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly seo = inject(Seo);

  /**
   * الفلاتر تعيش في عنوان الصفحة لا في حالة المكوّن.
   *
   * وهذا مقصود: الزائر يشارك رابط «تجريدي تحت ٣٠٠٠ ريال» فيصل من يفتحه إلى
   * النتيجة نفسها، وزرّ الرجوع يتراجع خطوةً واحدة لا يقفز خارج المعرض.
   */
  private readonly params = toSignal(this.route.queryParamMap, { requireSync: true });

  protected readonly sortOptions = [
    { value: '-published_at', label: 'الأحدث' },
    { value: 'published_at', label: 'الأقدم' },
    { value: 'price', label: 'السعر: من الأقل' },
    { value: '-price', label: 'السعر: من الأعلى' },
    { value: '-views_count', label: 'الأكثر مشاهدة' },
    { value: '-rating_avg', label: 'الأعلى تقييماً' },
  ];

  protected readonly typeOptions = [
    { value: '', label: 'الكل' },
    { value: 'ready_made', label: 'جاهزة للشراء' },
    { value: 'reservable', label: 'قابلة للحجز' },
    { value: 'custom_order', label: 'طلب خاص' },
  ];

  protected readonly filtersOpen = signal(false);

  protected readonly categories = resource({
    loader: () => firstValueFrom(this.api.get<{ data: Category[] }>('/categories')),
  });

  protected readonly filterMeta = resource({
    loader: () => firstValueFrom(this.api.get<Resource<FilterMeta>>('/paintings/filters')),
  });

  protected readonly results = resource({
    params: () => this.queryFor(this.params()),
    loader: ({ params }) => firstValueFrom(this.api.get<Paginated<Painting>>('/paintings', params)),
  });

  constructor() {
    effect(() => {
      const p = this.params();
      const category = p.get('category');
      const search = p.get('search');

      this.seo.apply({
        title: search
          ? `نتائج البحث عن «${search}»`
          : category
            ? `لوحات ${category}`
            : 'المعرض — كل اللوحات',
        description: 'تصفّح اللوحات الفنية الأصلية حسب الأسلوب والمقاس والسعر.',
        path: '/paintings',
      });
    });
  }

  /** يبني معاملات الاستعلام بالشكل الذي يتوقّعه spatie/query-builder. */
  private queryFor(p: ReturnType<typeof this.params>): Record<string, string | number> {
    const query: Record<string, string | number> = {
      sort: p.get('sort') ?? '-published_at',
      page: Number(p.get('page') ?? 1),
      per_page: 24,
    };

    const map: Record<string, string> = {
      category: 'filter[category]',
      search: 'filter[search]',
      type: 'filter[type]',
      style: 'filter[style]',
      material: 'filter[material]',
      min_price: 'filter[min_price]',
      max_price: 'filter[max_price]',
      min_width: 'filter[min_width]',
      max_width: 'filter[max_width]',
      min_height: 'filter[min_height]',
      max_height: 'filter[max_height]',
      availability: 'filter[availability]',
    };

    for (const [key, param] of Object.entries(map)) {
      const value = p.get(key);
      if (value) query[param] = value;
    }

    return query;
  }

  protected readonly activeCount = computed(() => {
    const p = this.params();
    const keys = ['category', 'type', 'style', 'material', 'min_price', 'max_price', 'min_width', 'max_width', 'search'];

    return keys.filter((k) => p.get(k)).length;
  });

  /** يعدّل معاملاً واحداً ويعيد الترقيم إلى الصفحة الأولى. */
  protected setParam(key: string, value: string | null): void {
    const current: Record<string, string> = {};
    this.params().keys.forEach((k) => (current[k] = this.params().get(k)!));

    if (value === null || value === '') {
      delete current[key];
    } else {
      current[key] = value;
    }

    delete current['page'];

    this.router.navigate(['/paintings'], { queryParams: current });
  }

  protected goToPage(page: number): void {
    const current: Record<string, string> = {};
    this.params().keys.forEach((k) => (current[k] = this.params().get(k)!));
    current['page'] = String(page);

    this.router.navigate(['/paintings'], { queryParams: current });
  }

  protected clearAll(): void {
    this.router.navigate(['/paintings']);
  }

  protected value(key: string): string {
    return this.params().get(key) ?? '';
  }

  protected pages(last: number, current: number): number[] {
    const from = Math.max(1, current - 2);
    const to = Math.min(last, from + 4);

    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
  }
}
