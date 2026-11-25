<?php
/** GET → اعلان‌ها و تعداد خوانده‌نشده — POST JSON {op:'read', id} | {op:'read_all'} */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
$mid = $v['member_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d = json_input();
    if (($d['op'] ?? '') === 'read_all') {
        db()->prepare('UPDATE member_notifications SET is_read=1, read_at=NOW() WHERE member_id=? AND is_read=0')->execute([$mid]);
    } elseif (($d['op'] ?? '') === 'read') {
        db()->prepare('UPDATE member_notifications SET is_read=1, read_at=NOW() WHERE id=? AND member_id=? AND is_read=0')->execute([(int)($d['id'] ?? 0), $mid]);
    } else {
        out(false, 'عملیات نامعتبر', null, 422);
    }
    out(true, '', pf_notifications(db(), $mid));
}
out(true, '', pf_section('notifications', $mid, $v));
