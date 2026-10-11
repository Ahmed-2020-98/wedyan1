import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import type { HomeSections, Resource } from '../../core/models';
import { Seo } from '../../core/seo.service';
import { PaintingCard } from '../../shared/painting-card';
import { Spinner } from '../../shared/spinner';

@Component({
  selector: 'app-home',
  imports: [RouterLink, PaintingCard, Spinner],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './home.html',
})
export class HomeScreen {
  private readonly api = inject(Api);
  private readonly seo = inject(Seo);

  /**
   * قسم واحد يجلب كل ما تحتاجه الصفحة.
   *
   * resource() لا subscribe: يعمل أثناء التصيير على الخادم ويُنقل ناتجه إلى
   * المتصفح مع حالة الترطيب، فلا يتكرّر الطلب بعد الإقلاع.
   */
  /** خطوات الحجز كما تُعرض في قسم «اطلب لوحتك». */
  protected readonly steps = [
    { n: '١', text: 'اختر لوحة قابلة للحجز أو صف ما تريد' },
    { n: '٢', text: 'تراجع الفنانة الطلب وتحدّد السعر والمدة' },
    { n: '٣', text: 'تدفع العربون فيبدأ التنفيذ' },
    { n: '٤', text: 'تتابع المراحل بالصور حتى تصلك اللوحة' },
  ];

  protected readonly sections = resource({
    loader: async () => {
      const res = await firstValueFrom(this.api.get<Resource<HomeSections>>('/home'));

      this.seo.apply({
        title: 'وديان — لوحات فنية أصلية',
        description: 'معرض إلكتروني للوحات فنية أصلية. اقتنِ عملاً جاهزاً أو احجز لوحة تُرسم خصيصاً لك.',
        path: '/',
        image: res.data.featured[0]?.mainImage?.large ?? null,
      });

      return res.data;
    },
  });
}
