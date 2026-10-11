import { Routes } from '@angular/router';

import { adminOnlyGuard, dashboardGuard, guestGuard } from './core/guards';

const SUFFIX = ' · لوحة وديان';

/**
 * مسارات لوحة التحكم.
 *
 * اللوحة واحدة للفنانة وللإدارة، والفصل بحارس أدوار على المسار لا بتطبيقين:
 * الشاشتان تتشاركان الجداول والرسوم والتخطيط، وفصلهما كان سيعني نسخ ذلك
 * كلّه مرتين. الأقسام الإدارية وحدها خلف adminOnlyGuard.
 */
export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    title: 'تسجيل الدخول' + SUFFIX,
    loadComponent: () => import('./features/login').then((m) => m.LoginScreen),
  },
  {
    path: 'forbidden',
    title: 'لا صلاحية' + SUFFIX,
    loadComponent: () => import('./features/forbidden').then((m) => m.ForbiddenScreen),
  },
  {
    path: '',
    canActivate: [dashboardGuard],
    loadComponent: () => import('./layout/shell').then((m) => m.Shell),
    children: [
      {
        path: '',
        pathMatch: 'full',
        title: 'نظرة عامة' + SUFFIX,
        loadComponent: () => import('./features/dashboard').then((m) => m.DashboardScreen),
      },

      /* ------------------------------ اللوحات ------------------------------ */
      {
        path: 'paintings',
        title: 'اللوحات' + SUFFIX,
        loadComponent: () => import('./features/paintings').then((m) => m.ManagePaintingsScreen),
      },
      {
        path: 'paintings/new',
        title: 'لوحة جديدة' + SUFFIX,
        loadComponent: () => import('./features/painting-form').then((m) => m.PaintingFormScreen),
      },
      {
        path: 'paintings/:id',
        title: 'تعديل لوحة' + SUFFIX,
        loadComponent: () => import('./features/painting-form').then((m) => m.PaintingFormScreen),
      },

      /* ----------------------------- الحجوزات ----------------------------- */
      {
        path: 'reservations',
        title: 'الحجوزات' + SUFFIX,
        loadComponent: () => import('./features/reservations').then((m) => m.ManageReservationsScreen),
      },
      {
        path: 'reservations/:number',
        title: 'تفاصيل الحجز' + SUFFIX,
        loadComponent: () => import('./features/reservation-detail').then((m) => m.ManageReservationDetailScreen),
      },

      /* ------------------------------ الطلبات ------------------------------ */
      {
        path: 'orders',
        title: 'الطلبات' + SUFFIX,
        loadComponent: () => import('./features/orders').then((m) => m.ManageOrdersScreen),
      },
      {
        path: 'orders/:orderNumber',
        title: 'تفاصيل الطلب' + SUFFIX,
        loadComponent: () => import('./features/order-detail').then((m) => m.ManageOrderDetailScreen),
      },

      {
        path: 'categories',
        title: 'التصنيفات' + SUFFIX,
        loadComponent: () => import('./features/categories').then((m) => m.ManageCategoriesScreen),
      },
      {
        path: 'reviews',
        title: 'المراجعات' + SUFFIX,
        loadComponent: () => import('./features/reviews').then((m) => m.ManageReviewsScreen),
      },

      /* ---------------------------- الإدارة فقط ---------------------------- */
      {
        path: 'users',
        canActivate: [adminOnlyGuard],
        title: 'المستخدمون' + SUFFIX,
        loadComponent: () => import('./features/users').then((m) => m.ManageUsersScreen),
      },
      {
        path: 'coupons',
        canActivate: [adminOnlyGuard],
        title: 'الكوبونات' + SUFFIX,
        loadComponent: () => import('./features/coupons').then((m) => m.ManageCouponsScreen),
      },
      {
        path: 'payments',
        canActivate: [adminOnlyGuard],
        title: 'المدفوعات' + SUFFIX,
        loadComponent: () => import('./features/payments').then((m) => m.ManagePaymentsScreen),
      },
      {
        path: 'shipping',
        canActivate: [adminOnlyGuard],
        title: 'الشحن' + SUFFIX,
        loadComponent: () => import('./features/shipping').then((m) => m.ManageShippingScreen),
      },
      {
        path: 'settings',
        canActivate: [adminOnlyGuard],
        title: 'الإعدادات' + SUFFIX,
        loadComponent: () => import('./features/settings').then((m) => m.ManageSettingsScreen),
      },

      { path: '**', redirectTo: '' },
    ],
  },
];
