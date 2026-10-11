import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, LOCALE_ID, inject, provideAppInitializer, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter, withComponentInputBinding, withInMemoryScrolling } from '@angular/router';

import { routes } from './app.routes';
import { authInterceptor } from './core/auth.interceptor';
import { AuthStore } from './core/auth.store';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),

    provideRouter(
      routes,
      withComponentInputBinding(),
      withInMemoryScrolling({ scrollPositionRestoration: 'top' }),
    ),

    provideHttpClient(withInterceptors([authInterceptor])),

    { provide: LOCALE_ID, useValue: 'ar-SA' },

    /*
     | التحقق من الجلسة بعد اكتمال بناء الحاقن لا داخل بانِي AuthStore،
     | تفادياً للاعتماد الدائري مع اعتراض المصادقة.
     */
    provideAppInitializer(() => inject(AuthStore).restoreSession()),
  ],
};
