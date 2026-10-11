import { Injectable } from '@angular/core';

/**
 * تخزين رمز الجلسة.
 *
 * غلاف رقيق حول localStorage، وليس ترفاً:
 *
 * ١) على الخادم (SSR) لا وجود لـ localStorage إطلاقاً.
 * ٢) سفاري في التصفّح الخاص يرمي QuotaExceededError عند الكتابة، فيسقط
 *    التطبيق كلّه عند تسجيل الدخول لا عند حفظ الرمز فقط.
 * ٣) بعض المتصفحات ترفض الوصول حين تُعطَّل كعكات الطرف الثالث.
 *
 * الفشل هنا يعني «جلسة لهذه الصفحة فقط» لا شاشة بيضاء.
 */
@Injectable({ providedIn: 'root' })
export class TokenStorage {
  private memory: string | null = null;

  private get store(): Storage | null {
    try {
      return typeof localStorage === 'undefined' ? null : localStorage;
    } catch {
      return null;
    }
  }

  read(key: string): string | null {
    try {
      return this.store?.getItem(key) ?? this.memory;
    } catch {
      return this.memory;
    }
  }

  write(key: string, value: string): void {
    this.memory = value;

    try {
      this.store?.setItem(key, value);
    } catch {
      // نبقي القيمة في الذاكرة — الجلسة تعمل حتى تُغلق الصفحة.
    }
  }

  remove(key: string): void {
    this.memory = null;

    try {
      this.store?.removeItem(key);
    } catch {
      // لا شيء نفعله؛ القيمة مُزالة من الذاكرة على أي حال.
    }
  }
}
