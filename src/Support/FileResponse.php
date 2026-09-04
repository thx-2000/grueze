<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Datei ausliefern – mit Unterstützung für HTTP-Range-Requests, damit Videos
 * im Browser vor- und zurückgespult werden können. Beendet den Request.
 */
final class FileResponse
{
    public static function stream(string $absPath, string $mime, ?string $downloadName = null, int $cacheSeconds = 0): never
    {
        if (!is_file($absPath) || !is_readable($absPath)) {
            http_response_code(404);
            exit;
        }

        $size = (int) filesize($absPath);
        $mtime = (int) filemtime($absPath);
        $etag = '"' . md5($absPath . '|' . $size . '|' . $mtime) . '"';

        // Alle bis hier von der App gesetzten Header, die für eine Binärantwort
        // stören könnten, überschreiben wir gleich gezielt.
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; media-src \'self\'; img-src \'self\'');

        if ($cacheSeconds > 0) {
            header('Cache-Control: private, max-age=' . $cacheSeconds);
        } else {
            header('Cache-Control: private, no-cache');
        }

        if ($downloadName !== null) {
            header('Content-Disposition: attachment; filename="' . self::asciiName($downloadName) . '"'
                . "; filename*=UTF-8''" . rawurlencode($downloadName));
        }

        // Nicht geändert?
        $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($ifNoneMatch !== '' && $ifNoneMatch === $etag) {
            http_response_code(304);
            exit;
        }

        $start = 0;
        $end = $size - 1;
        $isPartial = false;

        $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');
        if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $m)) {
            if ($m[1] === '' && $m[2] === '') {
                self::rangeNotSatisfiable($size);
            }
            if ($m[1] === '') {
                // letzte N Bytes
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
            }
            if ($start > $end || $start >= $size) {
                self::rangeNotSatisfiable($size);
            }
            $isPartial = true;
        }

        $length = $end - $start + 1;

        if ($isPartial) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . $length);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        // Ausgabepuffer leeren, damit nichts vor der Datei landet.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen($absPath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            exit;
        }
        if ($start > 0) {
            fseek($handle, $start);
        }

        $chunk = 8192;
        $remaining = $length;
        while ($remaining > 0 && !feof($handle)) {
            $read = fread($handle, (int) min($chunk, $remaining));
            if ($read === false) {
                break;
            }
            echo $read;
            $remaining -= strlen($read);
            if (connection_aborted()) {
                break;
            }
        }
        fclose($handle);
        exit;
    }

    private static function rangeNotSatisfiable(int $size): never
    {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }

    private static function asciiName(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', $name) ?? 'datei';

        return str_replace(['"', '\\'], '_', $ascii);
    }
}
