<?php
/** GET /api/profile/courses.php — بخش «courses» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('courses', $v['member_id'], $v));
