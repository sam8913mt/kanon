<?php
/**
 * upload.php — آپلود رسانه در Media Manager و آلبوم‌ها (تصویر، ویدئو، سند، فایل)
 * فیلدها: file (الزامی)، album_id، title، sub (camps|albums|images؛ فقط برای تصویر)
 * نوع فایل از روی محتوای واقعی تشخیص داده می‌شود (نه پسوند یا Content-Type کاربر).
 * Editor فقط می‌تواند در آلبوم‌های مجاز خودش آپلود کند.
 */
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('media');
csrf_check();
if (empty($_FILES['file'])) {
    out(false, 'فایلی ارسال نشد (حجم فایل از محدودیت PHP بیشتر است؟)', null, 422);
}
$sub     = in_array($_POST['sub'] ?? '', ['camps', 'albums', 'images'], true) ? $_POST['sub'] : 'images';
$albumId = (int)($_POST['album_id'] ?? 0) ?: null;
$pdo     = db();
if ($albumId) {
    $a = $pdo->prepare('SELECT id FROM albums WHERE id=?');
    $a->execute([$albumId]);
    if (!$a->fetchColumn()) {
        out(false, 'آلبوم پیدا نشد', null, 404);
    }
    if (!album_allowed($albumId)) {
        out(false, 'این آلبوم برای شما مجاز نیست', null, 403);
    }
}
[$type, $path, $mime, $size] = save_media_file($_FILES['file'], $sub);
$order = 0;
if ($albumId) {
    $q = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM media WHERE album_id=?');
    $q->execute([$albumId]);
    $order = (int)$q->fetchColumn();
}
try {
    $pdo->prepare('INSERT INTO media(album_id,type,path,mime,title,size,sort_order,created_by) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$albumId, $type, $path, $mime, mb_substr((string)($_POST['title'] ?? ''), 0, 200), $size, $order, (int)$user['id']]);
} catch (Throwable $x) {
    delete_upload($path); // خطای دیتابیس → فایل یتیم نماند
    throw $x;
}
$id = (int)$pdo->lastInsertId();
log_act('upload', $path);
out(true, 'آپلود شد', ['id' => $id, 'path' => $path, 'type' => $type]);
