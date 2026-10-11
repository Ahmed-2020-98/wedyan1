import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { AuthStore } from './auth.store';
import { TokenStorage } from './token-storage';

describe('AuthStore', () => {
  let store: AuthStore;
  let http: HttpTestingController;
  let storage: TokenStorage;

  /*
   | نقرأ الرمز عبر TokenStorage لا عبر localStorage مباشرةً.
   | بيئة الاختبار (jsdom) لا توفّر localStorage، والمخزن نفسه يسقط إلى
   | الذاكرة عند غيابه — وهو السلوك المطلوب في سفاري الخاص أيضاً.
   */
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });

    storage = TestBed.inject(TokenStorage);
    storage.remove('wedyan.token');

    store = TestBed.inject(AuthStore);
    http = TestBed.inject(HttpTestingController);
  });

  it('يبدأ بلا جلسة', () => {
    expect(store.isAuthenticated()).toBe(false);
    expect(store.user()).toBeNull();
  });

  it('يحفظ الرمز والمستخدم بعد الدخول', () => {
    store.login('sara@example.com', 'password').subscribe();

    http.expectOne((r) => r.url.endsWith('/auth/login')).flush({
      data: {
        user: { id: 1, name: 'سارة', email: 'sara@example.com', roles: ['customer'] },
        token: 'token-123',
      },
    });

    expect(store.isAuthenticated()).toBe(true);
    expect(store.user()?.name).toBe('سارة');
    expect(storage.read('wedyan.token')).toBe('token-123');
  });

  it('يميّز الأدوار', () => {
    store.login('a@b.c', 'x').subscribe();
    http.expectOne((r) => r.url.endsWith('/auth/login')).flush({
      data: { user: { id: 1, name: 'x', roles: ['artist'] }, token: 't' },
    });

    expect(store.hasRole('artist')).toBe(true);
    expect(store.hasRole('admin')).toBe(false);
  });

  /**
   * البانِي لا يُصدر أي طلب.
   *
   * إصداره منه يُنتج اعتماداً دائرياً مع اعتراض المصادقة، فيخرج الطلب بلا
   * ترويسة تفويض ويُمسح الرمز — أي خروج المستخدم عند كل تحديث للصفحة.
   */
  it('لا يُصدر أي طلب أثناء الإنشاء', () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    TestBed.inject(TokenStorage).write('wedyan.token', 'stored-token');

    const fresh = TestBed.inject(AuthStore);
    const freshHttp = TestBed.inject(HttpTestingController);

    expect(fresh.token()).toBe('stored-token');
    freshHttp.expectNone(() => true);
  });

  it('يمسح الجلسة عند رفض الخادم للرمز المستعاد', async () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    TestBed.inject(TokenStorage).write('wedyan.token', 'stale-token');

    const fresh = TestBed.inject(AuthStore);
    const freshHttp = TestBed.inject(HttpTestingController);

    const restored = fresh.restoreSession();
    freshHttp.expectOne((r) => r.url.endsWith('/auth/me')).flush(null, { status: 401, statusText: 'Unauthorized' });
    await restored;

    expect(fresh.isAuthenticated()).toBe(false);
    expect(TestBed.inject(TokenStorage).read('wedyan.token')).toBeNull();
  });

  /** انقطاع شبكة عابر ليس سبباً لإخراج المستخدم من حسابه. */
  it('يُبقي الجلسة عند خطأ شبكة عابر', async () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    TestBed.inject(TokenStorage).write('wedyan.token', 'good-token');

    const fresh = TestBed.inject(AuthStore);
    const freshHttp = TestBed.inject(HttpTestingController);

    const restored = fresh.restoreSession();
    freshHttp.expectOne((r) => r.url.endsWith('/auth/me')).flush(null, { status: 0, statusText: 'Network error' });
    await restored;

    expect(fresh.isAuthenticated()).toBe(true);
    expect(TestBed.inject(TokenStorage).read('wedyan.token')).toBe('good-token');
  });
});
