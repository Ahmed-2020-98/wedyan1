import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Seo } from '../../core/seo.service';

@Component({
  selector: 'app-about',
  imports: [RouterLink],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="mx-auto max-w-3xl px-5 sm:px-8 py-20">
      <p class="eyebrow mb-6">عن الفنانة</p>
      <h1 class="font-display text-4xl sm:text-5xl text-ink-900 mb-9 leading-tight">
        كل لوحة تبدأ<br />من سؤال
      </h1>

      <div class="space-y-6 text-ink-600 leading-loose text-lg">
        <p>
          وديان فنانة سعودية تعمل بالألوان الزيتية والأكريليك على القماش الكتّاني.
          تنشغل أعمالها بالضوء في المكان: كيف يسقط على جدار طيني، وكيف يتغيّر لون
          الرمل بين الظهيرة والغروب.
        </p>
        <p>
          تُرسم كل لوحة يدوياً من البداية إلى النهاية، ولا تُنسخ ولا تُطبع.
          ما تراه في هذا المعرض قطعة واحدة، تُوقَّع على الوجه وتُختم من الخلف
          بتاريخ إنجازها.
        </p>
        <p>
          إلى جانب الأعمال الجاهزة، تستقبل وديان طلبات خاصة: لوحة تُرسم لمساحة
          بعينها وبألوان تختارها أنت. يبدأ الأمر بوصف، ثم حوار، ثم عربون —
          وتتابع مراحل الرسم بالصور حتى تصلك.
        </p>
      </div>

      <section class="mt-16 pt-12 border-t border-ink-100">
        <h2 class="font-display text-2xl text-ink-900 mb-7">كيف تعمل الطلبات الخاصة</h2>
        <ol class="space-y-6">
          @for (step of steps; track step.n) {
            <li class="flex gap-5">
              <span class="font-display text-2xl text-gilt-500 shrink-0 w-8" data-numeric>{{ step.n }}</span>
              <div>
                <p class="text-ink-800 mb-1">{{ step.title }}</p>
                <p class="text-sm text-ink-500 leading-relaxed">{{ step.body }}</p>
              </div>
            </li>
          }
        </ol>

        <a routerLink="/paintings" [queryParams]="{ type: 'reservable' }"
           class="inline-block mt-10 bg-gilt-600 text-canvas-50 px-7 py-3.5 text-sm hover:bg-gilt-700 transition-colors">
          ابدأ طلبك
        </a>
      </section>
    </div>
  `,
})
export class AboutScreen {
  constructor() {
    inject(Seo).apply({
      title: 'عن الفنانة',
      description: 'وديان فنانة سعودية تعمل بالألوان الزيتية والأكريليك. تعرّف على أسلوبها وكيف تعمل الطلبات الخاصة.',
      path: '/about',
    });
  }

  protected readonly steps = [
    { n: '١', title: 'تصف ما تريد', body: 'المقاس والألوان والمكان الذي ستُعلَّق فيه اللوحة. كلما كان الوصف أدقّ كانت النتيجة أقرب لما تتخيّله.' },
    { n: '٢', title: 'تراجع الفنانة الطلب', body: 'تقرأ وصفك وتحدّد السعر النهائي ومدة التنفيذ. لا يُخصم منك شيء في هذه المرحلة.' },
    { n: '٣', title: 'تدفع العربون', body: 'عند موافقتك على السعر تدفع نصف المبلغ تقريباً، فيبدأ العمل مباشرةً.' },
    { n: '٤', title: 'تتابع المراحل', body: 'تصلك صور لمراحل الرسم أولاً بأول، ويمكنك التعليق عليها.' },
    { n: '٥', title: 'تستلم اللوحة', body: 'عند الاكتمال تسدّد المتبقّي، فتُشحن اللوحة مؤطَّرة ومغلَّفة مع شهادة أصالة.' },
  ];
}
