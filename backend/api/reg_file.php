<?php
/**
 * reg_file.php — دریافت فایل‌های ثبت‌نام اردو با کنترل دسترسی (عکس دانش‌آموز و پاسخ‌های تصویر/فایل)
 *
 * GET ?id=REG_ID            → عکس ثبت‌نام
 * GET ?id=REG_ID&a=ANSWER   → فایل یک پاسخ (باید متعلق به همان ثبت‌نام باشد)
 * GET ?id=REG_ID&r=1        → تصویر رسید پرداخت همان ثبت‌نام
 *
 * دسترسی: Admin ← همه | Editor ← فقط ثبت‌نام‌های اردوهای مجاز خودش | عضو ← فقط ثبت‌نام‌های خودش.
 * نامجاز و ناموجود هر دو ۴۰۴ می‌دهند (وجود فایل لو نمی‌رود). نام فایل روی دیسک تصادفی است و در URL نمی‌آید.
 * هم فایل‌های جدید (storage/private) و هم فایل‌های قدیمی (uploads/images|files) را پوشش می‌دهد.
 */
require __DIR__ . '/../includes/bootstrap.php';
$regId = (int)($_GET['id'] ?? 0);
$ansId = (int)($_GET['a'] ?? 0);
$pdo   = db();
$s = $pdo->prepare('SELECT id,camp_id,member_id,photo FROM registrations WHERE id=?');
$s->execute([$regId]);
$r = $s->fetch();
$rel = null;
if ($r) {
    if (!empty($_GET['r'])) {
        $q = $pdo->prepare('SELECT file FROM payment_receipts WHERE registration_id=?');
        $q->execute([$regId]);
        $rel = $q->fetchColumn() ?: null;
    } elseif ($ansId) {
        $q = $pdo->prepare('SELECT answer FROM registration_answers WHERE id=? AND registration_id=?');
        $q->execute([$ansId, $regId]);
        $rel = $q->fetchColumn() ?: null;
    } else {
        $rel = $r['photo'];
    }
}
$ok = false;
if ($r && $rel && is_reg_file_ref((string)$rel)) {
    $u = current_user();
    if ($u && $u['role'] === 'admin') {
        $ok = true;
    } elseif ($u && $u['role'] === 'editor' && in_array('registrations', EDITOR_CAN, true)) {
        $ok = camp_allowed((int)$r['camp_id']);
    }
    if (!$ok) {
        $mid = current_member_id();
        $ok  = $mid > 0 && $r['member_id'] !== null && $mid === (int)$r['member_id'];
    }
}
$path = $ok ? reg_file_path((string)$rel) : null;
$mime = $path ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($path) : '';
if (!$path || !in_array($mime, ['image/webp', 'image/jpeg', 'image/png', 'application/pdf'], true)) {
    out(false, 'فایل یافت نشد', null, 404);
}
session_write_close();
header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="file.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
readfile($path);
exit;
