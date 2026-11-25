<?php
/**
 * upload.php — آپلود امن فایل
 * - بررسی MIME واقعی (finfo) و امضای فایل، نه فقط پسوند
 * - نام تصادفی؛ پسوند فقط از روی MIME مجاز تعیین می‌شود (هرگز از نام کاربر)
 * - تصاویر با GD دوباره کدگذاری، کوچک و به webp تبدیل می‌شوند (حذف EXIF/payload پنهان + بهینه‌سازی حجم)
 */

function upload_fail(string $msg): void
{
    out(false, $msg, null, 422);
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        upload_fail('پوشه‌ی آپلود قابل ساخت نیست');
    }
}

/**
 * ذخیره‌ی تصویر در uploads/{sub}/ و برگرداندن مسیر نسبی
 * خروجی همیشه webp است (اگر GD از webp پشتیبانی کند)، حتی اگر ورودی JPG/PNG باشد.
 * $maxDim: بیشترین ضلع تصویر؛ برای عکس پروفایل/ثبت‌نام 600 و برای بقیه 1920.
 */
function save_image(array $f, string $sub, ?int $maxDim = null): string
{
    global $CFG;
    $u = $CFG['upload'];
    if (!isset($f['error'], $f['tmp_name'], $f['size']) || $f['error'] !== UPLOAD_ERR_OK
        || $f['size'] > $u['max_bytes'] || !is_uploaded_file($f['tmp_name'])) {
        upload_fail('فایل نامعتبر یا حجیم است');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($u['mimes'][$mime]) || !@getimagesize($f['tmp_name'])) {
        upload_fail('فقط تصویر JPG/PNG/WEBP مجاز است');
    }
    $toWebp = function_exists('imagewebp') && function_exists('imagecreatefromstring');
    $dir  = "{$u['dir']}/$sub";
    ensure_dir($dir);
    $base = bin2hex(random_bytes(16));
    $ext  = $toWebp ? 'webp' : $u['mimes'][$mime];
    $dest = "$dir/$base.$ext";
    $dim  = $maxDim ?? (int)($u['max_dimension'] ?? 1920);

    if (!reencode_image($f['tmp_name'], $dest, $mime, $dim, $toWebp)) {
        // GD در دسترس نیست یا ناموفق بود؛ همان فایلِ اعتبارسنجی‌شده را با پسوند درست منتقل می‌کنیم
        $ext  = $u['mimes'][$mime];
        $dest = "$dir/$base.$ext";
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            upload_fail('ذخیره‌ی فایل ناموفق بود');
        }
    }
    @chmod($dest, 0644);
    return "$sub/$base.$ext";
}

/** تبدیل تصویر پالتی به truecolor؛ روی GDهایی که imagepalettetruecolor ندارند، دستی کپی می‌کند */
function gd_to_truecolor($img)
{
    if (function_exists('imagepalettetruecolor')) {
        imagepalettetruecolor($img);
        return $img;
    }
    if (function_exists('imageistruecolor') && imageistruecolor($img)) {
        return $img;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $t = imagecreatetruecolor($w, $h);
    imagealphablending($t, false);
    imagesavealpha($t, true);
    imagecopy($t, $img, 0, 0, 0, 0, $w, $h);
    return $t;
}

/** جایگزین GD: Imagick (در صورت نصب‌بودن) — همان فرمت اصلی، بدون EXIF، با اصلاح چرخش و کاهش ابعاد */
function reencode_image_imagick(string $src, string $dest, string $mime, int $maxDim): bool
{
    if (!class_exists('Imagick')) {
        return false;
    }
    try {
        $im = new Imagick($src);
        $im->setIteratorIndex(0);
        if (method_exists($im, 'autoOrient')) {
            $im->autoOrient();
        }
        if (max($im->getImageWidth(), $im->getImageHeight()) > $maxDim) {
            $im->thumbnailImage($maxDim, $maxDim, true);
        }
        $im->stripImage();
        $fmt = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$fmt) {
            return false;
        }
        $im->setImageFormat($fmt);
        $im->setImageCompressionQuality($fmt === 'png' ? 90 : 85);
        $ok = $im->writeImage($fmt . ':' . $dest);
        $im->clear();
        return $ok && is_file($dest);
    } catch (Throwable $e) {
        return false;
    }
}

