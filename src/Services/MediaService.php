<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Nimmt Galerie-Uploads (Bilder + Videos) entgegen, legt sie unter
 * storage/media/ ab (außerhalb des Webroots) und erzeugt Vorschau-Varianten.
 *
 *  - Originaldatei bleibt unangetastet (EXIF/Metadaten bleiben drin).
 *  - HEIC/HEIF wird beim Upload zu JPEG umgewandelt (GD kann HEIC nicht),
 *    damit die Bilder im Browser nutzbar sind.
 *  - Thumbnail (klein) + Web-Größe (mittel) via GD; fehlt GD, wird das
 *    Original direkt angezeigt.
 *  - Videos: nur ablegen. Ein Vorschaubild liefert der Browser beim Upload
 *    (erster Frame) mit – serverseitig ist ohne ffmpeg keins möglich.
 */
final class MediaService
{
    /** Rückfall, falls in config/config.php kein `media`-Block steht. */
    public const DEFAULT_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
    public const DEFAULT_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = dirname(__DIR__, 2) . '/storage/media';
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    /** Absoluter Pfad zu einem gespeicherten Medienpfad (nie aus Nutzereingabe). */
    public function absolutePath(string $relative): ?string
    {
        $relative = ltrim($relative, '/');
        $full = $this->baseDir . '/' . $relative;
        $real = realpath($full);
        if ($real === false || !str_starts_with($real, $this->baseDir . '/')) {
            return null;
        }

        return $real;
    }

    /** Was kann dieser Server? – für die „Voraussetzungen"-Anzeige in der Verwaltung. */
    public function capabilities(): array
    {
        $gd = extension_loaded('gd');
        $gdInfo = $gd ? gd_info() : [];

        return [
            'gd' => $gd,
            'gd_webp' => $gd && (bool) ($gdInfo['WebP Support'] ?? false),
            'imagick' => extension_loaded('imagick'),
            'exif' => extension_loaded('exif'),
            'convert' => $this->convertBin() !== null,
            'heic' => $this->canDecodeHeic(),
            'upload_max_bytes' => self::iniBytes('upload_max_filesize'),
            'post_max_bytes' => self::iniBytes('post_max_size'),
        ];
    }

    /**
     * Eine hochgeladene Datei aufnehmen.
     *
     * @param string      $tmpPath      Pfad der hochgeladenen Datei (bereits per is_uploaded_file geprüft)
     * @param string      $originalName Dateiname vom Client (nur fürs Protokoll)
     * @param int         $size         Größe in Bytes
     * @param string|null $posterTmp    optionales, im Browser erzeugtes Poster-JPEG (Videos)
     *
     * @return array<string,mixed> Spalten für gallery_media (ohne gallery_id/position)
     */
    public function ingest(string $tmpPath, string $originalName, int $size, ?string $posterTmp = null): array
    {
        $mime = $this->detectMime($tmpPath, $originalName);
        $kind = $this->classify($mime);
        if ($kind === null) {
            throw new RuntimeException('Dateityp wird nicht unterstützt: ' . ($mime ?: 'unbekannt') . '.');
        }

        $maxImage = (int) config('media.max_image_bytes', 25165824);
        $maxVideo = (int) config('media.max_video_bytes', 524288000);
        if ($kind === 'image' && $size > $maxImage) {
            throw new RuntimeException('Das Bild ist zu groß (max. ' . self::humanBytes($maxImage) . ').');
        }
        if ($kind === 'video' && $size > $maxVideo) {
            throw new RuntimeException('Das Video ist zu groß (max. ' . self::humanBytes($maxVideo) . ').');
        }

        $id = bin2hex(random_bytes(16));
        $shard = substr($id, 0, 2) . '/' . substr($id, 2, 2);
        $dir = $this->baseDir . '/' . $shard;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Der Medienordner konnte nicht angelegt werden.');
        }

        if ($kind === 'video') {
            return $this->ingestVideo($tmpPath, $originalName, $size, $mime, $id, $shard, $dir, $posterTmp);
        }

