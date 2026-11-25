<?php
// کانال‌ها و شبکه‌های اجتماعی فعال
require __DIR__ . '/../includes/bootstrap.php';
out(true, '', db()->query('SELECT title,url,icon FROM channels WHERE enabled=1 ORDER BY sort_order,id')->fetchAll());
