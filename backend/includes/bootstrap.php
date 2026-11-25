<?php
declare(strict_types=1);
/**
 * bootstrap.php — نقطه‌ی ورود مشترک همه‌ی APIها
 * ترتیب بارگذاری: تنظیمات ← خطا ← include ها ← نشست ← هدرها
 */
$CFG = require __DIR__ . '/../config/config.php';
const ROYESH_VERSION = '2.0.0-dev'; // هم‌نسخه با frontend/js/config.js

ini_set('display_errors', '0'); // هشدار/Deprecated نباید به JSON پاسخ API نفوذ کند (فقط در error.log)
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/error.log');

require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/permissions.php';
require __DIR__ . '/validation.php';
require __DIR__ . '/upload.php';
require __DIR__ . '/payments.php';
require __DIR__ . '/sms.php';
require __DIR__ . '/member_auth.php';
require __DIR__ . '/profile.php';
require __DIR__ . '/profile_write.php';

start_secure_session($CFG);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
