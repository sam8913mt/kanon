<?php
/** GET /api/profile/images.php — بخش «images» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('images', $v['member_id'], $v));
