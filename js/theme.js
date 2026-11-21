/**
 * theme.js — تم روشن/تاریک (ذخیره در localStorage)
 * در <head> بارگذاری می‌شود تا هنگام باز شدن صفحه چشمک‌زدن تم رخ ندهد.
 */
(function () {
  var t;
  try { t = localStorage.getItem('theme'); } catch (e) {}
  if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  document.documentElement.dataset.theme = t;
})();

function toggleTheme() {
  var n = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = n;
  try { localStorage.setItem('theme', n); } catch (e) {}
  return n;
}
