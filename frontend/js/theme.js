/**
 * theme.js — تم روشن/تاریک (پیش‌فرض: تاریک؛ انتخاب کاربر در localStorage ذخیره می‌شود)
 * در <head> بارگذاری می‌شود تا هنگام باز شدن صفحه چشمک‌زدن تم رخ ندهد.
 */
(function () {
  var t;
  try { t = localStorage.getItem('theme'); } catch (e) {}
  if (t !== 'light' && t !== 'dark') t = 'dark'; // پیش‌فرض سایت: تاریک (انتخاب کاربر ذخیره می‌شود)
  document.documentElement.dataset.theme = t;
})();

function toggleTheme() {
  var n = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = n;
  try { localStorage.setItem('theme', n); } catch (e) {}
  return n;
}
