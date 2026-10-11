import { DOCUMENT, Injectable, inject } from '@angular/core';
import { Meta, Title } from '@angular/platform-browser';

import { environment } from '../../environments/environment';

export interface SeoData {
  title: string;
  description?: string | null;
  image?: string | null;
  path?: string;
  type?: 'website' | 'product' | 'article';
}

/**
 * وسوم الوصف والمشاركة والبيانات المنظّمة.
 *
 * تُستدعى أثناء التصيير على الخادم، فتصل الوسوم إلى زاحف محرّك البحث وإلى
 * معاينة واتساب داخل HTML نفسه لا بعد تشغيل جافاسكربت — وهو الفرق بين
 * صفحة تُفهرس وأخرى تظهر بعنوان فارغ.
 */
@Injectable({ providedIn: 'root' })
export class Seo {
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  private readonly document = inject(DOCUMENT);

  private readonly siteName = 'وديان';

  apply(data: SeoData): void {
    const fullTitle = data.title.includes(this.siteName)
      ? data.title
      : `${data.title} · ${this.siteName}`;

    const url = environment.siteUrl + (data.path ?? '');
    const description = data.description ?? 'معرض إلكتروني للوحات فنية أصلية.';

    this.title.setTitle(fullTitle);

    this.setTag('name', 'description', description);
    this.setTag('property', 'og:title', fullTitle);
    this.setTag('property', 'og:description', description);
    this.setTag('property', 'og:type', data.type ?? 'website');
    this.setTag('property', 'og:url', url);
    this.setTag('property', 'og:site_name', this.siteName);
    this.setTag('property', 'og:locale', 'ar_SA');
    this.setTag('name', 'twitter:card', data.image ? 'summary_large_image' : 'summary');
    this.setTag('name', 'twitter:title', fullTitle);
    this.setTag('name', 'twitter:description', description);

    if (data.image) {
      this.setTag('property', 'og:image', data.image);
      this.setTag('name', 'twitter:image', data.image);
    }

    this.setCanonical(url);
  }

  /**
   * يحقن JSON-LD.
   *
   * نستبدل الوسم بدل إضافة آخر: التنقّل بين لوحتين كان سيترك مخططين
   * متناقضين في الصفحة نفسها.
   */
  setJsonLd(id: string, schema: Record<string, unknown> | null): void {
    const elementId = `ld-${id}`;
    this.document.getElementById(elementId)?.remove();

    if (schema === null) return;

    const script = this.document.createElement('script');
    script.id = elementId;
    script.type = 'application/ld+json';
    script.textContent = JSON.stringify(schema);
    this.document.head.appendChild(script);
  }

  private setTag(attr: 'name' | 'property', key: string, value: string): void {
    this.meta.updateTag({ [attr]: key, content: value }, `${attr}='${key}'`);
  }

  private setCanonical(url: string): void {
    let link = this.document.querySelector<HTMLLinkElement>("link[rel='canonical']");

    if (!link) {
      link = this.document.createElement('link');
      link.setAttribute('rel', 'canonical');
      this.document.head.appendChild(link);
    }

    link.setAttribute('href', url);
  }
}
