/**
 * jalali.js — تقویم شمسی (جلالی) برای همه‌ی سایت و پنل: تبدیل تاریخ، نمایش و انتخابگر تاریخ (بدون وابستگی)
 * - مقدار ذخیره‌شده در دیتابیس و API همیشه میلادی (YYYY-MM-DD) می‌ماند؛ کاربر فقط تاریخ شمسی می‌بیند و انتخاب می‌کند.
 * - هر <input type="date"> (ثابت یا پویا) خودکار به انتخابگر شمسی تبدیل می‌شود؛ همان عنصر می‌ماند و .value همچنان ISO است.
 * - <input data-jdate="text"> = فیلد متنیِ شمسی (مثل پاسخ فرم ثبت‌نام): مقدار به‌صورت 1405/05/20 با ارقام انگلیسی.
 * - jd('2026-07-12') → «۱۴۰۵/۰۴/۲۱»؛ jd('2026-07-12 14:30', true) → با ساعت؛ jdl(...) → «۲۱ تیر ۱۴۰۵».
 */
(function () {
  'use strict';
  var FA = '۰۱۲۳۴۵۶۷۸۹', AR = '٠١٢٣٤٥٦٧٨٩';
  var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  var WD = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
  var div = function (a, b) { return Math.floor(a / b); };

  function toEn(s) { return String(s == null ? '' : s).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return AR.indexOf(d); }); }
  function toFa(s) { return String(s).replace(/\d/g, function (d) { return FA[d]; }); }
  function p2(n) { return (n < 10 ? '0' : '') + n; }

  function g2j(gy, gm, gd) {
    var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334], jy;
    if (gy > 1600) { jy = 979; gy -= 1600; } else { jy = 0; gy -= 621; }
    var gy2 = gm > 2 ? gy + 1 : gy;
    var days = 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) - 80 + gd + gdm[gm - 1];
    jy += 33 * div(days, 12053); days %= 12053;
    jy += 4 * div(days, 1461); days %= 1461;
    if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
    var jm, jd;
    if (days < 186) { jm = 1 + div(days, 31); jd = 1 + days % 31; } else { jm = 7 + div(days - 186, 30); jd = 1 + (days - 186) % 30; }
    return [jy, jm, jd];
  }
  function j2g(jy, jm, jd) {
    var gy;
    if (jy > 979) { gy = 1600; jy -= 979; } else { gy = 621; }
    var days = 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + 78 + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
    gy += 400 * div(days, 146097); days %= 146097;
    if (days > 36524) { days--; gy += 100 * div(days, 36524); days %= 36524; if (days >= 365) days++; }
    gy += 4 * div(days, 1461); days %= 1461;
    if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
    var gd = days + 1;
    var sal = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31], gm;
    for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
    return [gy, gm, gd];
  }
  function isLeap(jy) { var g = j2g(jy, 12, 30), j = g2j(g[0], g[1], g[2]); return j[0] === jy && j[1] === 12 && j[2] === 30; }
  function monthLen(jy, jm) { return jm <= 6 ? 31 : jm <= 11 ? 30 : (isLeap(jy) ? 30 : 29); }
  function validJ(jy, jm, jd) { return jy >= 1 && jy <= 3000 && jm >= 1 && jm <= 12 && jd >= 1 && jd <= monthLen(jy, jm); }

  /** 'YYYY-MM-DD[ HH:MM[:SS]]' ← [jy,jm,jd,'HH:MM'] یا null */
  function parseIso(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(String(s || '').trim());
    if (!m || +m[1] === 0 || +m[2] === 0 || +m[3] === 0) return null;
    var j = g2j(+m[1], +m[2], +m[3]);
    return [j[0], j[1], j[2], m[4] ? m[4] + ':' + m[5] : ''];
  }
  function isoOf(jy, jm, jd) { var g = j2g(jy, jm, jd); return g[0] + '-' + p2(g[1]) + '-' + p2(g[2]); }
  /** «1405/05/20» (ارقام فارسی/عربی/انگلیسی؛ جداکننده / - .) ← [jy,jm,jd] یا null */
  function parseJ(s) {
    var m = /^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/.exec(toEn(s).trim());
    return m && validJ(+m[1], +m[2], +m[3]) ? [+m[1], +m[2], +m[3]] : null;
  }
  function jstr(j) { return j[0] + '/' + p2(j[1]) + '/' + p2(j[2]); }

  /** نمایش شمسی یک تاریخ/زمان میلادی (رشته‌ی دیتابیس، Date یا عدد) */
  function jd(v, withTime) {
    if (v == null || v === '') return '';
    if (v instanceof Date) v = v.getFullYear() + '-' + p2(v.getMonth() + 1) + '-' + p2(v.getDate()) + ' ' + p2(v.getHours()) + ':' + p2(v.getMinutes());
    var p = parseIso(v);
    return p ? toFa(jstr(p) + (withTime && p[3] ? ' ' + p[3] : '')) : String(v);
  }
  /** نمایش بلند: «۲۱ تیر ۱۴۰۵» */
  function jdl(v, withTime) {
    var p = parseIso(v);
    return p ? toFa(p[2] + ' ' + MONTHS[p[1] - 1] + ' ' + p[0] + (withTime && p[3] ? ' — ' + p[3] : '')) : String(v == null ? '' : v);
  }
  function todayJ() { var d = new Date(); return g2j(d.getFullYear(), d.getMonth() + 1, d.getDate()); }

  /* ─────────────── انتخابگر ─────────────── */
  var pop = null, cur = null; // pop = عنصر پنجره، cur = {el, mode, view:[jy,jm]}

  function close() { if (pop) { pop.remove(); pop = null; cur = null; } }
  function place() {
    if (!pop || !cur) return;
    var r = cur.vis.getBoundingClientRect(), h = pop.offsetHeight, w = pop.offsetWidth;
    var top = r.bottom + 6, left = r.left + (r.width - w) / 2;
    if (top + h > innerHeight - 8 && r.top - h - 6 > 8) top = r.top - h - 6;
    left = Math.max(8, Math.min(left, innerWidth - w - 8));
    pop.style.top = (top + scrollY) + 'px'; pop.style.left = (left + scrollX) + 'px';
  }
  function setValue(c, j) { // j = [jy,jm,jd] یا null
    if (c.mode === 'text') { c.el.value = j ? jstr(j) : ''; } else {
      c.setIso(j ? isoOf(j[0], j[1], j[2]) : '');
    }
    c.el.dispatchEvent(new Event('input', { bubbles: true }));
    c.el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function draw() {
    var c = cur, vy = c.view[0], vm = c.view[1], sel = c.sel(), t = todayJ();
    var first = j2g(vy, vm, 1), wd = (new Date(first[0], first[1] - 1, first[2]).getDay() + 1) % 7, n = monthLen(vy, vm);
    var yrs = '';
    for (var y = Math.min(vy, t[0]) - 60 < 1200 ? 1200 : Math.min(vy, t[0]) - 60; y <= Math.max(vy, t[0]) + 30; y++) yrs += '<option value="' + y + '"' + (y === vy ? ' selected' : '') + '>' + toFa(y) + '</option>';
    var mo = MONTHS.map(function (m, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === vm ? ' selected' : '') + '>' + m + '</option>'; }).join('');
    var cells = '';
    for (var i = 0; i < wd; i++) cells += '<span></span>';
    for (var d = 1; d <= n; d++) {
      var on = sel && sel[0] === vy && sel[1] === vm && sel[2] === d, td = t[0] === vy && t[1] === vm && t[2] === d;
      cells += '<button type="button" class="jd-d' + (on ? ' on' : '') + (td ? ' td' : '') + (((wd + d - 1) % 7) === 6 ? ' fri' : '') + '" data-d="' + d + '"' + (on ? ' aria-pressed="true"' : '') + '>' + toFa(d) + '</button>';
    }
    pop.innerHTML = '<div class="jd-h"><button type="button" class="jd-n" data-m="-1" aria-label="ماه قبل">›</button><select data-sel="m" aria-label="ماه">' + mo + '</select><select data-sel="y" aria-label="سال">' + yrs +
      '</select><button type="button" class="jd-n" data-m="1" aria-label="ماه بعد">‹</button></div><div class="jd-w">' + WD.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</div><div class="jd-g">' + cells +
      '</div><div class="jd-f"><button type="button" data-a="today">امروز</button><button type="button" data-a="clear">پاک‌کردن</button><button type="button" data-a="close">بستن</button></div>';
    place();
  }
  function open(c) {
    close();
    pop = document.createElement('div');
    pop.className = 'jd-pop'; pop.setAttribute('role', 'dialog'); pop.setAttribute('aria-label', 'انتخاب تاریخ شمسی'); pop.dir = 'rtl';
    cur = c; c.view = (c.sel() || todayJ()).slice(0, 2);
    pop.addEventListener('mousedown', function (e) { if (e.target.tagName !== 'SELECT') e.preventDefault(); }); // فوکوس فیلد حفظ شود
    pop.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b || !cur) return;
      if (b.dataset.m) { var m = cur.view[1] + +b.dataset.m, y = cur.view[0]; if (m < 1) { m = 12; y--; } if (m > 12) { m = 1; y++; } cur.view = [y, m]; return draw(); }
      if (b.dataset.d) { setValue(cur, [cur.view[0], cur.view[1], +b.dataset.d]); return close(); }
      if (b.dataset.a === 'today') { setValue(cur, todayJ()); return close(); }
      if (b.dataset.a === 'clear') { setValue(cur, null); return close(); }
      if (b.dataset.a === 'close') { var v = cur.vis; close(); v.focus(); }
    });
    pop.addEventListener('change', function (e) {
      var s = e.target.dataset.sel; if (!s || !cur) return;
      cur.view = s === 'y' ? [+e.target.value, cur.view[1]] : [cur.view[0], +e.target.value]; draw();
    });
    document.body.appendChild(pop); draw();
  }
  document.addEventListener('mousedown', function (e) { if (pop && !pop.contains(e.target) && !(cur && cur.vis === e.target)) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pop) { var v = cur.vis; close(); v.focus(); } });
  addEventListener('resize', place); addEventListener('scroll', place, true);

  /* ─────────────── تبدیل فیلدها ─────────────── */
  var nativeValue = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

  function enhanceDate(el) { // <input type=date> ← فیلد مخفی ISO + فیلد متنی شمسی (همان عنصر می‌ماند)
    if (el.dataset.jdone) return; el.dataset.jdone = '1';
    var vis = document.createElement('input');
    vis.type = 'text'; vis.className = 'jd-in ' + (el.className || ''); vis.dir = 'ltr'; vis.autocomplete = 'off';
    vis.inputMode = 'numeric'; vis.placeholder = '۱۴۰۵/۰۱/۰۱'; vis.maxLength = 10;
    vis.required = el.required; vis.disabled = el.disabled;
    var lab = el.id && document.querySelector('label[for="' + el.id + '"]');
    if (el.id) { vis.id = el.id + '__j'; if (lab) lab.htmlFor = vis.id; }
    ['aria-label', 'title', 'style'].forEach(function (a) { if (el.hasAttribute(a)) vis.setAttribute(a, el.getAttribute(a)); });
    var iso = nativeValue.get.call(el);
    el.required = false; el.type = 'hidden'; nativeValue.set.call(el, iso);
    el.parentNode.insertBefore(vis, el);
    var showIso = function () { var v = nativeValue.get.call(el); vis.value = v ? jd(v).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }) : ''; vis.setCustomValidity(''); };
    var c = { el: el, vis: vis, mode: 'date',
      sel: function () { var p = parseIso(nativeValue.get.call(el)); return p ? p.slice(0, 3) : null; },
      setIso: function (v) { nativeValue.set.call(el, v); showIso(); } };
    Object.defineProperty(el, 'value', { configurable: true, get: function () { return nativeValue.get.call(el); }, set: function (v) { nativeValue.set.call(el, v == null ? '' : String(v)); showIso(); } });
    showIso();
    vis.addEventListener('focus', function () { open(c); });
    vis.addEventListener('click', function () { if (!pop || cur !== c) open(c); });
    vis.addEventListener('input', function () {
      var t = toEn(vis.value).replace(/[^\d\/\-.]/g, ''); if (t !== vis.value) vis.value = t;
      var j = parseJ(t);
      if (j) { nativeValue.set.call(el, isoOf(j[0], j[1], j[2])); vis.setCustomValidity(''); if (cur === c) { cur.view = j.slice(0, 2); draw(); } } else {
        nativeValue.set.call(el, ''); vis.setCustomValidity(t ? 'تاریخ شمسی معتبر وارد کنید (مثل ۱۴۰۵/۰۱/۱۵)' : '');
      }
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    vis.addEventListener('blur', function () { setTimeout(function () { if (!(pop && pop.contains(document.activeElement))) { var j = parseJ(vis.value); if (j) vis.value = jstr(j); } }, 0); });
    new MutationObserver(function () { vis.disabled = el.disabled; }).observe(el, { attributes: true, attributeFilter: ['disabled'] });
  }

  function enhanceText(el) { // <input data-jdate="text">: مقدار = رشته‌ی شمسی با ارقام انگلیسی
    if (el.dataset.jdone) return; el.dataset.jdone = '1';
    el.autocomplete = 'off'; el.inputMode = 'numeric'; el.dir = 'ltr';
    if (!el.placeholder || /^\d{4}\//.test(el.placeholder)) el.placeholder = '۱۴۰۵/۰۱/۰۱';
    var c = { el: el, vis: el, mode: 'text', sel: function () { return parseJ(el.value); } };
    el.addEventListener('focus', function () { open(c); });
    el.addEventListener('click', function () { if (!pop || cur !== c) open(c); });
    el.addEventListener('input', function () { var j = parseJ(el.value); if (j && cur === c) { cur.view = j.slice(0, 2); draw(); } });
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('input[type=date]:not([data-jdone])').forEach(enhanceDate);
    root.querySelectorAll('input[data-jdate="text"]:not([data-jdone])').forEach(enhanceText);
  }
  function start() { scan(document); new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && (n.matches('input[type=date]') || n.matches('input[data-jdate]'))) scan(n.parentNode); else scan(n); } }); }); }).observe(document.documentElement, { childList: true, subtree: true }); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

  window.JD = { g2j: g2j, j2g: j2g, isLeap: isLeap, monthLen: monthLen, parseIso: parseIso, parseJ: parseJ, isoOf: isoOf, toFa: toFa, toEn: toEn, today: todayJ, months: MONTHS, fmt: jd, long: jdl };
  window.jd = jd; window.jdl = jdl;
})();
