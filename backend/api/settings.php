<?php
// تنظیمات عمومی سایت (بدون اطلاعات حساس)
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public_data.php';
out(true, '', public_settings(db()));
