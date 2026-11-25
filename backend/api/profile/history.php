<?php
/** GET /api/profile/history.php — بخش «history» پروفایل خود کاربر (فقط‌خواندنی) */
require __DIR__ . '/../../includes/bootstrap.php';
$v = pf_self();
out(true, '', pf_section('history', $v['member_id'], $v));
