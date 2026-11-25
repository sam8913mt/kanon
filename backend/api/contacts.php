<?php
// اطلاعات تماس فعال
require __DIR__ . '/../includes/bootstrap.php';
out(true, '', db()->query("SELECT label,value,kind FROM contacts WHERE enabled=1 AND value<>'' ORDER BY sort_order,id")->fetchAll());
