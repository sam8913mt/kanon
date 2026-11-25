<?php
/**
 * media.php — Media Manager: لیست/جستجو/فیلتر/مرتب‌سازی، تغییر نام، جایگزینی، حذف، کاور، منتخب، افزودن لینک ویدئو
 * انواع: image | video | document | file.
 * Editor فقط رسانه‌ی آلبوم‌های مجاز خودش و رسانه‌ای را که خودش ساخته می‌بیند و تغییر می‌دهد (کنترل در همین فایل، نه فقط UI).
 */
require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('media');
$pdo  = db();
const MEDIA_TYPES = ['image', 'video', 'document', 'file'];

/** یک رسانه را می‌خواند و دسترسی کاربر جاری را بررسی می‌کند (۴۰۴ اگر نیست، ۴۰۳ اگر مجاز نیست) */
function media_get(PDO $pdo, int $id): array
{
    $s = $pdo->prepare('SELECT id,album_id,type,path,url,mime,title,size,created_by FROM media WHERE id=?');
    $s->execute([$id]);
    $m = $s->fetch();
    if (!$m) {
        out(false, 'یافت نشد', null, 404);
    }
    if (!media_allowed($m)) {
        out(false, 'این رسانه برای شما مجاز نیست', null, 403);
    }
    return $m;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $where  = [camp_scope_sql('media_own')];
    $params = [];
    if (!empty($_GET['q'])) {
        $where[]  = '(title LIKE ? OR path LIKE ? OR url LIKE ?)';
        $like     = '%' . $_GET['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (in_array($_GET['type'] ?? '', MEDIA_TYPES, true)) {
        $where[]  = 'type=?';
        $params[] = $_GET['type'];
    }
    if (isset($_GET['album_id']) && $_GET['album_id'] !== '') {
        $where[]  = 'album_id=?';
        $params[] = (int)$_GET['album_id'];
    }
    $order = ['old' => 'id ASC', 'size' => 'size DESC', 'name' => 'title ASC', 'order' => 'sort_order,id'][$_GET['sort'] ?? ''] ?? 'id DESC';
    $s = $pdo->prepare('SELECT id,album_id,type,path,url,mime,title,size,sort_order,featured,created_at FROM media WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT 500");
    $s->execute($params);
    out(true, '', $s->fetchAll());
}

csrf_check();

// جایگزینی فایل یک رسانه (multipart: file + id) — نوع فایل جدید باید با نوع قبلی یکی باشد
if (!empty($_FILES['file'])) {
    $id  = (int)($_POST['id'] ?? 0);
    $old = media_get($pdo, $id);
    if ($old['type'] === 'video' && !empty($old['url'])) {
        out(false, 'ویدئوی خارجی فایل ندارد؛ حذف و دوباره اضافه کنید', null, 422);
    }
    $first = explode('/', (string)$old['path'])[0];
    [$type, $new, $mime, $size] = save_media_file($_FILES['file'], in_array($first, ['camps', 'albums', 'images'], true) ? $first : 'images');
    if ($type !== $old['type']) {
        delete_upload($new);
        out(false, 'نوع فایل جدید با فایل قبلی یکی نیست', null, 422);
    }
    $pdo->prepare('UPDATE media SET path=?,mime=?,size=? WHERE id=?')->execute([$new, $mime, $size, $id]);
    if ($old['path'] !== $new) {
        $pdo->prepare('UPDATE albums SET cover=? WHERE cover=?')->execute([$new, $old['path']]); // کاور آلبوم با فایل جدید هماهنگ بماند
    }
    delete_upload($old['path']);
    log_act('media_replace', (string)$id);
    out(true, 'جایگزین شد', ['path' => $new]);
}

$d  = json_input();
$id = (int)($d['id'] ?? 0);
$op = $d['op'] ?? '';

if ($op === 'add_video_url') { // ویدئوی خارجی (آپارات، یوتیوب، ویمیو یا فایل مستقیم mp4/webm)
    $src = video_source((string)($d['url'] ?? ''));
    if (!$src) {
        out(false, 'آدرس ویدئو معتبر نیست. فقط https و آپارات/یوتیوب/ویمیو یا فایل مستقیم MP4/WEBM مجاز است', null, 422);
    }
    $albumId = (int)($d['album_id'] ?? 0) ?: null;
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
    $order = 0;
    if ($albumId) {
        $q = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM media WHERE album_id=?');
        $q->execute([$albumId]);
        $order = (int)$q->fetchColumn();
    }
    $pdo->prepare("INSERT INTO media(album_id,type,path,url,mime,title,size,sort_order,created_by) VALUES(?,'video','',?,NULL,?,0,?,?)")
        ->execute([$albumId, trim((string)$d['url']), mb_substr((string)($d['title'] ?? ''), 0, 200), $order, (int)$user['id']]);
    $newId = (int)$pdo->lastInsertId();
    log_act('media_add_video_url', (string)$newId);
    out(true, 'ویدئو اضافه شد', ['id' => $newId]);
}

$m = media_get($pdo, $id); // همه‌ی عملیات زیر: وجود + مجاز بودن

if ($op === 'rename') {
    $pdo->prepare('UPDATE media SET title=? WHERE id=?')->execute([mb_substr((string)($d['title'] ?? ''), 0, 200), $id]);
    log_act('media_rename', (string)$id);
    out(true, 'نام تغییر کرد');
}
if ($op === 'delete') {
    $pdo->prepare('DELETE FROM media WHERE id=?')->execute([$id]);
    delete_upload($m['path']);
    if ($m['type'] === 'image' && $m['path'] !== '') {
        $pdo->prepare('UPDATE albums SET cover=NULL WHERE cover=?')->execute([$m['path']]); // کاور به فایل حذف‌شده اشاره نکند
    }
    log_act('delete_media', (string)$id);
    out(true, 'حذف شد');
}
if ($op === 'set_cover') { // انتخاب تصویر به‌عنوان کاور آلبوم
    if ($m['type'] !== 'image' || !$m['album_id']) {
        out(false, 'فقط تصویرِ عضو یک آلبوم می‌تواند کاور شود', null, 422);
    }
    $pdo->prepare('UPDATE albums SET cover=? WHERE id=?')->execute([$m['path'], $m['album_id']]);
    log_act('album_cover', (string)$m['album_id']);
    out(true, 'کاور آلبوم تنظیم شد');
}
if ($op === 'feature') { // نمایش در «منتخب‌ها»ی صفحه‌ی اصلی (تصویر و ویدئو)
    if (!in_array($m['type'], ['image', 'video'], true)) {
        out(false, 'فقط تصویر و ویدئو قابل انتخاب به‌عنوان منتخب است', null, 422);
    }
    $pdo->prepare('UPDATE media SET featured=? WHERE id=?')->execute([empty($d['value']) ? 0 : 1, $id]);
    log_act('media_feature', (string)$id);
    out(true, 'ذخیره شد');
}
out(false, 'عملیات نامعتبر', null, 422);
