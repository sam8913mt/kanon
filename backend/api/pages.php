<?php
// صفحه‌ی عمومی با slug (about، rules، privacy، …)
require __DIR__ . '/../includes/bootstrap.php';
$s = db()->prepare('SELECT slug,title,content FROM pages WHERE slug=? AND published=1');
$s->execute([(string)($_GET['slug'] ?? '')]);
$r = $s->fetch();
$r ? out(true, '', $r) : out(false, 'صفحه پیدا نشد', null, 404);
