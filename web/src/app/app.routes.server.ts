import { RenderMode, ServerRoute } from '@angular/ssr';

/**
 * وضع التصيير لكل مسار.
 *
 * الصفحات العامة تُصيَّر على الخادم لتُفهرس وتظهر معاينتها عند المشاركة.
 * أمّا صفحات الحساب والدفع فتُصيَّر في المتصفح وحده، لسببين:
 *
 * ١) رمز الدخول يعيش في localStorage ولا وجود له على الخادم. تصييرها هناك
 *    يعني أن حارس المسار يراها «غير مسجّلة» فيحوّل إلى صفحة الدخول، ثم
 *    يكتشف المتصفح بعد الترطيب أن المستخدم مسجّل — فيقفز بين صفحتين.
 *
 * ٢) لا معنى لفهرسة سلة زبون أو صفحة دفعه أصلاً.
 */
export const serverRoutes: ServerRoute[] = [
  { path: 'login', renderMode: RenderMode.Client },
  { path: 'register', renderMode: RenderMode.Client },
  { path: 'forgot-password', renderMode: RenderMode.Client },
  { path: 'reset-password', renderMode: RenderMode.Client },
  { path: 'verify-email', renderMode: RenderMode.Client },
  { path: 'cart', renderMode: RenderMode.Client },
  { path: 'checkout', renderMode: RenderMode.Client },
  { path: 'checkout/**', renderMode: RenderMode.Client },
  { path: 'reserve/**', renderMode: RenderMode.Client },
  { path: 'account', renderMode: RenderMode.Client },
  { path: 'account/**', renderMode: RenderMode.Client },

  /*
   | Server لا Prerender: صفحات اللوحات والتصنيفات تأتي من قاعدة البيانات
   | وتتغيّر كلما أضاف الفنان عملاً أو بيعت لوحة، فتوليدها وقت البناء يعني
   | صفحات قديمة تعرض «متاحة» للوحة مباعة حتى النشر التالي.
   */
  { path: '**', renderMode: RenderMode.Server },
];