/** کدگذاری مجدد، اصلاح چرخش EXIF و کاهش ابعاد با GD؛ خروجی webp یا فرمت اصلی. true یعنی فایل مقصد ساخته شد. */
function reencode_image(string $src, string $dest, string $mime, int $maxDim, bool $toWebp = false): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return reencode_image_imagick($src, $dest, $mime, $maxDim);
    }
    $img = @imagecreatefromstring((string)file_get_contents($src));
    if (!$img) {
        return false;
    }
    // عکس‌های موبایل: چرخش را از EXIF بخوان (چون EXIF حذف می‌شود)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $ex  = @exif_read_data($src);
        $deg = [3 => 180, 6 => -90, 8 => 90][(int)($ex['Orientation'] ?? 1)] ?? 0;
        if ($deg && ($r = imagerotate($img, $deg, 0))) {
            $img = $r;
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if (max($w, $h) > $maxDim) {
        $scaled = $w >= $h ? imagescale($img, $maxDim) : imagescale($img, (int)round($w * $maxDim / $h), $maxDim);
        if ($scaled) {
            $img = $scaled;
        }
    }
    $ok = false;
    if ($toWebp) {
        $img = gd_to_truecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagewebp($img, $dest, 80);
    } elseif ($mime === 'image/jpeg') {
        $ok = imagejpeg($img, $dest, 85);
    } elseif ($mime === 'image/png') {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $dest, 6);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $ok = imagewebp($img, $dest, 82);
    }
    imagedestroy($img);
    return $ok;
}

/** فایل فیلد فرم: تصویر یا PDF (بررسی MIME و امضای %PDF) */
function save_field_file(array $f, string $type): string
{
    global $CFG;
    if ($type === 'file' && ($f['error'] ?? 1) === UPLOAD_ERR_OK && $f['size'] <= $CFG['upload']['max_bytes']
        && is_uploaded_file($f['tmp_name'])
        && (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) === 'application/pdf'
        && file_get_contents($f['tmp_name'], false, null, 0, 4) === '%PDF') {
        $dir = $CFG['upload']['dir'] . '/files';
        ensure_dir($dir);
        $n = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], "$dir/$n")) {
            upload_fail('ذخیره‌ی فایل ناموفق بود');
        }
        @chmod("$dir/$n", 0644);
        return "files/$n";
    }
    return save_image($f, 'images', (int)($CFG['upload']['profile_dimension'] ?? 600));
}

/** حذف فایل آپلودشده با مسیر نسبی امن (جلوگیری از Path Traversal) */
function delete_upload(?string $rel): void
{
    global $CFG;
    if ($rel && preg_match('#^(images|files|camps|albums|videos)/[\w.\-]+$#', $rel)) {
        $p = $CFG['upload']['dir'] . '/' . $rel;
        if (is_file($p)) {
            @unlink($p);
        }
    }
}


/* ───────────── فایل‌های خصوصی پروفایل (backend/storage/private) ───────────── */

function private_dir(): string
{
    return __DIR__ . '/../storage/private';
}

/**
 * ذخیره‌ی تصویر یا PDF خصوصی بیرون از مسیر عمومی؛ فقط با /api/profile/file.php و کنترل دسترسی قابل دریافت است.
 * خروجی: [مسیر نسبی، MIME، حجم]. تصاویر دوباره کدگذاری می‌شوند (حذف EXIF و payload پنهان).
 */
