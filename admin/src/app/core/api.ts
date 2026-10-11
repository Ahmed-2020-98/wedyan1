import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

/**
 * نقطة الوصول الوحيدة إلى الواجهة البرمجية.
 *
 * كل استدعاء في التطبيق يمرّ من هنا لا عبر HttpClient مباشرةً، حتى يبقى
 * عنوان الخادم وشكل الغلاف ({ data: ... }) معرّفين في مكان واحد؛ فتغيير
 * الإصدار من v1 إلى v2 لاحقاً يمسّ هذا الملف وحده.
 */
@Injectable({ providedIn: 'root' })
export class Api {
  private readonly http = inject(HttpClient);
  private readonly base = environment.apiUrl;

  get<T>(path: string, params?: Record<string, string | number | boolean | undefined>): Observable<T> {
    return this.http.get<T>(`${this.base}${path}`, { params: toParams(params) });
  }

  post<T>(path: string, body?: unknown): Observable<T> {
    return this.http.post<T>(`${this.base}${path}`, body ?? {});
  }

  put<T>(path: string, body?: unknown): Observable<T> {
    return this.http.put<T>(`${this.base}${path}`, body ?? {});
  }

  patch<T>(path: string, body?: unknown): Observable<T> {
    return this.http.patch<T>(`${this.base}${path}`, body ?? {});
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(`${this.base}${path}`);
  }
}

/**
 * يحذف المفاتيح غير المعرّفة بدل إرسال "undefined" نصاً إلى الخادم —
 * وهو خطأ شائع يُفسد الفلاتر لأن الخادم يراها قيمة لا غياباً.
 */
function toParams(params?: Record<string, string | number | boolean | undefined>): HttpParams {
  let httpParams = new HttpParams();
  if (!params) return httpParams;

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      httpParams = httpParams.set(key, String(value));
    }
  }
  return httpParams;
}
