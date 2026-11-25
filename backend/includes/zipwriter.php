<?php
/**
 * zipwriter.php — ساخت ZIP بدون افزونه‌ی ZipArchive (جایگزین خودکار؛ فقط نوشتن).
 * فایل‌های بزرگ بدون فشرده‌سازی (Store) و جریانی کپی می‌شوند؛ متن‌ها با zlib فشرده می‌شوند (در صورت وجود).
 */
class SimpleZip
{
    private $fh;
    private array $cd = [];
    private int $off = 0;

    public function __construct(string $path)
    {
        $this->fh = fopen($path, 'wb');
        if (!$this->fh) {
            throw new RuntimeException('cannot open zip');
        }
    }

    private function w(string $d): void
    {
        fwrite($this->fh, $d);
        $this->off += strlen($d);
    }

    private function dt(int $t): array
    {
        $d = getdate($t);
        $y = max($d['year'], 1980);
        return [($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2), (($y - 1980) << 9) | ($d['mon'] << 5) | $d['mday']];
    }

    private function header(string $name, int $crc, int $csize, int $size, int $method, int $time): void
    {
        [$tm, $dd] = $this->dt($time);
        $this->cd[] = [$name, $crc, $csize, $size, $method, $tm, $dd, $this->off];
        $this->w(pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $method, $tm, $dd, $crc, $csize, $size, strlen($name), 0) . $name);
    }

    public function addFromString(string $name, string $data): void
    {
        $crc = crc32($data);
        $size = strlen($data);
        $method = 0;
        if (function_exists('gzdeflate') && $size > 64 && ($z = gzdeflate($data, 6)) !== false && strlen($z) < $size) {
            $data = $z;
            $method = 8;
        }
        $this->header($name, $crc & 0xffffffff, strlen($data), $size, $method, time());
        $this->w($data);
    }

    public function addFile(string $path, string $name): bool
    {
        $in = @fopen($path, 'rb');
        if (!$in) {
            return false;
        }
        $size = (int)filesize($path);
        $crc = (int)hexdec((string)hash_file('crc32b', $path));
        $this->header($name, $crc, $size, $size, 0, (int)filemtime($path));
        while (!feof($in)) {
            $this->w((string)fread($in, 1048576));
        }
        fclose($in);
        return true;
    }

    public function close(): void
    {
        $start = $this->off;
        foreach ($this->cd as [$n, $crc, $cs, $s, $m, $tm, $dd, $o]) {
            $this->w(pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $m, $tm, $dd, $crc, $cs, $s, strlen($n), 0, 0, 0, 0, 0, $o) . $n);
        }
        $size = $this->off - $start;
        $this->w(pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->cd), count($this->cd), $size, $start, 0));
        fclose($this->fh);
    }
}

/** ZIP ساز یکپارچه: ZipArchive اگر بود، وگرنه SimpleZip. هر دو addFromString/addFile/close دارند. */
function make_zip(string $path)
{
    if (class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) === true) {
            return $z;
        }
        return null;
    }
    try {
        return new SimpleZip($path);
    } catch (Throwable $e) {
        return null;
    }
}