function save_private_file(array $f, bool $allowPdf = false, int $maxDim = 1600): array
{
    global $CFG;
    $u = $CFG['upload'];
    if (!isset($f['error'], $f['tmp_name'], $f['size']) || $f['error'] !== UPLOAD_ERR_OK
        || $f['size'] > $u['max_bytes'] || !is_uploaded_file($f['tmp_name'])) {
        upload_fail('فایل نامعتبر یا حجیم است');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $sub  = date('Y-m');
    ensure_dir(private_dir() . "/$sub");
    $base = bin2hex(random_bytes(16));

    if ($allowPdf && $mime === 'application/pdf' && file_get_contents($f['tmp_name'], false, null, 0, 4) === '%PDF') {
        $rel = "$sub/$base.pdf";
        if (!move_uploaded_file($f['tmp_name'], private_dir() . "/$rel")) {
            upload_fail('ذخیره‌ی فایل ناموفق بود');
        }
        return [$rel, 'application/pdf', (int)filesize(private_dir() . "/$rel")];
    }
    if (!isset($u['mimes'][$mime]) || !@getimagesize($f['tmp_name'])) {
        upload_fail($allowPdf ? 'فقط تصویر JPG/PNG/WEBP یا PDF مجاز است' : 'فقط تصویر JPG/PNG/WEBP مجاز است');
    }
    $toWebp = function_exists('imagewebp') && function_exists('imagecreatefromstring');
    $ext    = $toWebp ? 'webp' : $u['mimes'][$mime];
    $out    = $toWebp ? 'image/webp' : $mime;
    $rel    = "$sub/$base.$ext";
    if (!reencode_image($f['tmp_name'], private_dir() . "/$rel", $mime, $maxDim, $toWebp)) {
        $ext = $u['mimes'][$mime];
        $out = $mime;
        $rel = "$sub/$base.$ext";
        if (!move_uploaded_file($f['tmp_name'], private_dir() . "/$rel")) {
            upload_fail('ذخیره‌ی فایل ناموفق بود');
        }
    }
    @chmod(private_dir() . "/$rel", 0640);
    return [$rel, $out, (int)filesize(private_dir() . "/$rel")];
}

/** مسیر کامل یک فایل خصوصی (فقط الگوی مجاز؛ جلوگیری از Path Traversal) یا null */
function private_path(string $rel): ?string
{
    if (!preg_match('#^\d{4}-\d{2}/[a-f0-9]{32}\.(webp|jpg|png|pdf)$#', $rel)) {
        return null;
    }
    $p = private_dir() . '/' . $rel;
    return is_file($p) ? $p : null;
}


/* ───────────── رسانه‌ی عمومی: تصویر، ویدئو، سند و فایل (Media Manager و آلبوم) ───────────── */

/** پسوند مجاز ← [نوع رسانه، MIME‌های مجاز، فایل شاخص داخل ZIP برای Office (null = بدون بررسی)] */
const MEDIA_FILE_TYPES = [
    'pdf'  => ['document', ['application/pdf'], null],
    'txt'  => ['document', ['text/plain'], null],
    'docx' => ['document', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], 'word/document.xml'],
    'xlsx' => ['document', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'], 'xl/workbook.xml'],
    'pptx' => ['document', ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'], 'ppt/presentation.xml'],
    'zip'  => ['file', ['application/zip'], null],
];
const MEDIA_VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

/** انتقال فایل اعتبارسنجی‌شده با نام تصادفی و پسوند مجاز (هرگز از نام کاربر) → [نوع، مسیر نسبی، MIME، حجم] */
function move_media_file(array $f, string $sub, string $ext, string $type, string $mime): array
{
    global $CFG;
    $dir = $CFG['upload']['dir'] . "/$sub";
    ensure_dir($dir);
    $name = bin2hex(random_bytes(16)) . ".$ext";
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) {
        upload_fail('ذخیره‌ی فایل ناموفق بود');
    }
    @chmod("$dir/$name", 0644);
    return [$type, "$sub/$name", $mime, (int)filesize("$dir/$name")];
}

/**
 * ذخیره‌ی یک فایل رسانه با تشخیص نوع از روی محتوای واقعی (MIME + امضا + پسوند مجاز).
 * خروجی: [type(image|video|document|file), path, mime, size]
 *  - تصویر: JPG/PNG/WEBP (کدگذاری مجدد و webp) تا max_bytes
 *  - ویدئو: MP4/WEBM تا video_max_bytes (پیش‌فرض ۵۰ مگابایت؛ سقف واقعی را upload_max_filesize و post_max_size PHP هم تعیین می‌کنند)
 *  - سند/فایل: PDF, TXT, DOCX, XLSX, PPTX, ZIP تا file_max_bytes (پیش‌فرض ۱۰ مگابایت)
 */
function save_media_file(array $f, string $sub = 'images'): array
{
    global $CFG;
    $u = $CFG['upload'];
    if (!isset($f['error'], $f['tmp_name'], $f['size'], $f['name']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        upload_fail('فایل نامعتبر یا حجیم است (سقف آپلود سرور را در تنظیمات PHP بررسی کنید)');
    }
    $fi   = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$fi->file($f['tmp_name']);
    $ext  = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));

    if (isset($u['mimes'][$mime])) { // تصویر
        $path = save_image($f, $sub);
        $full = $u['dir'] . '/' . $path;
        return ['image', $path, (string)($fi->file($full) ?: 'image/webp'), (int)filesize($full)];
    }

    if (isset(MEDIA_VIDEO_TYPES[$mime])) { // ویدئو
        if ($f['size'] > (int)($u['video_max_bytes'] ?? 50 * 1048576)) {
            upload_fail('حجم ویدئو بیشتر از حد مجاز است؛ از «افزودن لینک ویدئو» استفاده کنید');
        }
        $head  = (string)file_get_contents($f['tmp_name'], false, null, 0, 12);
        $sigOk = $mime === 'video/mp4' ? substr($head, 4, 4) === 'ftyp' : strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0;
        if (!$sigOk || $ext !== MEDIA_VIDEO_TYPES[$mime]) {
            upload_fail('فایل ویدئو معتبر نیست (فقط MP4 یا WEBM)');
        }
        return move_media_file($f, 'videos', MEDIA_VIDEO_TYPES[$mime], 'video', $mime);
    }

    $def = MEDIA_FILE_TYPES[$ext] ?? null; // سند / فایل
    if ($def && in_array($mime, $def[1], true)) {
        if ($f['size'] > (int)($u['file_max_bytes'] ?? 10 * 1048576)) {
            upload_fail('حجم فایل بیشتر از حد مجاز است');
        }
        $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 4096);
        if ($ext === 'pdf' && strncmp($head, '%PDF', 4) !== 0) {
            upload_fail('فایل PDF معتبر نیست');
        }
        if ($ext === 'txt' && strpos($head, "\0") !== false) {
            upload_fail('فایل متنی معتبر نیست');
        }
        if (in_array($ext, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
            if (strncmp($head, "PK\x03\x04", 4) !== 0) {
                upload_fail('ساختار فایل معتبر نیست');
            }
            if ($def[2] && class_exists('ZipArchive')) {
                $z = new ZipArchive();
                $okZip = $z->open($f['tmp_name']) === true && $z->locateName($def[2]) !== false;
                if ($okZip) {
                    $z->close();
                }
                if (!$okZip) {
                    upload_fail('ساختار فایل Office معتبر نیست');
                }
            }
        }
        return move_media_file($f, 'files', $ext, $def[0], $mime);
    }
    upload_fail('نوع فایل مجاز نیست (تصویر JPG/PNG/WEBP، ویدئو MP4/WEBM، سند PDF/DOCX/XLSX/PPTX/TXT یا ZIP)');
}

