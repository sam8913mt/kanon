<?php
/**
 * آلبوم‌ها
 *  - لیست:          /api/albums.php
 *  - یک آلبوم:      /api/albums.php?slug=mashhad-1403   (با رسانه‌ها)
 *  - منتخب‌ها:      /api/albums.php?featured=1          (تصاویر و ویدئوهای منتخب برای صفحه‌ی اصلی)
 *  - رسانه‌ی هر آلبوم: media (تصویر/ویدئو) و files (سند/فایل برای دانلود)
 */
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();

if (isset($_GET['featured'])) {
    // تصاویر منتخب (۸ مورد؛ منتخب‌ها اول) و ویدئوهای منتخب (فقط آن‌هایی که Admin منتخب کرده)
    $sel = 'SELECT m.type,m.path,m.url,m.mime,m.title,m.size,a.slug AS album FROM media m JOIN albums a ON a.id=m.album_id WHERE a.published=1 AND ';
    $imgs = $pdo->query($sel . "m.type='image' ORDER BY m.featured DESC, m.id DESC LIMIT 8")->fetchAll();
    $vids = $pdo->query($sel . "m.type='video' AND m.featured=1 ORDER BY m.id DESC LIMIT 6")->fetchAll();
    $pub = fn(array $r) => media_public_row($r) + ['album' => $r['album']];
    out(true, '', ['images' => array_map($pub, $imgs), 'videos' => array_map($pub, $vids)]);
}

if (isset($_GET['home'])) {
    // صفحه‌ی اصلی: برای هر آلبوم منتشرشده یک اسلایدر (حداکثر ۱۰ آلبوم × ۱۲ تصویر)
    $out = [];
    $im = $pdo->prepare("SELECT type,path,url,mime,title,size FROM media WHERE album_id=? AND type='image' ORDER BY sort_order,id LIMIT 12");
    foreach ($pdo->query('SELECT id,slug,title FROM albums WHERE published=1 ORDER BY album_date DESC, id DESC LIMIT 10')->fetchAll() as $a) {
        $im->execute([$a['id']]);
        $imgs = array_map('media_public_row', $im->fetchAll());
        if ($imgs) {
            $out[] = ['slug' => $a['slug'], 'title' => $a['title'], 'images' => $imgs];
        }
    }
    out(true, '', $out);
}

if (!empty($_GET['slug'])) {
    $s = $pdo->prepare('SELECT id,slug,title,description,album_date AS date,cover FROM albums WHERE slug=? AND published=1');
    $s->execute([$_GET['slug']]);
    $a = $s->fetch();
    if (!$a) {
        out(false, 'آلبوم پیدا نشد', null, 404);
    }
    // گالری: تصویر و ویدئو؛ اسناد و فایل‌ها جدا به‌صورت لینک دانلود
    $m = $pdo->prepare("SELECT type,path,url,mime,title,size FROM media WHERE album_id=? AND type IN ('image','video') ORDER BY sort_order,id");
    $m->execute([$a['id']]);
    $a['media'] = array_map('media_public_row', $m->fetchAll());
    $f = $pdo->prepare("SELECT type,path,url,mime,title,size FROM media WHERE album_id=? AND type IN ('document','file') ORDER BY sort_order,id");
    $f->execute([$a['id']]);
    $a['files'] = array_map('media_public_row', $f->fetchAll());
    unset($a['id']);
    out(true, '', $a);
}
out(true, '', $pdo->query('SELECT slug,title,description,album_date AS date,cover FROM albums WHERE published=1 ORDER BY album_date DESC, id DESC')->fetchAll());
