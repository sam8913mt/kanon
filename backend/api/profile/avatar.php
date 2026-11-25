<?php
/** POST /api/profile/avatar.php — تغییر عکس پروفایل (multipart: file) یا حذف آن (JSON {op:'remove'}) */
require __DIR__ . '/../../includes/bootstrap.php';
$mid = require_member();
csrf_check();
$pdo = db();
if (!empty($_FILES['file'])) {
    $id = pf_set_avatar($pdo, $mid, $_FILES['file'], 'member');
    log_member($mid, 'profile_avatar');
    out(true, 'عکس پروفایل به‌روز شد', ['avatar_id' => $id]);
}
if ((json_input()['op'] ?? '') === 'remove') {
    pf_remove_avatar($pdo, $mid);
    log_member($mid, 'profile_avatar', 'remove');
    out(true, 'عکس پروفایل حذف شد');
}
out(false, 'فایلی ارسال نشد (حجم فایل از محدودیت PHP بیشتر است؟)', null, 422);