/**
 * اعتبارسنجی آدرس ویدئوی خارجی. فقط https و یکی از:
 *  - آپارات، یوتیوب، ویمیو → آدرس Embed امن (iframe؛ در CSP فقط همین میزبان‌ها مجازند)
 *  - فایل مستقیم .mp4 / .webm → پخش با <video>
 * خروجی: ['kind' => 'embed'|'file', 'src' => …] یا null
 */
function video_source(?string $url): ?array
{
    $url = trim((string)$url);
    if ($url === '' || mb_strlen($url) > 500 || !preg_match('#^https://#i', $url) || preg_match('/[\s"\'<>\\\\]/u', $url)) {
        return null;
    }
    $p = parse_url($url);
    if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
        return null;
    }
    $host = preg_replace('/^www\./', '', strtolower((string)$p['host']));
    $path = (string)($p['path'] ?? '');
    $id   = '';
    if ($host === 'aparat.com' && preg_match('#^/v/([A-Za-z0-9]{3,20})#', $path, $m)) {
        return ['kind' => 'embed', 'src' => "https://www.aparat.com/video/video/embed/videohash/{$m[1]}/vt/frame"];
    }
    if ($host === 'youtu.be' && preg_match('#^/([\w\-]{6,20})$#', $path, $m)) {
        $id = $m[1];
    } elseif (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
        if ($path === '/watch') {
            parse_str((string)($p['query'] ?? ''), $q);
            $id = (string)($q['v'] ?? '');
        } elseif (preg_match('#^/(?:embed|shorts)/([\w\-]{6,20})#', $path, $m)) {
            $id = $m[1];
        }
    }
    if ($id !== '' && preg_match('/^[\w\-]{6,20}$/', $id)) {
        return ['kind' => 'embed', 'src' => "https://www.youtube-nocookie.com/embed/$id"];
    }
    if ($host === 'vimeo.com' && preg_match('#^/(\d{5,12})#', $path, $m)) {
        return ['kind' => 'embed', 'src' => "https://player.vimeo.com/video/{$m[1]}"];
    }
    if (preg_match('/\.(mp4|webm)$/i', $path)) {
        return ['kind' => 'file', 'src' => $url];
    }
    return null;
}

