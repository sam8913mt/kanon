<?php
// اخبار منتشرشده؛ با ?slug= فقط یک خبر
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$sql = 'SELECT slug,title,summary,content,cover,author,DATE(created_at) AS date FROM news WHERE published=1';
$p = [];
if (!empty($_GET['slug'])) {
    $sql .= ' AND slug=?';
    $p[] = $_GET['slug'];
}
$s = $pdo->prepare($sql . ' ORDER BY created_at DESC LIMIT 200');
$s->execute($p);
$rows = $s->fetchAll();
if (!empty($_GET['slug'])) {
    $rows ? out(true, '', $rows[0]) : out(false, 'خبر پیدا نشد', null, 404);
}
out(true, '', $rows);
