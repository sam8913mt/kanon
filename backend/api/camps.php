<?php
// لیست اردوهای منتشرشده (+ فرم)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_data.php';
out(true, '', public_camps(db()));
