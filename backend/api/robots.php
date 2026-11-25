<?php
// robots.txt پویا (آدرس سایت از config.php)
$CFG = require __DIR__ . '/../config/config.php';
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim($CFG['site_url'], '/');
echo "User-agent: *\n"
   . "Disallow: /api/\nDisallow: /admin/\nDisallow: /backend/\nDisallow: /database/\nDisallow: /login\nDisallow: /signup\nDisallow: /profile\nDisallow: /dashboard\nDisallow: /complete-profile\nDisallow: /docs/\n"
   . "Disallow: /camp/*/register\n"
   . "Sitemap: $base/sitemap.xml\n";
