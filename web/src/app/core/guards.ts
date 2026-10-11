import { PLATFORM_ID, inject } from '@angular/core';
import { isPlatformServer } from '@angular/common';
import { CanActivateFn, Router } from '@angular/router';

import { AuthStore } from './auth.store';

/**
 * يحمي مسارات الحساب.
 *
 * يحفظ الوجهة في returnUrl فيعود المستخدم إلى ما كان يريده بعد الدخول،
 * بدل إلقائه في الصفحة الرئيسية.
 */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthStore);
  const router = inject(Router);

  /*
   | على الخادم لا وجود لـ localStorage، فالحارس لا يستطيع معرفة حالة
   | الجلسة. نمرّر القرار إلى المتصفح بدل رفض كل زائر مسجّل.
   | المسارات المحمية مضبوطة أصلاً على التصيير في المتصفح — انظر
   | app.routes.server.ts — وهذا حارس احتياطي لا أكثر.
   */
  if (isPlatformServer(inject(PLATFORM_ID))) {
    return true;
  }

  if (auth.isAuthenticated()) {
    return true;
  }

  return router.createUrlTree(['/login'], {
    queryParams: { returnUrl: state.url },
  });
};

/** يمنع دخول المسجَّل إلى صفحات الدخول والتسجيل. */
export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthStore);
  const router = inject(Router);

  if (isPlatformServer(inject(PLATFORM_ID))) {
    return true;
  }

  return auth.isAuthenticated() ? router.createUrlTree(['/account']) : true;
};
