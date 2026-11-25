<?php
/**
 * GET /api/profile/user.php?id=ID&section=profile|registrations|activities|…[&reg=ID]
 * ورودی عمومی Admin/Editor (و خود فرد فقط برای شناسه‌ی خودش). Backend همیشه بررسی می‌کند:
 * Authentication ← مالکیت ← نقش ← دامنه‌ی اردوهای Editor ← مجاز بودن بخش. ID در URL هرگز به‌تنهایی کافی نیست.
 */
require __DIR__ . '/../../includes/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    out(false, 'شناسه نامعتبر است', null, 422);
}
$v = pf_viewer_for($id);
$data = pf_section((string)($_GET['section'] ?? 'profile'), $id, $v);
if (($_GET['section'] ?? 'profile') === 'profile' && $v['type'] === 'admin') {
    $data['lists'] = ['users' => db()->query('SELECT id,username FROM users WHERE active=1 ORDER BY username')->fetchAll()];
}
out(true, '', $data);
