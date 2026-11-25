/**
 * enhance.js — ابزارهای مشترک رابط (سایت و پنل)، بدون وابستگی:
 * 1) دکمه‌ی چشم نمایش/مخفی‌کردن برای «همه‌ی» فیلدهای رمز عبور (ثابت و پویا)
 * 2) copyText(text) — کپی در کلیپ‌بورد (با جایگزین برای مرورگرهای قدیمی)
 * 3) compressImage(file, {maxDim, quality}) — فشرده‌سازی خودکار تصویر سمت کاربر (اعتبارسنجی نهایی همیشه سمت سرور است)
 * 4) pickAvatar(file) — پیش‌نمایش و کادر برش قابل تنظیم برای عکس پروفایل؛ خروجی File مربعی یا null (انصراف)
 */
(function () {
  'use strict';
  var EYE = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19C5 19 1 12 1 12a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A10.7 10.7 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';

  /* ── ۱) چشم رمز ── */
  function eye(inp) {
    if (inp.dataset.eye) return; inp.dataset.eye = '1';
    var w = document.createElement('span'); w.className = 'pw-wrap';
    var b = document.createElement('button'); b.type = 'button'; b.className = 'pw-eye'; b.tabIndex = 0;
    var paint = function () { var show = inp.type === 'text'; b.innerHTML = show ? EYE_OFF : EYE; b.setAttribute('aria-label', show ? 'مخفی کردن گذرواژه' : 'نمایش گذرواژه'); b.setAttribute('aria-pressed', show); };
    b.addEventListener('click', function () { var pos = inp.selectionStart; inp.type = inp.type === 'password' ? 'text' : 'password'; paint(); inp.focus(); try { inp.setSelectionRange(pos, pos); } catch (e) {} });
    inp.parentNode.insertBefore(w, inp); w.appendChild(inp); w.appendChild(b); paint();
    var sync = function () { var hid = inp.hidden || inp.style.display === 'none'; w.style.display = hid ? 'none' : ''; b.disabled = inp.disabled; };
    sync();
    new MutationObserver(sync).observe(inp, { attributes: true, attributeFilter: ['style', 'hidden', 'disabled'] });
    // هنگام ارسال فرم، رمزِ نمایان دوباره مخفی شود
    if (inp.form) inp.form.addEventListener('submit', function () { if (inp.type === 'text') { inp.type = 'password'; paint(); } }, true);
  }
  function scanEye(root) { if (root && root.querySelectorAll) root.querySelectorAll('input[type=password]:not([data-eye])').forEach(eye); }

  /* ── ۲) کپی ── */
  function copyText(t) {
    t = String(t == null ? '' : t);
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(t).then(function () { return true; }, function () { return legacy(t); });
    return Promise.resolve(legacy(t));
  }
  function legacy(t) {
    try {
      var a = document.createElement('textarea'); a.value = t; a.setAttribute('readonly', ''); a.style.cssText = 'position:fixed;top:-100px;opacity:0';
      document.body.appendChild(a); a.select(); a.setSelectionRange(0, t.length); var ok = document.execCommand('copy'); a.remove(); return ok;
    } catch (e) { return false; }
  }

  /* ── ۳) فشرده‌سازی تصویر ── */
  function loadImg(file) {
    return new Promise(function (res, rej) {
      var u = URL.createObjectURL(file), i = new Image();
      i.onload = function () { res({ img: i, url: u }); }; i.onerror = function () { URL.revokeObjectURL(u); rej(new Error('bad image')); }; i.src = u;
    });
  }
  function canvasBlob(c, type, q) { return new Promise(function (res) { c.toBlob(function (b) { res(b); }, type, q); }); }
  /** تصویر را کوچک و فشرده می‌کند؛ اگر نشد یا بزرگ‌تر شد، فایل اصلی را برمی‌گرداند. */
  function compressImage(file, o) {
    o = o || {};
    var maxDim = o.maxDim || 1600, q = o.quality || 0.82;
    if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.resolve(file);
    return loadImg(file).then(function (r) {
      var i = r.img, w = i.naturalWidth, h = i.naturalHeight, s = Math.min(1, maxDim / Math.max(w, h));
      if (s === 1 && file.size < 250 * 1024) { URL.revokeObjectURL(r.url); return file; }
      var c = document.createElement('canvas'); c.width = Math.max(1, Math.round(w * s)); c.height = Math.max(1, Math.round(h * s));
      var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(i, 0, 0, c.width, c.height);
      URL.revokeObjectURL(r.url);
      return canvasBlob(c, 'image/webp', q).then(function (b) {
        var type = 'image/webp', ext = 'webp';
        if (!b || b.type !== 'image/webp') return canvasBlob(c, 'image/jpeg', q).then(function (j) { return [j, 'image/jpeg', 'jpg']; });
        return [b, type, ext];
      }).then(function (a) {
        var b = a[0]; if (!b || b.size >= file.size) return file;
        return new File([b], (file.name || 'image').replace(/\.[^.]+$/, '') + '.' + a[2], { type: a[1] });
      });
    }).catch(function () { return file; });
  }

  /* ── ۴) برش عکس پروفایل ── */
  function pickAvatar(file, opts) {
    opts = opts || {};
    var OUT = opts.size || 600;
    return new Promise(function (resolve) {
      if (!file || !/^image\//.test(file.type)) return resolve(file || null);
      loadImg(file).then(function (r) {
        var img = r.img, NW = img.naturalWidth, NH = img.naturalHeight;
        var M = document.createElement('div'); M.className = 'crop-modal'; M.setAttribute('role', 'dialog'); M.setAttribute('aria-modal', 'true'); M.setAttribute('aria-label', 'برش عکس پروفایل');
        M.innerHTML = '<div class="crop-box"><h3>برش عکس پروفایل</h3><p class="crop-hint">کادر را بکشید تا محل برش را انتخاب کنید؛ با دستگیره‌ی گوشه یا لغزنده اندازه را تنظیم کنید.</p>' +
          '<div class="crop-stage" id="cs"><img id="ci" alt="پیش‌نمایش عکس" draggable="false"><div class="crop-sel" id="cb" tabindex="0" role="slider" aria-label="کادر برش (با کلیدهای جهت‌دار جابه‌جا کنید)"><span class="crop-h" id="ch"></span></div></div>' +
          '<div class="crop-row"><label for="cz">اندازه‌ی کادر</label><input type="range" id="cz" min="20" max="100" value="80"><canvas id="cp" width="72" height="72" aria-label="پیش‌نمایش نهایی"></canvas></div>' +
          '<div class="crop-acts"><button type="button" class="btn" id="cok">برش و استفاده</button><button type="button" class="btn alt" id="cno">انصراف</button></div></div>';
        document.body.appendChild(M);
        var $ = function (id) { return M.querySelector('#' + id); }, st = $('cs'), im = $('ci'), bx = $('cb'), hd = $('ch'), zs = $('cz'), pv = $('cp');
        var maxW = Math.min(420, innerWidth - 56), maxH = Math.max(200, Math.min(420, innerHeight - 300));
        var sc = Math.min(maxW / NW, maxH / NH); var DW = Math.round(NW * sc), DH = Math.round(NH * sc);
        st.style.width = DW + 'px'; st.style.height = DH + 'px'; im.src = r.url; im.width = DW; im.height = DH;
        var minS = 24, S, X, Y; // کادر مربعی: اندازه و مختصات در فضای نمایش
        var maxS = Math.min(DW, DH);
        S = Math.round(maxS * 0.8); X = Math.round((DW - S) / 2); Y = Math.round((DH - S) / 2);
        var clamp = function () { S = Math.max(Math.min(minS, maxS), Math.min(S, maxS)); X = Math.max(0, Math.min(X, DW - S)); Y = Math.max(0, Math.min(Y, DH - S)); };
        var paint = function () {
          clamp(); bx.style.cssText = 'left:' + X + 'px;top:' + Y + 'px;width:' + S + 'px;height:' + S + 'px';
          zs.value = Math.round(S / maxS * 100);
          var x = pv.getContext('2d'); x.clearRect(0, 0, 72, 72); x.drawImage(img, X / sc, Y / sc, S / sc, S / sc, 0, 0, 72, 72);
        };
        paint();
        var mode = null, sx, sy, ox, oy, os;
        var down = function (e, m) { mode = m; sx = e.clientX; sy = e.clientY; ox = X; oy = Y; os = S; e.preventDefault(); e.stopPropagation(); (e.target.setPointerCapture || function () {}).call(e.target, e.pointerId); };
        bx.addEventListener('pointerdown', function (e) { down(e, 'move'); });
        hd.addEventListener('pointerdown', function (e) { down(e, 'size'); });
        var move = function (e) {
          if (!mode) return; var dx = e.clientX - sx, dy = e.clientY - sy;
          if (mode === 'move') { X = ox + dx; Y = oy + dy; } else { var d = Math.max(dx, dy); S = os + d; if (X + S > DW) S = DW - X; if (Y + S > DH) S = DH - Y; }
          paint();
        };
        bx.addEventListener('pointermove', move); hd.addEventListener('pointermove', move);
        var up = function () { mode = null; }; bx.addEventListener('pointerup', up); hd.addEventListener('pointerup', up); bx.addEventListener('pointercancel', up); hd.addEventListener('pointercancel', up);
        zs.addEventListener('input', function () { var c = X + S / 2, d = Y + S / 2; S = Math.round(maxS * zs.value / 100); X = c - S / 2; Y = d - S / 2; paint(); });
        bx.addEventListener('keydown', function (e) { var k = { ArrowLeft: [-8, 0], ArrowRight: [8, 0], ArrowUp: [0, -8], ArrowDown: [0, 8] }[e.key]; if (k) { X += k[0]; Y += k[1]; paint(); e.preventDefault(); } });
        var done = function (v) { document.removeEventListener('keydown', esc); URL.revokeObjectURL(r.url); M.remove(); resolve(v); };
        var esc = function (e) { if (e.key === 'Escape') done(null); };
        document.addEventListener('keydown', esc);
        $('cno').onclick = function () { done(null); };
        M.addEventListener('mousedown', function (e) { if (e.target === M) done(null); });
        $('cok').onclick = function () {
          var side = Math.min(OUT, Math.round(S / sc)), c = document.createElement('canvas'); c.width = c.height = side;
          var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, side, side); x.drawImage(img, X / sc, Y / sc, S / sc, S / sc, 0, 0, side, side);
          canvasBlob(c, 'image/webp', 0.86).then(function (b) {
            if (b && b.type === 'image/webp') return [b, 'webp'];
            return canvasBlob(c, 'image/jpeg', 0.86).then(function (j) { return [j, 'jpg']; });
          }).then(function (a) { done(a[0] ? new File([a[0]], 'avatar.' + a[1], { type: a[0].type }) : file); });
        };
        $('cok').focus();
      }).catch(function () { resolve(file); });
    });
  }

  window.copyText = copyText; window.compressImage = compressImage; window.pickAvatar = pickAvatar;

  function start() {
    scanEye(document);
    new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (n.matches && n.matches('input[type=password]')) scanEye(n.parentNode); else scanEye(n); } }); }); }).observe(document.documentElement, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
