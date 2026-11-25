<?php
/**
 * GET → مدارک من — POST multipart {title, category, file} → افزودن مدرک خصوصی — POST JSON {op:'delete', id} → حذف (Archive) مدرکِ خودآپلودشده
 * فایل‌ها در backend/storage/private هستند و فقط از طریق file.php با کنترل دسترسی دریافت می‌شوند.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$mid = $v['member_id'];
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!empty($_FILES['file'])) {
        $cat = (string)($_POST['category'] ?? 'other');
        if (!in_array($cat, ['certificate', 'training', 'other'], true)) {
            out(false, 'دسته‌بندی نامعتبر است', null, 422);
        }
        if (pf_scalar($pdo, "SELECT COUNT(*) FROM member_documents WHERE member_id=? AND archived_at IS NULL AND uploaded_by='member'", [$mid]) >= 50) {
            out(false, 'سقف تعداد مدارک رسیده است', null, 422);
        }
        pf_doc_create($pdo, $mid, $cat, trim((string)($_POST['title'] ?? '')), $_FILES['file'], 'private', 'member');
        log_member($mid, 'profile_doc', 'add');
        out(true, 'مدرک افزوده شد');
    }
    $d = json_input();
    if (($d['op'] ?? '') === 'delete') {
        $q = $pdo->prepare("UPDATE member_documents SET archived_at=NOW() WHERE id=? AND member_id=? AND uploaded_by='member' AND category<>'avatar' AND archived_at IS NULL");
        $q->execute([(int)($d['id'] ?? 0), $mid]);
        if (!$q->rowCount()) {
            out(false, 'این مدرک قابل حذف نیست', null, 403);
        }
        log_member($mid, 'profile_doc', 'delete');
        out(true, 'مدرک حذف شد');
    }
    out(false, 'فایلی ارسال نشد (حجم فایل از محدودیت PHP بیشتر است؟)', null, 422);
}
out(true, '', pf_section('documents', $mid, $v));
