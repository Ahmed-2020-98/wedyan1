import { provideHttpClient, withFetch, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, LOCALE_ID, inject, provideAppInitializer, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideClientHydration, withEventReplay } from '@angular/platform-browser';
import { provideRouter, withComponentInputBinding, withInMemoryScrolling } from '@angular/router';

import { routes } from './app.routes';
import { authInterceptor } from './core/auth.interceptor';
import { AuthStore } from './core/auth.store';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),

    provideRouter(
      routes,
      // ربط معاملات المسار بمدخلات المكوّن مباشرةً — يغني عن حقن
      // ActivatedRoute في كل شاشة.
      withComponentInputBinding(),
      withInMemoryScrolling({ scrollPositionRestoration: 'top', anchorScrolling: 'enabled' }),
    ),

    // withFetch شرط لعمل التصيير على الخادم: XHR غير موجود في Node.
    provideHttpClient(withFetch(), withInterceptors([authInterceptor])),

    // withEventReplay يحتفظ بنقرات الزائر قبل اكتمال الترطيب ثم يعيد
    // تشغيلها — يمنع ضياع أول نقرة على «أضف إلى السلة».
    provideClientHydration(withEventReplay()),

    // التقويم ميلادي والأرقام لاتينية — انظر التعليق في styles.css
    { provide: LOCALE_ID, useValue: 'ar-SA' },

    /*
     | التحقق من الجلسة بعد اكتمال بناء الحاقن لا داخل بانِي AuthStore،
     | تفادياً للاعتماد الدائري مع اعتراض المصادقة — انظر التعليق هناك.
     */
    provideAppInitializer(() => inject(AuthStore).restoreSession()),
  ],
};
