<?php
// هیئت‌های منتشرشده؛ زمان برگزاری به‌وقت ایران (+03:30) برای شمارشگر معکوس
require __DIR__ . '/../includes/bootstrap.php';
$rows = db()->query("SELECT id,title,caption,image,event_date AS date,TIME_FORMAT(event_time,'%H:%i') AS time,occasion
    FROM heyats WHERE published=1 ORDER BY event_date DESC, event_time DESC, id DESC LIMIT 200")->fetchAll();
foreach ($rows as &$r) {
    $r['at'] = $r['date'] ? $r['date'] . 'T' . ($r['time'] ?: '00:00') . ':00+03:30' : null;
}
out(true, '', $rows);
