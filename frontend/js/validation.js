/** validation.js — اعتبارسنجی اولیه سمت کاربر (اعتبارسنجی اصلی در PHP انجام می‌شود) */
function validNationalId(v) {
  if (!/^\d{10}$/.test(v) || /^(\d)\1{9}$/.test(v)) return false;
  let s = 0;
  for (let i = 0; i < 9; i++) s += (+v[i]) * (10 - i);
  const r = s % 11;
  return r < 2 ? +v[9] === r : +v[9] === 11 - r;
}
const validPhone = v => /^(\+98|0)?9\d{9}$|^0\d{10}$/.test(v);
const validDate = v => /^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/.test(v);

/** تبدیل ارقام فارسی/عربی به انگلیسی (کاربر ایرانی معمولاً با کیبورد فارسی تایپ می‌کند) */
function faToEn(s) {
  return String(s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
}

/** خطای یک پاسخ بر اساس نوع سؤال؛ در صورت معتبر بودن رشته‌ی خالی */
function fieldError(x, v) {
  if (x.required && !v) return `«${x.label}» را وارد کنید`;
  if (!v) return '';
  if (x.type === 'national_id' && !validNationalId(v)) return 'کد ملی معتبر نیست';
  if (x.type === 'number' && isNaN(+v)) return `«${x.label}» باید عدد باشد`;
  if (x.type === 'phone' && !validPhone(v)) return `شماره‌ی «${x.label}» معتبر نیست`;
  if (x.type === 'date' && !validDate(v)) return `تاریخ «${x.label}» معتبر نیست (مثال: 1403/05/20)`;
  return '';
}
