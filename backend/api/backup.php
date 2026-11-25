<?php
/**
 * backup.php — پشتیبان‌گیری (فقط Admin)
 * ?type=files → ZIP فایل‌ها با ساختار نسبت به ریشه‌ی سایت (بازیابی = استخراج در همان ریشه):
 *     uploads/…                      ← پوشه‌ی uploads (تصویر، ویدئو، سند)
 *     backend/storage/private/…      ← فایل‌های خصوصی (پروفایل و ثبت‌نام)
 *     BACKUP-INFO.txt                ← نسخه، تاریخ و راهنمای بازیابی
 * بدون پارامتر → دیتابیس به‌صورت SQL (استریم، سازگار با جداول بزرگ)
 */
require __DIR__ . '/../includes/bootstrap.php';
require_role('backup');
$p = db();
@set_time_limit(0);

if (($_GET['type'] ?? '') === 'files') {
    require __DIR__ . '/../includes/zipwriter.php';
    $tmp = tempnam(sys_get_temp_dir(), 'bk');
    $zip = make_zip($tmp);
    if (!$zip) {
        out(false, 'ساخت فایل ZIP ممکن نیست', null, 500);
    }
    $zip->addFromString('BACKUP-INFO.txt', "Royesh " . ROYESH_VERSION . " files backup — " . date('Y-m-d H:i:s') . "\n"
        . "Restore: extract this ZIP in the site root (public_html). It restores uploads/ and backend/storage/private/.\n"
        . "Import the matching SQL backup separately (phpMyAdmin → Import). .htaccess files are not included; keep the ones already in place.\n");
    $root = realpath($CFG['upload']['dir']);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && !in_array($f->getFilename(), ['.htaccess', '.gitkeep'], true)) {
            $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1)));
        }
    }
    $priv = realpath(__DIR__ . '/../storage/private');
    if ($priv) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($priv, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && !in_array($f->getFilename(), ['.htaccess', '.gitkeep'], true)) {
                $zip->addFile($f->getPathname(), 'backend/storage/private/' . str_replace('\\', '/', substr($f->getPathname(), strlen($priv) + 1)));
            }
        }
    }
    $zip->close();
    log_act('backup_files');
    header_remove('Content-Type');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="uploads-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    unlink($tmp);
    exit;
}

header_remove('Content-Type');
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="backup-' . date('Y-m-d-His') . '.sql"');
$p->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false); // ردیف‌به‌ردیف، بدون پر کردن حافظه
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
$tables = $p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    $create = $p->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    echo "DROP TABLE IF EXISTS `$t`;\n$create;\n";
    $batch = [];
    foreach ($p->query("SELECT * FROM `$t`") as $r) {
        $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $p->quote((string)$v), array_values($r))) . ')';
        if (count($batch) >= 100) {
            echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
            $batch = [];
        }
    }
    if ($batch) {
        echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
    }
}
echo "SET FOREIGN_KEY_CHECKS=1;\n";
log_act('backup');
