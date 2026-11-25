/** register-success.js — پیام موفقیت و کد پیگیری (بدون پارامتر در آدرس نهایی) */
document.addEventListener('DOMContentLoaded', () => {
  let id = qs('id');
  try { id = id || sessionStorage.getItem('reg_id'); sessionStorage.removeItem('reg_id'); } catch (e) {}
  byId('id').textContent = id || '—';
  let t = '';
  try { t = sessionStorage.getItem('reg_track') || ''; sessionStorage.removeItem('reg_track'); } catch (e) {}
  const box = byId('trk');
  if (box && t) { // ثبت‌نام مهمان: کد پیگیری محرمانه برای اتصال بعدی به حساب
    byId('tc').textContent = t.replace(/(.{4})(?=.)/g, '$1-');
    box.hidden = false;
  }
});
