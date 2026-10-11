import { ArDatePipe, DimensionsPipe, MoneyPipe, SaudiPhonePipe } from './pipes';

/**
 * أنابيب العرض.
 *
 * الأرقام اللاتينية والتقويم الميلادي قرار مقصود لا صدفة — انظر التعليق في
 * pipes.ts — ولذلك يُثبَّت هنا: أي انزلاق إلى الأرقام الهندية يكسر نسخ
 * أرقام الطلبات ولصقها في تتبّع الشحنة.
 */
describe('أنابيب العرض العربية', () => {
  describe('MoneyPipe', () => {
    const pipe = new MoneyPipe();

    it('يعرض النص المنسّق القادم من الخادم كما هو', () => {
      expect(pipe.transform({ amount: 1250.5, currency: 'SAR', formatted: 'ر.س.‏ 1,250.50' }))
        .toBe('ر.س.‏ 1,250.50');
    });

    it('ينسّق العدد المجرّد بأرقام لاتينية', () => {
      const result = pipe.transform(1250.5);

      expect(result).toContain('1,250.50');
      expect(result).not.toMatch(/[٠-٩]/);
    });

    it('يعرض شرطة بدل الفراغ حين لا قيمة', () => {
      expect(pipe.transform(null)).toBe('—');
      expect(pipe.transform(undefined)).toBe('—');
    });
  });

  describe('DimensionsPipe', () => {
    const pipe = new DimensionsPipe();

    it('يكتب المقاس عرضاً × ارتفاعاً بالوحدة العربية', () => {
      expect(pipe.transform({ width: 60, height: 90, unit: 'cm' })).toBe('60 × 90 سم');
    });

    it('يضيف العمق حين يوجد', () => {
      expect(pipe.transform({ width: 60, height: 90, depth: 2, unit: 'cm' })).toBe('60 × 90 × 2 سم');
    });

    it('يترجم البوصة والمتر', () => {
      expect(pipe.transform({ width: 24, height: 36, unit: 'inch' })).toBe('24 × 36 بوصة');
      expect(pipe.transform({ width: 1, height: 2, unit: 'm' })).toBe('1 × 2 م');
    });
  });

  describe('ArDatePipe', () => {
    const pipe = new ArDatePipe();

    it('يستخدم التقويم الميلادي والأرقام اللاتينية', () => {
      const result = pipe.transform('2026-08-19T12:00:00Z');

      expect(result).toMatch(/2026/);
      expect(result).not.toMatch(/[٠-٩]/);
      // لو استُخدم التقويم الهجري لظهرت السنة ١٤٤٨ لا ٢٠٢٦
      expect(result).not.toMatch(/144\d/);
    });
  });

  describe('SaudiPhonePipe', () => {
    const pipe = new SaudiPhonePipe();

    it('يحوّل الصيغة الدولية إلى محلية', () => {
      expect(pipe.transform('966501234567')).toBe('0501234567');
      expect(pipe.transform('+966501234567')).toBe('0501234567');
    });

    it('يُبقي الصيغة المحلية كما هي', () => {
      expect(pipe.transform('0501234567')).toBe('0501234567');
    });
  });
});
