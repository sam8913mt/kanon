<?php
/** GET /api/profile/timeline.php — بخش «timeline» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('timeline', $v['member_id'], $v));