        return $this->ingestImage($tmpPath, $originalName, $size, $mime, $id, $shard, $dir);
    }

    /** Alle Dateien einer Medienzeile löschen (stored/thumb/web). */
    public function deleteFiles(array $mediaRow): void
    {
        foreach (['stored_path', 'thumb_path', 'web_path'] as $col) {
            $rel = (string) ($mediaRow[$col] ?? '');
            if ($rel === '') {
                continue;
            }
            $abs = $this->baseDir . '/' . ltrim($rel, '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    // ---------------------------------------------------------------- Bilder

    private function ingestImage(
        string $tmpPath,
        string $originalName,
        int $size,
        string $mime,
        string $id,
        string $shard,
        string $dir
    ): array {
        $workPath = $tmpPath;
        $cleanupWork = null;

        // HEIC/HEIF kann GD nicht – vor dem Ablegen zu JPEG wandeln.
        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            $converted = $this->convertToJpeg($tmpPath);
            if ($converted === null) {
                throw new RuntimeException('HEIC-Bilder brauchen ImageMagick auf dem Server. Bitte als JPG hochladen.');
            }
            $workPath = $converted;
            $cleanupWork = $converted;
            $mime = 'image/jpeg';
        }

        $ext = self::extensionFor($mime);
        $storedRel = $shard . '/' . $id . '.' . $ext;
        $storedAbs = $dir . '/' . $id . '.' . $ext;

        if ($workPath === $tmpPath) {
            if (!move_uploaded_file($tmpPath, $storedAbs)) {
                throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
            }
        } else {
            if (!rename($workPath, $storedAbs)) {
                throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
            }
            $cleanupWork = null;
        }
        if ($cleanupWork !== null && is_file($cleanupWork)) {
            @unlink($cleanupWork);
        }

        $dimensions = @getimagesize($storedAbs) ?: [0, 0];
        $capturedAt = $this->readCapturedAt($storedAbs, $mime);

        $thumbMax = (int) config('media.thumb_max_edge', 400);
        $webMax = (int) config('media.web_max_edge', 1600);

        $thumbRel = null;
        $webRel = null;
        if (extension_loaded('gd')) {
            $vext = $mime === 'image/png' ? 'png' : 'jpg';
            $thumbAbs = $dir . '/' . $id . '_t.' . $vext;
            if ($this->makeVariant($storedAbs, $thumbAbs, $thumbMax, $mime)) {
                $thumbRel = $shard . '/' . $id . '_t.' . $vext;
            }
            if (max((int) $dimensions[0], (int) $dimensions[1]) > $webMax) {
                $webAbs = $dir . '/' . $id . '_w.' . $vext;
                if ($this->makeVariant($storedAbs, $webAbs, $webMax, $mime)) {
                    $webRel = $shard . '/' . $id . '_w.' . $vext;
                }
            }
        }

        return [
            'kind' => 'image',
            'original_name' => self::cleanName($originalName),
            'stored_path' => $storedRel,
            'thumb_path' => $thumbRel,
            'web_path' => $webRel,
            'mime' => $mime,
            'byte_size' => filesize($storedAbs) ?: $size,
            'width' => (int) $dimensions[0] ?: null,
            'height' => (int) $dimensions[1] ?: null,
            'duration_seconds' => null,
            'captured_at' => $capturedAt,
        ];
    }

    // ---------------------------------------------------------------- Videos

    private function ingestVideo(
        string $tmpPath,
        string $originalName,
        int $size,
        string $mime,
        string $id,
        string $shard,
        string $dir,
        ?string $posterTmp
    ): array {
        $ext = self::extensionFor($mime);
        $storedRel = $shard . '/' . $id . '.' . $ext;
        $storedAbs = $dir . '/' . $id . '.' . $ext;

        if (!move_uploaded_file($tmpPath, $storedAbs)) {
            throw new RuntimeException('Das Video konnte nicht gespeichert werden.');
        }

        $thumbRel = null;
        if ($posterTmp !== null && is_file($posterTmp) && extension_loaded('gd')) {
            $thumbAbs = $dir . '/' . $id . '_t.jpg';
            if ($this->makeVariant($posterTmp, $thumbAbs, (int) config('media.thumb_max_edge', 400), 'image/jpeg')) {
                $thumbRel = $shard . '/' . $id . '_t.jpg';
            }
        }
        if ($posterTmp !== null && is_file($posterTmp)) {
            @unlink($posterTmp);
        }

        return [
            'kind' => 'video',
            'original_name' => self::cleanName($originalName),
            'stored_path' => $storedRel,
            'thumb_path' => $thumbRel,
            'web_path' => null,
            'mime' => $mime,
            'byte_size' => filesize($storedAbs) ?: $size,
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'captured_at' => null,
        ];
    }

    // ---------------------------------------------------------------- intern

    private function detectMime(string $path, string $name): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }
        $mime = strtolower(trim($mime));
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        // libmagic erkennt HEIC/HEIF nicht überall – Endung als Rückfall.
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (($mime === '' || $mime === 'application/octet-stream') && in_array($ext, ['heic', 'heif'], true)) {
            return 'image/heic';
        }
        if (($mime === '' || $mime === 'application/octet-stream') && $ext === 'mov') {
            return 'video/quicktime';
        }

        return $mime;
    }

    private function classify(string $mime): ?string
    {
        $images = (array) config('media.allowed_image_types', self::DEFAULT_IMAGE_TYPES) ?: self::DEFAULT_IMAGE_TYPES;
        $videos = (array) config('media.allowed_video_types', self::DEFAULT_VIDEO_TYPES) ?: self::DEFAULT_VIDEO_TYPES;

        if (in_array($mime, $images, true)) {
            return 'image';
        }
        if (in_array($mime, $videos, true)) {
            return 'video';
        }

        return null;
    }

    private function convertBin(): ?string
    {
        $bin = trim((string) config('media.convert_bin', '/usr/bin/convert'));
        if ($bin !== '' && @is_executable($bin)) {
            return $bin;
        }
        $which = trim((string) @shell_exec('command -v convert 2>/dev/null'));

        return $which !== '' ? $which : null;
    }

    private function canDecodeHeic(): bool
    {
        if (extension_loaded('imagick') && in_array('HEIC', \Imagick::queryFormats('HEIC'), true)) {
            return true;
        }
        $bin = $this->convertBin();
        if ($bin === null) {
            return false;
        }
        $out = (string) @shell_exec(escapeshellarg($bin) . ' -list format 2>/dev/null');

        return (bool) preg_match('/^\s*HEIC?\*?\s+HEIC\s+r/mi', $out);
    }

    private function convertToJpeg(string $src): ?string
    {
        $bin = $this->convertBin();
        if ($bin === null) {
            return null;
        }
        $out = tempnam(sys_get_temp_dir(), 'gmedia_') . '.jpg';
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($src . '[0]')
            . ' -auto-orient -quality 88 ' . escapeshellarg($out) . ' 2>&1';
        @exec($cmd, $lines, $code);
        if ($code === 0 && is_file($out) && filesize($out) > 0) {
            return $out;
        }
        if (is_file($out)) {
            @unlink($out);
        }

        return null;
    }

    private function makeVariant(string $srcPath, string $destPath, int $maxEdge, string $srcMime): bool
    {
        $info = @getimagesize($srcPath);
        if ($info === false) {
            return false;
        }

        $src = match ($srcMime) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png' => @imagecreatefrompng($srcPath),
            'image/webp' => @imagecreatefromwebp($srcPath),
            'image/gif' => @imagecreatefromgif($srcPath),
            default => false,
        };
        if (!$src) {
            return false;
        }

        if ($srcMime === 'image/jpeg') {
            $src = $this->applyExifRotation($src, $srcPath);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $maxEdge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        if (in_array($srcMime, ['image/png', 'image/gif'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $ok = $srcMime === 'image/png'
            ? imagepng($dst, $destPath, 6)
            : imagejpeg($dst, $destPath, 82);

        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok;
    }

    private function applyExifRotation(\GdImage $image, string $path): \GdImage
    {
        if (!extension_loaded('exif')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 0);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function readCapturedAt(string $path, string $mime): ?string
    {
        if ($mime !== 'image/jpeg' || !extension_loaded('exif')) {
            return null;
        }
        $data = @exif_read_data($path);
        if (!is_array($data)) {
            return null;
        }
        $raw = $data['DateTimeOriginal'] ?? $data['DateTimeDigitized'] ?? $data['DateTime'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $normalized = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', trim($raw));
        $ts = strtotime((string) $normalized);
        if ($ts === false || $ts <= 0 || $ts > time() + 86400) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private static function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'bin',
        };
    }

    private static function cleanName(string $name): string
    {
        $name = basename(trim($name));
        $name = preg_replace('/[\x00-\x1f]/', '', $name) ?? '';

        return mb_substr($name, 0, 255);
    }

    public static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '') {
            return 0;
        }
        $unit = strtolower($value[strlen($value) - 1]);
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
