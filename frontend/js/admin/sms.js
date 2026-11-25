/** sms.js — «پیامک و کد ورود» (فقط Admin): اتصال به سامانه‌ی پیامکی، ورود با کد یکبار مصرف، یادآوری هیئت */
const SMS_GROUPS = [
  ['اتصال به سامانه‌ی پیامکی (کاوه‌نگار)', [['secret_sms_apikey', 'کلید API کاوه‌نگار'], ['sms_sender', 'شماره‌ی خط ارسال‌کننده (خالی = خط پیش‌فرض حساب)']]],
  ['ورود با کد یکبار مصرف', [
    ['sms_otp_enabled', 'فعال‌سازی «ورود با کد یکبار مصرف» در صفحه‌ی ورود کاربران (غیرفعال = ورود فقط با گذرواژه، مثل قبل)', 'bool'],
    ['sms_otp_text', 'متن پیامک — به‌جای {code} کد ۵ رقمی قرار می‌گیرد', 'area'],
    ['sms_otp_ttl', 'مدت اعتبار کد (دقیقه، بین ۱ تا ۱۰)']]],
  ['پیامک یادآوری هیئت', [
    ['sms_enabled', 'ارسال پیامک یادآوری یک روز قبل از هر هیئت فعال باشد (غیرفعال = هیچ پیامکی ارسال نمی‌شود)', 'bool'],
    ['sms_heyat_text', 'متن پیامک — می‌توانید از {title} عنوان، {occasion} مناسبت و {time} ساعت استفاده کنید', 'area']]],
];
VIEWS.sms = async () => {
  await renderSettings(SMS_GROUPS);
  $('#c').insertAdjacentHTML('beforeend', `<div class="f wide"><fieldset class="fs"><legend>تست اتصال</legend>
    <p class="muted">ابتدا تنظیمات را ذخیره کنید، سپس یک پیامک آزمایشی بفرستید.</p>
    <div class="row"><input id="sms_t" dir="ltr" inputmode="tel" placeholder="09123456789" maxlength="11"><button type="button" class="btn alt" id="sms_tb">ارسال پیامک آزمایشی</button></div>
    <p class="muted">ارسال پیامک یادآوری هیئت علاوه بر این تنظیمات، به یک Cron Job روزانه نیاز دارد (راهنمای نصب).</p></fieldset></div>`);
  $('#sms_tb').onclick = async () => {
    const r = await call('sms_admin.php', { op: 'test', mobile: $('#sms_t').value });
    toast(r.message, !!r.success);
  };
};


/** ارسال فوری پیامک هیئت (Admin / Editor / مدیر رسانه) — کلید API در دسترس این کاربران نیست */
VIEWS.__heyat_sms = async h => {
  const r = (await call('heyat_send.php?id=' + h.id)).data;
  if (!r) return;
  $('#c').innerHTML = `<form class="f wide" id="hs"><h3>ارسال پیامک هیئت «${esc(h.title)}»</h3>
    <p class="hint">پیامک برای ${r.count} عضو فعال ارسال می‌شود.${r.ready ? '' : ' ⚠ کلید API پیامک هنوز توسط مدیر تنظیم نشده است.'}${r.sent_at ? ' آخرین ارسال: ' + esc(jd(r.sent_at, true)) : ''}</p>
    <label for="hs_t">متن پیامک</label><textarea id="hs_t" rows="5" maxlength="500">${esc(r.text)}</textarea>
    <button class="btn">ارسال به همه‌ی اعضای فعال</button> <a class="btn alt" href="#heyats">بازگشت</a></form>`;
  $('#hs').onsubmit = async e => {
    e.preventDefault();
    if (!confirm('پیامک همین حالا برای ' + r.count + ' عضو ارسال می‌شود. ادامه می‌دهید؟')) return;
    report(await call('heyat_send.php', { id: h.id, text: $('#hs_t').value }));
  };
};
