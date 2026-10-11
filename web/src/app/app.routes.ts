import { Routes } from '@angular/router';

import { authGuard, guestGuard } from './core/guards';

const SUFFIX = ' · وديان';

/**
 * مسارات المتجر.
 *
 * كلها كسولة (loadComponent): الصفحة الأولى معرض صور ثقيل، وكل كيلوبايت
 * جافاسكربت يؤجّل ظهور اللوحات.
 *
 * الشاشات المحمية خلف authGuard، وشاشات الدخول خلف guestGuard حتى لا يرى
 * المسجَّل نموذج تسجيل جديد.
 */
export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./layout/shell').then((m) => m.Shell),
    children: [
      {
        path: '',
        pathMatch: 'full',
        title: 'لوحات فنية أصلية' + SUFFIX,
        loadComponent: () => import('./features/home/home').then((m) => m.HomeScreen),
      },
      {
        path: 'paintings',
        title: 'المعرض' + SUFFIX,
        loadComponent: () => import('./features/gallery/gallery').then((m) => m.GalleryScreen),
      },
      {
        path: 'paintings/:slug',
        loadComponent: () => import('./features/painting/painting').then((m) => m.PaintingScreen),
      },
      {
        path: 'about',
        title: 'عن الفنانة' + SUFFIX,
        loadComponent: () => import('./features/about/about').then((m) => m.AboutScreen),
      },
      {
        path: 'cart',
        title: 'السلة' + SUFFIX,
        loadComponent: () => import('./features/cart/cart').then((m) => m.CartScreen),
      },

      /* ------------------------------ الدخول ------------------------------ */
      {
        path: 'login',
        canActivate: [guestGuard],
        title: 'تسجيل الدخول' + SUFFIX,
        loadComponent: () => import('./features/auth/login').then((m) => m.LoginScreen),
      },
      {
        path: 'register',
        canActivate: [guestGuard],
        title: 'حساب جديد' + SUFFIX,
        loadComponent: () => import('./features/auth/register').then((m) => m.RegisterScreen),
      },
      {
        path: 'forgot-password',
        title: 'استعادة كلمة المرور' + SUFFIX,
        loadComponent: () => import('./features/auth/forgot-password').then((m) => m.ForgotPasswordScreen),
      },
      {
        path: 'reset-password',
        title: 'كلمة مرور جديدة' + SUFFIX,
        loadComponent: () => import('./features/auth/reset-password').then((m) => m.ResetPasswordScreen),
      },
      {
        path: 'verify-email',
        title: 'تأكيد البريد' + SUFFIX,
        loadComponent: () => import('./features/auth/verify-email').then((m) => m.VerifyEmailScreen),
      },

      /* ---------------------------- محمية ---------------------------- */
      {
        path: 'checkout',
        canActivate: [authGuard],
        title: 'إتمام الطلب' + SUFFIX,
        loadComponent: () => import('./features/checkout/checkout').then((m) => m.CheckoutScreen),
      },
      {
        path: 'checkout/result',
        canActivate: [authGuard],
        title: 'نتيجة الدفع' + SUFFIX,
        loadComponent: () => import('./features/checkout/result').then((m) => m.CheckoutResultScreen),
      },
      {
        path: 'reserve/:slug',
        canActivate: [authGuard],
        title: 'طلب لوحة خاصة' + SUFFIX,
        loadComponent: () => import('./features/reserve/reserve').then((m) => m.ReserveScreen),
      },
      {
        path: 'account',
        canActivate: [authGuard],
        loadComponent: () => import('./features/account/account-shell').then((m) => m.AccountShell),
        children: [
          {
            path: '',
            pathMatch: 'full',
            title: 'حسابي' + SUFFIX,
            loadComponent: () => import('./features/account/overview').then((m) => m.AccountOverviewScreen),
          },
          {
            path: 'orders',
            title: 'طلباتي' + SUFFIX,
            loadComponent: () => import('./features/account/orders').then((m) => m.AccountOrdersScreen),
          },
          {
            path: 'orders/:orderNumber',
            title: 'تفاصيل الطلب' + SUFFIX,
            loadComponent: () => import('./features/account/order-detail').then((m) => m.OrderDetailScreen),
          },
          {
            path: 'reservations',
            title: 'حجوزاتي' + SUFFIX,
            loadComponent: () => import('./features/account/reservations').then((m) => m.AccountReservationsScreen),
          },
          {
            path: 'reservations/:number',
            title: 'تفاصيل الحجز' + SUFFIX,
            loadComponent: () => import('./features/account/reservation-detail').then((m) => m.ReservationDetailScreen),
          },
          {
            path: 'wishlist',
            title: 'المفضّلة' + SUFFIX,
            loadComponent: () => import('./features/account/wishlist').then((m) => m.AccountWishlistScreen),
          },
          {
            path: 'addresses',
            title: 'العناوين' + SUFFIX,
            loadComponent: () => import('./features/account/addresses').then((m) => m.AccountAddressesScreen),
          },
          {
            path: 'payments',
            title: 'المدفوعات' + SUFFIX,
            loadComponent: () => import('./features/account/payments').then((m) => m.AccountPaymentsScreen),
          },
          {
            path: 'notifications',
            title: 'الإشعارات' + SUFFIX,
            loadComponent: () => import('./features/account/notifications').then((m) => m.AccountNotificationsScreen),
          },
          {
            path: 'profile',
            title: 'بياناتي' + SUFFIX,
            loadComponent: () => import('./features/account/profile').then((m) => m.AccountProfileScreen),
          },
        ],
      },

      {
        path: '**',
        title: 'الصفحة غير موجودة' + SUFFIX,
        loadComponent: () => import('./features/not-found').then((m) => m.NotFoundScreen),
      },
    ],
  },
];
