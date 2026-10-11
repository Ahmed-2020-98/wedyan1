import { PLATFORM_ID, inject } from '@angular/core';
import { isPlatformServer } from '@angular/common';
import { CanActivateFn, Router } from '@angular/router';

import { AuthStore } from './auth.store';

/**
 * حارس لوحة التحكم.
 *
 * لا يكفي أن يكون المستخدم مسجّلاً: الزبون العادي يملك رمز دخول صالحاً
 * تماماً، ولا شأن له بهذه الشاشات. ولذلك يُشترط الدور صراحةً.
 *
 * وهذا حارس واجهة لا حارس أمان: الخادم يتحقق من الدور في كل نقطة
 * (role:admin|artist)، وما هنا يمنع عرض شاشة فارغة لا أكثر.
 */
export const dashboardGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  const router = inject(Router);

  if (isPlatformServer(inject(PLATFORM_ID))) {
    return true;
  }

  if (!auth.isAuthenticated()) {
    return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
  }

  if (auth.hasRole('admin') || auth.hasRole('artist')) {
    return true;
  }

  return router.createUrlTree(['/forbidden']);
};

/** أقسام تخصّ الإدارة وحدها: المستخدمون والكوبونات والمدفوعات والإعدادات. */
export const adminOnlyGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  const router = inject(Router);

  if (isPlatformServer(inject(PLATFORM_ID))) {
    return true;
  }

  return auth.hasRole('admin') ? true : router.createUrlTree(['/forbidden']);
};

export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  const router = inject(Router);

  if (isPlatformServer(inject(PLATFORM_ID))) {
    return true;
  }

  return auth.isAuthenticated() ? router.createUrlTree(['/']) : true;
};
