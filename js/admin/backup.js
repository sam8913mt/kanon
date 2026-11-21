/** backup.js — دانلود Backup دیتابیس و فایل‌ها (فقط Admin؛ سمت سرور هم بررسی می‌شود) */
VIEWS.backup = async () => {
  $('#c').innerHTML = `<p>نسخه‌ی پشتیبان را دانلود و در جای امن نگه دارید. برای بازیابی، فایل SQL را در phpMyAdmin (Import) و فایل ZIP را در پوشه‌ی <code>uploads</code> بازگردانید.</p>
    <div class="row"><a class="btn" href="/api/backup.php">Backup دیتابیس (SQL)</a><a class="btn alt" href="/api/backup.php?type=files">Backup فایل‌ها (ZIP)</a></div>
    <p class="hint">پیشنهاد: قبل از هر به‌روزرسانی و دست‌کم هفته‌ای یک‌بار Backup بگیرید.</p>`;
};
