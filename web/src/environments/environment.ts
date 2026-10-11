/**
 * إعدادات الإنتاج. تُستبدل وقت البناء بـ environment.development.ts في وضع
 * التطوير — انظر fileReplacements في angular.json.
 *
 * لا يوضع هنا أي مفتاح سرّي: كل ما في هذا الملف يُشحن داخل حزمة المتصفح.
 */
export const environment = {
  production: true,
  apiUrl: '/api/v1',
  siteUrl: 'https://wedyan.sa',
};