/** ردیف رسانه برای نمایش عمومی (بدون فیلدهای داخلی) */
function media_public_row(array $m): array
{
    $o = ['type' => $m['type'], 'path' => (string)($m['path'] ?? ''), 'title' => (string)($m['title'] ?? ''),
          'mime' => $m['mime'] ?? null, 'size' => (int)($m['size'] ?? 0)];
    if ($m['type'] === 'video' && !empty($m['url'])) {
        $v = video_source((string)$m['url']);
        if ($v) {
            if ($v['kind'] === 'embed') {
                $o['embed'] = $v['src'];
                $o['path']  = '';
            } else {
                $o['path'] = $v['src'];
            }
        }
    }
    return $o;
}

/* ───────────── فایل‌های ثبت‌نام اردو (عکس دانش‌آموز و پاسخ‌های فایل/تصویر) ───────────── */

/** ارجاع معتبر به فایل ثبت‌نام: خصوصی (جدید: YYYY-MM/hex.ext) یا قدیمی عمومی (images|files/…) */
function is_reg_file_ref(?string $v): bool
{
    $v = (string)$v;
    return (bool)preg_match('#^(images|files)/[\w.\-]+$#', $v) || (bool)preg_match('#^\d{4}-\d{2}/[a-f0-9]{32}\.(webp|jpg|png|pdf)$#', $v);
}

/** ذخیره‌ی خصوصی فایل ثبت‌نام (بیرون از مسیر عمومی؛ فقط با /api/reg_file.php و کنترل دسترسی). خروجی: مسیر نسبی */
function save_reg_file(array $f, bool $allowPdf = false): string
{
    global $CFG;
    return save_private_file($f, $allowPdf, (int)($CFG['upload']['profile_dimension'] ?? 600))[0];
}

