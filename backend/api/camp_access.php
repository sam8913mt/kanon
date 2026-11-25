<?php
/**
 * camp_access.php — تعیین اردوها و آلبوم‌های مجاز هر Editor (فقط Admin)
 * GET  ?user_id=3                              → {camp_ids:[…], album_ids:[…]}
 * POST {user_id, camp_ids:[…], album_ids?:[…]} → ذخیره (album_ids فقط اگر ارسال شود جایگزین می‌شود)
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('users');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = $pdo->prepare('SELECT camp_id FROM camp_editors WHERE user_id=?');
    $s->execute([(int)($_GET['user_id'] ?? 0)]);
    $a = $pdo->prepare('SELECT album_id FROM album_editors WHERE user_id=?');
    $a->execute([(int)($_GET['user_id'] ?? 0)]);
    out(true, '', ['camp_ids' => array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN)), 'album_ids' => array_map('intval', $a->fetchAll(PDO::FETCH_COLUMN))]);
}
csrf_check();
$d = json_input();
$uid = (int)($d['user_id'] ?? 0);
$role = $pdo->prepare('SELECT role FROM users WHERE id=?');
$role->execute([$uid]);
if ($role->fetchColumn() !== 'editor') {
    out(false, 'فقط برای کاربر با نقش Editor قابل تنظیم است', null, 422);
}
$ids = array_values(array_unique(array_map('intval', (array)($d['camp_ids'] ?? []))));
$pdo->beginTransaction();
$pdo->prepare('DELETE FROM camp_editors WHERE user_id=?')->execute([$uid]);
$ins = $pdo->prepare('INSERT IGNORE INTO camp_editors(camp_id,user_id) SELECT id,? FROM camps WHERE id=?');
foreach ($ids as $cid) {
    $ins->execute([$uid, $cid]);
}
if (isset($d['album_ids']) && is_array($d['album_ids'])) {
    $aids = array_values(array_unique(array_map('intval', $d['album_ids'])));
    $pdo->prepare('DELETE FROM album_editors WHERE user_id=?')->execute([$uid]);
    $ai = $pdo->prepare('INSERT IGNORE INTO album_editors(album_id,user_id) SELECT id,? FROM albums WHERE id=?');
    foreach ($aids as $aid) {
        $ai->execute([$uid, $aid]);
    }
}
$pdo->commit();
log_act('camp_access', "$uid:" . implode(',', $ids));
out(true, 'ذخیره شد');
