<?php
/**
 * GET → دستاوردهای من
 * POST multipart/JSON {title, kind, achieved_at, description, file?} → «درخواست ثبت» (وضعیت pending تا تأیید مدیر)
 * POST JSON {op:'delete', id} → حذف درخواست خودِ کاربر تا قبل از تأیید
 */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$mid = $v['member_id'];
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = $_POST ? $_POST : json_input();
    if (($d['op'] ?? '') === 'delete') {
        $q = $pdo->prepare("UPDATE member_achievements SET deleted_at=NOW() WHERE id=? AND member_id=? AND submitted_by='member' AND status<>'approved' AND deleted_at IS NULL");
        $q->execute([(int)($d['id'] ?? 0), $mid]);
        out((bool)$q->rowCount(), $q->rowCount() ? 'درخواست حذف شد' : 'این مورد قابل حذف نیست', null, $q->rowCount() ? 200 : 403);
    }
    if (pf_scalar($pdo, "SELECT COUNT(*) FROM member_achievements WHERE member_id=? AND status='pending' AND deleted_at IS NULL", [$mid]) >= 10) {
        out(false, 'ابتدا منتظر بررسی درخواست‌های قبلی بمانید', null, 429);
    }
    [$row, $err] = pf_clean_section('achievements', ['title' => $d['title'] ?? '', 'kind' => $d['kind'] ?? 'other', 'achieved_at' => $d['achieved_at'] ?? '',
        'description' => $d['description'] ?? '', 'status' => 'pending'], true);
    if ($err) {
        out(false, $err, null, 422);
    }
    $doc = !empty($_FILES['file']) ? pf_doc_create($pdo, $mid, 'certificate', $row['title'], $_FILES['file'], 'private', 'member') : null;
    $row += ['member_id' => $mid, 'submitted_by' => 'member', 'document_id' => $doc];
    $cols = array_keys($row);
    $pdo->prepare('INSERT INTO member_achievements(' . implode(',', $cols) . ') VALUES(' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
    log_member($mid, 'profile_achievement');
    out(true, 'درخواست ثبت دستاورد ارسال شد و پس از تأیید مدیر نمایش داده می‌شود');
}
out(true, '', pf_section('achievements', $mid, $v));
