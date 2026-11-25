<?php
/**
 * GET /api/profile/file.php?id=DOC — دریافت فایل خصوصی با کنترل دسترسی
 *  - Admin: همه   - Editor: فقط عکس پروفایل افراد دامنه‌ی اردوهای مجازش   - خود فرد: فایل‌های فعال و غیر «فقط مدیر»
 * دسترسی نامجاز ۴۰۴ می‌دهد تا وجود فایل لو نرود. نام فایل روی دیسک تصادفی است و هرگز در URL نمی‌آید.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
$s = db()->prepare('SELECT member_id,category,path,mime,visibility,archived_at FROM member_documents WHERE id=?');
$s->execute([$id]);
$d = $s->fetch();
$ok = false;
if ($d) {
    $u = current_user();
    if ($u && $u['role'] === 'admin') {
        $ok = true;
    } elseif ($u && in_array($u['role'], ['editor'], true) && $d['category'] === 'avatar' && $d['archived_at'] === null) {
        $ok = pf_editor_may_see((int)$d['member_id']);
    }
    if (!$ok) {
        $mid = current_member_id();
        $ok = $mid && $mid === (int)$d['member_id'] && $d['archived_at'] === null && $d['visibility'] !== 'admin';
    }
}
$path = $ok ? private_path((string)$d['path']) : null;
$mime = $d['mime'] ?? '';
if (!$path || !in_array($mime, ['image/webp', 'image/jpeg', 'image/png', 'application/pdf'], true)) {
    out(false, 'فایل یافت نشد', null, 404);
}
session_write_close();
header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header('Content-Disposition: inline; filename="file.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
readfile($path);
exit;
