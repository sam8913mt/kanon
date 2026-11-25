<?php
/** GET /api/profile/registrations.php[?reg=ID] — فهرست ثبت‌نام‌ها یا جزئیات یک ثبت‌نام (پاسخ‌ها، عکس، وضعیت، یادداشت مجاز) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('registrations', $v['member_id'], $v));
