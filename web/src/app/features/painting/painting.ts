import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { firstValueFrom } from 'rxjs';

import { Api } from '../../core/api';
import { AuthStore } from '../../core/auth.store';
import { CartStore } from '../../core/cart.store';
import type { Paginated, Painting, PaintingDetail, Resource, Review } from '../../core/models';
import { Seo } from '../../core/seo.service';
import { environment } from '../../../environments/environment';
import { PaintingCard } from '../../shared/painting-card';
import { AR_PIPES } from '../../shared/pipes';
import { Spinner } from '../../shared/spinner';
import { StarRating } from '../../shared/star-rating';

@Component({
  selector: 'app-painting',
  imports: [RouterLink, PaintingCard, Spinner, StarRating, ...AR_PIPES],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './painting.html',
})
export class PaintingScreen {
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly seo = inject(Seo);
  protected readonly auth = inject(AuthStore);
  protected readonly cart = inject(CartStore);

  /**
   * معامل المسار. input() لا signal(): withComponentInputBinding يربط
   * معاملات المسار بالمدخلات فقط، وإشارة عادية تبقى فارغة أبداً — فلا
   * تُصيَّر الصفحة على الخادم ولا تصل وسوم الوصف إلى الزاحف.
   */
  readonly slug = input.required<string>();

  protected readonly activeImage = signal(0);
  protected readonly zoomed = signal(false);
  protected readonly adding = signal(false);
  protected readonly added = signal(false);
  protected readonly wishlisted = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly painting = resource({
    params: () => this.slug(),
    loader: async ({ params }) => {
      const res = await firstValueFrom(
        this.api.get<Resource<PaintingDetail>>(`/paintings/${encodeURIComponent(params)}`),
      );
      const p = res.data;

      this.activeImage.set(0);
      this.applySeo(p);

      return p;
    },
  });

  protected readonly related = resource({
    params: () => this.slug(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<{ data: Painting[] }>(`/paintings/${encodeURIComponent(params)}/related`)),
  });

  protected readonly reviews = resource({
    params: () => this.slug(),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<Paginated<Review>>(`/paintings/${encodeURIComponent(params)}/reviews`)),
  });

  protected readonly currentImage = computed(() => {
    const p = this.painting.value();
    return p?.images?.[this.activeImage()] ?? p?.mainImage ?? null;
  });

  /** العربون المتوقّع — يُعرض قبل الطلب حتى لا يفاجأ الزبون بالمبلغ. */
  protected readonly estimatedDeposit = computed(() => {
    const p = this.painting.value();
    if (!p?.reservation) return null;

    return (p.effectivePrice.amount * p.reservation.depositPercentage) / 100;
  });

  protected addToCart(): void {
    const p = this.painting.value();
    if (!p) return;

    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/login'], { queryParams: { returnUrl: `/paintings/${p.slug}` } });
      return;
    }

    this.adding.set(true);
    this.error.set(null);

    this.cart.add(p.id).subscribe({
      next: () => {
        this.adding.set(false);
        this.added.set(true);
        setTimeout(() => this.added.set(false), 2500);
      },
      error: (err) => {
        this.adding.set(false);
        this.error.set(err?.error?.message ?? 'تعذّرت الإضافة إلى السلة.');
      },
    });
  }

  protected buyNow(): void {
    const p = this.painting.value();
    if (!p) return;

    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/login'], { queryParams: { returnUrl: `/paintings/${p.slug}` } });
      return;
    }

    this.adding.set(true);
    this.cart.add(p.id).subscribe({
      next: () => this.router.navigateByUrl('/checkout'),
      error: (err) => {
        this.adding.set(false);
        this.error.set(err?.error?.message ?? 'تعذّرت الإضافة إلى السلة.');
      },
    });
  }

  protected reserve(): void {
    const p = this.painting.value();
    if (!p) return;

    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/login'], { queryParams: { returnUrl: `/reserve/${p.slug}` } });
      return;
    }

    this.router.navigate(['/reserve', p.slug]);
  }

  protected toggleWishlist(): void {
    const p = this.painting.value();
    if (!p) return;

    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/login'], { queryParams: { returnUrl: `/paintings/${p.slug}` } });
      return;
    }

    if (this.wishlisted()) {
      this.api.delete(`/wishlist/${p.id}`).subscribe(() => this.wishlisted.set(false));
    } else {
      this.api.post('/wishlist', { painting_id: p.id }).subscribe(() => this.wishlisted.set(true));
    }
  }

  /**
   * وسوم الوصف ومخطّط المنتج.
   *
   * يُنفَّذ أثناء التصيير على الخادم، فيصل المخطّط إلى جوجل داخل HTML —
   * وهو ما يجعل السعر والتوفّر يظهران في نتيجة البحث نفسها.
   */
  private applySeo(p: PaintingDetail): void {
    const url = `${environment.siteUrl}/paintings/${p.slug}`;

    this.seo.apply({
      title: p.seo.title,
      description: p.seo.description ?? p.shortDescription,
      image: p.images?.[0]?.large ?? p.mainImage?.large ?? null,
      path: `/paintings/${p.slug}`,
      type: 'product',
    });

    this.seo.setJsonLd('product', {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: p.title,
      description: p.shortDescription ?? p.description,
      sku: p.sku,
      image: p.images?.map((i) => i.large) ?? [],
      brand: { '@type': 'Brand', name: 'وديان' },
      ...(p.artist?.name ? { creator: { '@type': 'Person', name: p.artist.name } } : {}),
      ...(p.material ? { material: p.material } : {}),
      ...(p.dimensions
        ? { size: `${p.dimensions.width} × ${p.dimensions.height} ${p.dimensions.unit}` }
        : {}),
      offers: {
        '@type': 'Offer',
        url,
        priceCurrency: p.effectivePrice.currency,
        price: p.effectivePrice.amount,
        availability: p.isPurchasable
          ? 'https://schema.org/InStock'
          : p.isReservable
            ? 'https://schema.org/PreOrder'
            : 'https://schema.org/SoldOut',
      },
      ...(p.rating.count > 0
        ? {
            aggregateRating: {
              '@type': 'AggregateRating',
              ratingValue: p.rating.average,
              reviewCount: p.rating.count,
            },
          }
        : {}),
    });

    this.seo.setJsonLd('breadcrumb', {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: 'الرئيسية', item: environment.siteUrl },
        { '@type': 'ListItem', position: 2, name: 'المعرض', item: `${environment.siteUrl}/paintings` },
        ...(p.category
          ? [{ '@type': 'ListItem', position: 3, name: p.category.name, item: `${environment.siteUrl}/paintings?category=${p.category.slug}` }]
          : []),
        { '@type': 'ListItem', position: p.category ? 4 : 3, name: p.title, item: url },
      ],
    });
  }
}
