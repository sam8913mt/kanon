<?php
/** GET /api/profile/activities.php — بخش «activities» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('activities', $v['member_id'], $v));
