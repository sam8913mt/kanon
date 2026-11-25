<?php
// یک اردو با slug: /api/camp.php?slug=mashhad-1403
// پیش‌نمایش: ادمین (و ویرایشگرِ مجاز همان اردو) می‌تواند اردوی پیش‌نویس را هم ببیند؛ برای عموم فقط اردوهای منتشرشده.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_data.php';
$slug = (string)($_GET['slug'] ?? '');
$pdo  = db();
$r    = public_camps($pdo, $slug);
if (!$r) {
    $u = current_user();
    $canPreview = false;
    if ($u && $slug !== '') {
        if (($u['role'] ?? '') === 'admin') {
            $canPreview = true;
        } else {
            $q = $pdo->prepare('SELECT 1 FROM camps c JOIN camp_editors e ON e.camp_id=c.id WHERE c.slug=? AND e.user_id=?');
            $q->execute([$slug, $u['id'] ?? 0]);
            $canPreview = (bool)$q->fetchColumn();
        }
    }
    if ($canPreview) {
        $r = public_camps($pdo, $slug, true);
    }
}
$r ? out(true, '', $r[0]) : out(false, 'اردو پیدا نشد', null, 404);
