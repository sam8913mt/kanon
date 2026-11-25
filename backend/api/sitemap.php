<?php
// sitemap.xml پویا: فقط صفحه‌های منتشرشده و معتبر
require __DIR__ . '/../includes/bootstrap.php';
header_remove('Content-Type');
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim($CFG['site_url'], '/');
$pdo  = db();
$urls = [['/', null], ['/camps', null], ['/albums', null], ['/news', null], ['/contact', null], ['/channels', null], ['/about', null]];
$sources = [
    'camp'  => 'SELECT slug, start_date AS d FROM camps WHERE published=1',
    'album' => 'SELECT slug, album_date AS d FROM albums WHERE published=1',
    'news'  => 'SELECT slug, DATE(created_at) AS d FROM news WHERE published=1',
    'page'  => "SELECT slug, NULL AS d FROM pages WHERE published=1 AND content<>'' AND slug<>'about'",
];
foreach ($sources as $type => $sql) {
    foreach ($pdo->query($sql) as $r) {
        if (is_valid_slug((string)$r['slug'])) {
            $urls[] = ["/$type/" . rawurlencode($r['slug']), $r['d']];
        }
    }
}
echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach ($urls as [$path, $date]) {
    echo '<url><loc>' . htmlspecialchars($base . $path, ENT_XML1) . '</loc>' . ($date ? '<lastmod>' . htmlspecialchars((string)$date, ENT_XML1) . '</lastmod>' : '') . '</url>';
}
echo '</urlset>';