/** مسیر کامل فایل ثبت‌نام روی دیسک یا null (فقط الگوهای مجاز؛ جلوگیری از Path Traversal) */
function reg_file_path(string $rel): ?string
{
    global $CFG;
    if (preg_match('#^\d{4}-\d{2}/#', $rel)) {
        return private_path($rel);
    }
    if (preg_match('#^(images|files)/[\w.\-]+$#', $rel)) {
        $p = $CFG['upload']['dir'] . '/' . $rel;
        return is_file($p) ? $p : null;
    }
    return null;
}

/** حذف فایل ثبت‌نام (خصوصی یا قدیمیِ عمومی) */
function delete_reg_file(?string $rel): void
{
    if (!$rel) {
        return;
    }
    if (preg_match('#^\d{4}-\d{2}/#', $rel)) {
        $p = private_path($rel);
        if ($p) {
            @unlink($p);
        }
        return;
    }
    delete_upload($rel);
}


/* ───────────── حذف کامل (فیزیکی) ───────────── */

/** حذف فایل عمومی فقط اگر هیچ ردیف دیگری (رسانه، کاور، تنظیمات، SEO) هنوز به آن اشاره نکند */
function purge_public_file(PDO $pdo, ?string $rel): void
{
    if (!$rel || !preg_match('#^(images|files|camps|albums|videos)/[\w.\-]+$#', $rel)) {
        return;
    }
    foreach (['media' => 'path', 'camps' => 'cover', 'albums' => 'cover', 'news' => 'cover', 'heyats' => 'image', 'seo' => 'og_image', 'settings' => 'v'] as $t => $c) {
        try {
            $q = $pdo->prepare("SELECT 1 FROM `$t` WHERE `$c`=? LIMIT 1");
            $q->execute([$rel]);
            if ($q->fetchColumn()) {
                return;
            }
        } catch (Throwable $e) {
        }
    }
    delete_upload($rel);
}

/**
 * حذف کامل ثبت‌نام‌های مطابق شرط (ردیف‌ها + پاسخ‌ها). فایل‌ها پاک نمی‌شوند؛
 * فهرست آن‌ها برگردانده می‌شود تا بعد از commit با delete_reg_file حذف شوند. باید داخل تراکنش صدا زده شود.
 */
function purge_registrations(PDO $pdo, string $where, array $params): array
{
    $q = $pdo->prepare("SELECT id,photo FROM registrations WHERE $where");
    $q->execute($params);
    $files = [];
    foreach ($q->fetchAll() as $r) {
        if ($r['photo']) {
            $files[] = $r['photo'];
        }
        $a = $pdo->prepare('SELECT answer FROM registration_answers WHERE registration_id=?');
        $a->execute([$r['id']]);
        $files = array_merge($files, array_filter($a->fetchAll(PDO::FETCH_COLUMN), 'is_reg_file_ref'));
        $rc = $pdo->prepare('SELECT file FROM payment_receipts WHERE registration_id=?');
        $rc->execute([$r['id']]);
        $files = array_merge($files, $rc->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('DELETE FROM registrations WHERE id=?')->execute([$r['id']]);
    }
    return $files;
}

/** حذف کامل کاربر عمومی: پروفایل، مدارک (به‌همراه فایل‌ها)، ثبت‌نام‌ها و همه‌ی سوابق وابسته */
function purge_member(PDO $pdo, int $mid): void
{
    $files = [];
    $pdo->beginTransaction();
    try {
        $d = $pdo->prepare('SELECT path FROM member_documents WHERE member_id=?');
        $d->execute([$mid]);
        $files = $d->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('UPDATE member_profiles SET avatar_doc_id=NULL WHERE member_id=?')->execute([$mid]);
        $files = array_merge($files, purge_registrations($pdo, 'member_id=?', [$mid]));
        $pdo->prepare('DELETE FROM members WHERE id=?')->execute([$mid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    foreach ($files as $f) {
        delete_reg_file($f);
    }
}
