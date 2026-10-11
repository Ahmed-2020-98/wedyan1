import { isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';

import { Api } from './api';
import { TokenStorage } from './token-storage';
import type { AuthPayload, Resource, User } from './models';

/*
 | مفتاح مختلف عن مفتاح المتجر: التطبيقان يعملان على localhost نفسه أثناء
 | التطوير ويتشاركان مخزنه، فمفتاح واحد يعني أن تسجيل الخروج من أحدهما
 | يُخرجك من الآخر.
 */
const TOKEN_KEY = 'wedyan.admin.token';

/**
 * حالة الجلسة.
 *
 * إشارات لا RxJS للحالة نفسها: القالب يقرأ user() مباشرةً بلا async pipe
 * وبلا اشتراك يحتاج إلغاءً.
 *
 * الرمز محفوظ عبر TokenStorage لا عبر localStorage مباشرةً — انظر تعليق ذلك الصنف.
 */
@Injectable({ providedIn: 'root' })
export class AuthStore {
  private readonly api = inject(Api);
  private readonly router = inject(Router);
  private readonly isBrowser = isPlatformBrowser(inject(PLATFORM_ID));
  private readonly storage = inject(TokenStorage);

  private readonly _user = signal<User | null>(null);
  private readonly _token = signal<string | null>(null);
  private readonly _ready = signal(false);

  readonly user = this._user.asReadonly();
  readonly token = this._token.asReadonly();
  readonly ready = this._ready.asReadonly();

  readonly isAuthenticated = computed(() => this._token() !== null);
  readonly isVerified = computed(() => this._user()?.emailVerified ?? false);

  constructor() {
    /*
     | استرجاع الرمز فقط — بلا أي طلب شبكة هنا.
     |
     | استدعاء HttpClient من داخل هذا البانِي يُنتج اعتماداً دائرياً:
     | الطلب يمرّ باعتراض المصادقة، والاعتراض يحقن AuthStore وهو ما يزال
     | قيد الإنشاء. النتيجة طلب بلا ترويسة تفويض، فـ401، فمسح الرمز —
     | أي خروج المستخدم من حسابه في كل مرّة يحدّث فيها الصفحة.
     |
     | التحقق من صلاحية الرمز يقع في restoreSession() التي يستدعيها
     | provideAppInitializer بعد اكتمال بناء الحاقن.
     */
    if (this.isBrowser) {
      this._token.set(this.storage.read(TOKEN_KEY));
    }
  }

  /**
   * يتحقق من الرمز المستعاد لدى الخادم.
   *
   * لا يُمسح الرمز إلا عند رفض صريح (401/403): انقطاع شبكة عابر يجب ألّا
   * يُخرج المستخدم من حسابه.
   */
  restoreSession(): Promise<void> {
    if (!this.isBrowser || this._token() === null) {
      this._ready.set(true);

      return Promise.resolve();
    }

    return new Promise((resolve) => {
      this.refresh().subscribe({
        next: () => {
          this._ready.set(true);
          resolve();
        },
        error: (err: { status?: number }) => {
          if (err?.status === 401 || err?.status === 403) {
            this.clearSession();
          } else {
            this._ready.set(true);
          }

          resolve();
        },
      });
    });
  }

  login(email: string, password: string): Observable<Resource<AuthPayload>> {
    return this.api
      .post<Resource<AuthPayload>>('/auth/login', { email, password })
      .pipe(tap((res) => this.accept(res.data)));
  }

  register(payload: {
    name: string; email: string; phone: string;
    password: string; password_confirmation: string;
  }): Observable<Resource<AuthPayload>> {
    return this.api
      .post<Resource<AuthPayload>>('/auth/register', payload)
      .pipe(tap((res) => this.accept(res.data)));
  }

  refresh(): Observable<Resource<User>> {
    return this.api
      .get<Resource<User>>('/auth/me')
      .pipe(tap((res) => this._user.set(res.data)));
  }

  logout(redirectTo = '/'): void {
    // نمسح محلياً أولاً ثم نُبلغ الخادم: لو فشل الطلب (شبكة مقطوعة) يجب
    // ألّا يبقى المستخدم مسجّلاً في متصفحه.
    const hadToken = this._token() !== null;
    this.clearSession();

    if (hadToken) {
      this.api.post('/auth/logout').subscribe({ error: () => void 0 });
    }

    this.router.navigateByUrl(redirectTo);
  }

  hasRole(role: string): boolean {
    return this._user()?.roles.includes(role) ?? false;
  }

  private accept(payload: AuthPayload): void {
    this._token.set(payload.token);
    this._user.set(payload.user);
    this._ready.set(true);

    if (this.isBrowser) {
      this.storage.write(TOKEN_KEY, payload.token);
    }
  }

  /** يمسح الجلسة محلياً بلا تحويل — يستدعيها الاعتراض عند 401. */
  clearSession(): void {
    this._token.set(null);
    this._user.set(null);
    this._ready.set(true);

    if (this.isBrowser) {
      this.storage.remove(TOKEN_KEY);
    }
  }
}
