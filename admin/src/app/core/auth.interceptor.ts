import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';

import { AuthStore } from './auth.store';

/**
 * يرفق رمز الدخول ويتعامل مع انتهاء صلاحيته.
 *
 * الرمز يُقرأ من المخزن لا من التخزين المحلي مباشرةً، فيعمل الاعتراض أثناء
 * التصيير على الخادم أيضاً حيث لا وجود لذلك التخزين.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthStore);
  const token = auth.token();

  const request = token
    ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : req;

  return next(request).pipe(
    catchError((error: HttpErrorResponse) => {
      /*
       | 401 تعني رمزاً منتهياً أو مُلغى، فنمسح الجلسة — لكن **بلا تحويل**
       | إلى صفحة الدخول.
       |
       | التحويل من هنا يخطف الزائر من صفحة عامة: يفتح لوحة فنية برمز قديم
       | في متصفحه، فيفشل التحقق في الخلفية ويجد نفسه في شاشة تسجيل دخول
       | لم يطلبها. حراس المسارات هي ما يحوّل، وهي وحدها تعرف إن كانت
       | الصفحة تحتاج جلسة أصلاً.
       */
      if (error.status === 401 && auth.isAuthenticated()) {
        auth.clearSession();
      }

      return throwError(() => error);
    }),
  );
};
