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

    /** Schreib-Ordner für temporäre ZIPs (nicht /tmp – auf Shared Hosting oft knapp). */
    public function tmpDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_writable($dir) ? $dir : sys_get_temp_dir();
    }

    // ------------------------------------------------------- Chunked Upload

    private function chunkBaseDir(): string
    {
        $dir = $this->tmpDir() . '/chunks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** sessionId kommt immer aus bin2hex() – trotzdem defensiv gegen Pfad-Traversal. */
    private function chunkSessionDir(string $sessionId): string
    {
        $safe = preg_replace('/[^a-f0-9]/', '', $sessionId) ?? '';

        return $this->chunkBaseDir() . '/' . $safe;
    }

    /** Neue Chunk-Upload-Sitzung anlegen. @return string Sitzungs-ID */
    /**
     * @param array<string,mixed> $context Aufrufer-eigene Zuordnung, z. B.
     *                                     user_id/gallery_id (eingeloggter
     *                                     Upload) oder link_id (öffentlicher
     *                                     Beitrags-Link) – wird 1:1 mit in
     *                                     die Sitzungs-Metadaten geschrieben
     *                                     und von chunkSessionMeta() wieder
     *                                     zurückgegeben.
     */
    public function startChunkSession(array $context, string $originalName, int $totalSize, int $totalChunks): string
    {
        $id = bin2hex(random_bytes(16));
        $dir = $this->chunkSessionDir($id);
        if (!mkdir($dir, 0775, true)) {
            throw new RuntimeException('Die Upload-Sitzung konnte nicht angelegt werden.');
        }
        $meta = $context + [
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
            'created_at' => time(),
        ];
        file_put_contents($dir . '/meta.json', json_encode($meta));

        return $id;
    }

    /** @return array<string,mixed>|null */
    public function chunkSessionMeta(string $sessionId): ?array
    {
        $path = $this->chunkSessionDir($sessionId) . '/meta.json';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    public function writeChunkPart(string $sessionId, int $index, string $tmpUploadPath): void
    {
        $dir = $this->chunkSessionDir($sessionId);
        if (!is_dir($dir) || $index < 0) {
            throw new RuntimeException('Unbekannte Upload-Sitzung.');
        }
        if (!move_uploaded_file($tmpUploadPath, $dir . '/' . $index . '.part')) {
            throw new RuntimeException('Das Stück konnte nicht gespeichert werden.');
        }
    }

    public function chunkSessionComplete(string $sessionId, int $totalChunks): bool
    {
        $dir = $this->chunkSessionDir($sessionId);
        for ($i = 0; $i < $totalChunks; $i++) {
            if (!is_file($dir . '/' . $i . '.part')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Alle Stücke einer Sitzung zu einer Datei zusammenfügen (streamend,
     * damit auch sehr große Videos nicht komplett in den Speicher müssen).
     * Die zusammengesetzte Datei liegt danach noch im Sitzungsordner –
     * discardChunkSession() räumt sie mit weg.
     */
    public function assembleChunkSession(string $sessionId, int $totalChunks): string
    {
        $dir = $this->chunkSessionDir($sessionId);
        $out = $dir . '/assembled.bin';
        $outHandle = fopen($out, 'wb');
        if ($outHandle === false) {
            throw new RuntimeException('Die Datei konnte nicht zusammengesetzt werden.');
        }
        for ($i = 0; $i < $totalChunks; $i++) {
            $partHandle = fopen($dir . '/' . $i . '.part', 'rb');
            if ($partHandle === false) {
                fclose($outHandle);
                throw new RuntimeException('Es fehlt ein Teil der Datei.');
            }
            stream_copy_to_stream($partHandle, $outHandle);
            fclose($partHandle);
        }
        fclose($outHandle);

        return $out;
    }

    public function discardChunkSession(string $sessionId): void
    {
        $dir = $this->chunkSessionDir($sessionId);
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** Abgebrochene/nie fertiggestellte Upload-Sitzungen aufräumen (GC, kein Cron nötig). */
    public function pruneStaleChunkSessions(int $maxAgeHours = 24): void
    {
        foreach (glob($this->chunkBaseDir() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $metaPath = $dir . '/meta.json';
            $createdAt = 0;
            if (is_file($metaPath)) {
                $data = json_decode((string) file_get_contents($metaPath), true);
                $createdAt = is_array($data) ? (int) ($data['created_at'] ?? 0) : 0;
            }
            if ($createdAt === 0 || (time() - $createdAt) > $maxAgeHours * 3600) {
                foreach (glob($dir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($dir);
            }
        }
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
    public function ingest(string $tmpPath, string $originalName, int $size, ?string $posterTmp = null, bool $uploaded = true): array
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
            return $this->ingestVideo($tmpPath, $originalName, $size, $mime, $id, $shard, $dir, $posterTmp, $uploaded);
        }

        return $this->ingestImage($tmpPath, $originalName, $size, $mime, $id, $shard, $dir, $uploaded);
    }

    /**
     * Wie ingest(), aber für eine Datei, die schon auf der Platte liegt (nicht
     * frisch hochgeladen) – z. B. beim Wiederherstellen einer Medien-Sicherung.
     * Die Quelldatei bleibt liegen (Kopie), der Aufrufer räumt sie weg.
     */
    public function adopt(string $path, string $originalName): array
    {
        return $this->ingest($path, $originalName, (int) @filesize($path) ?: 0, null, false);
    }

    private function place(string $src, string $dst, bool $uploaded): bool
    {
        return $uploaded ? move_uploaded_file($src, $dst) : copy($src, $dst);
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
        string $dir,
        bool $uploaded = true
    ): array {
        $workPath = $tmpPath;
        $workIsUploaded = $uploaded;
        $cleanupWork = null;

        // HEIC/HEIF kann GD nicht – vor dem Ablegen zu JPEG wandeln.
        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            $converted = $this->convertToJpeg($tmpPath);
            if ($converted === null) {
                throw new RuntimeException('HEIC-Bilder brauchen ImageMagick auf dem Server. Bitte als JPG hochladen.');
            }
            $workPath = $converted;
            $workIsUploaded = false;
            $cleanupWork = $converted;
            $mime = 'image/jpeg';
        }

        $ext = self::extensionFor($mime);
        $storedRel = $shard . '/' . $id . '.' . $ext;
        $storedAbs = $dir . '/' . $id . '.' . $ext;

        if (!$this->place($workPath, $storedAbs, $workIsUploaded)) {
            throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        }
        if ($cleanupWork !== null && is_file($cleanupWork)) {
            @unlink($cleanupWork);
        }

        // Aufnahmezeit erst lesen, dann drehen – GD verwirft beim Neu-
        // Speichern alle Metadaten (auch den Orientation-Tag selbst, das
        // Original braucht danach keine EXIF-Drehung mehr durch den Browser).
        $capturedAt = $this->readCapturedAt($storedAbs, $mime);
        if ($mime === 'image/jpeg') {
            $this->rotateOriginalIfNeeded($storedAbs);
        }
        $dimensions = @getimagesize($storedAbs) ?: [0, 0];

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
            'byte_size' => @filesize($storedAbs) ?: $size,
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
        ?string $posterTmp,
        bool $uploaded = true
    ): array {
        $ext = self::extensionFor($mime);
        $storedRel = $shard . '/' . $id . '.' . $ext;
        $storedAbs = $dir . '/' . $id . '.' . $ext;

        if (!$this->place($tmpPath, $storedAbs, $uploaded)) {
            throw new RuntimeException('Das Video konnte nicht gespeichert werden.');
        }

        $videoMeta = match ($mime) {
            'video/mp4', 'video/quicktime' => $this->readMp4Meta($storedAbs),
            'video/webm' => $this->readWebmMeta($storedAbs),
            default => ['duration_seconds' => null, 'width' => null, 'height' => null],
        };

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
            'byte_size' => @filesize($storedAbs) ?: $size,
            'width' => $videoMeta['width'],
            'height' => $videoMeta['height'],
            'duration_seconds' => $videoMeta['duration_seconds'],
            'captured_at' => null,
        ];
    }

    // ------------------------------------------------------- Video-Metadaten

    /**
     * Dauer + Breite/Höhe aus MP4/MOV lesen (ISO-Base-Media-Container) –
     * ganz ohne ffmpeg (auf Shared Hosting meist nicht verfügbar), reines
     * PHP-Byte-Parsing der `moov`/`mvhd`/`trak`/`tkhd`-Boxen. Für WebM siehe
     * readWebmMeta() weiter unten (anderes Container-Format, EBML).
     *
     * @return array{duration_seconds:int|null,width:int|null,height:int|null}
     */
    private function readMp4Meta(string $path): array
    {
        $result = ['duration_seconds' => null, 'width' => null, 'height' => null];
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $result;
        }

        try {
            $size = @filesize($path) ?: 0;
            $moov = $this->findChildBox($handle, 0, $size, 'moov');
            if ($moov === null) {
                return $result;
            }
            [$moovStart, $moovEnd] = $moov;

            $mvhd = $this->findChildBox($handle, $moovStart, $moovEnd, 'mvhd');
            if ($mvhd !== null) {
                $result['duration_seconds'] = $this->readMvhdDuration($handle, $mvhd[0]);
            }

            // Mehrere Spuren möglich (Video + Audio) – tkhd verrät den Typ
            // nicht direkt, daher die mit der größten Fläche nehmen (eine
            // Audiospur hat width=height=0).
            $bestArea = 0;
            $pos = $moovStart;
            while (($trak = $this->findChildBox($handle, $pos, $moovEnd, 'trak')) !== null) {
                [$trakStart, $trakEnd] = $trak;
                $tkhd = $this->findChildBox($handle, $trakStart, $trakEnd, 'tkhd');
                if ($tkhd !== null) {
                    [$w, $h] = $this->readTkhdDimensions($handle, $tkhd[0]);
                    if ($w * $h > $bestArea) {
                        $bestArea = $w * $h;
                        $result['width'] = $w > 0 ? $w : null;
                        $result['height'] = $h > 0 ? $h : null;
                    }
                }
                $pos = $trakEnd;
            }
        } catch (\Throwable) {
            // Ungewöhnliche/kaputte Datei – ohne Metadaten weitermachen,
            // der Upload selbst darf daran nicht scheitern.
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * Erste Box mit diesem Typ innerhalb [$start, $end) suchen (keine
     * Rekursion in andere Boxen – wird gezielt mit dem Container-Bereich
     * aufgerufen). Liefert [Inhalt-Start, Inhalt-Ende] (nach dem 8/16-Byte
     * Box-Header) oder null.
     *
     * @return array{0:int,1:int}|null
     */
    private function findChildBox($handle, int $start, int $end, string $type): ?array
    {
        $pos = $start;
        while ($pos + 8 <= $end) {
            fseek($handle, $pos);
            $header = fread($handle, 8);
            if ($header === false || strlen($header) < 8) {
                return null;
            }
            $boxSize = unpack('N', substr($header, 0, 4))[1];
            $boxType = substr($header, 4, 4);
            $headerSize = 8;

            if ($boxSize === 1) {
                $ext = fread($handle, 8);
                if ($ext === false || strlen($ext) < 8) {
                    return null;
                }
                $hi = unpack('N', substr($ext, 0, 4))[1];
                $lo = unpack('N', substr($ext, 4, 4))[1];
                $boxSize = ($hi << 32) | $lo;
                $headerSize = 16;
            } elseif ($boxSize === 0) {
                $boxSize = $end - $pos;
            }
            if ($boxSize < $headerSize) {
                return null; // korrupt – Größe kleiner als der eigene Header
            }

            $contentStart = $pos + $headerSize;
            $contentEnd = min($pos + $boxSize, $end);
            if ($boxType === $type) {
                return [$contentStart, $contentEnd];
            }
            $pos += $boxSize;
        }

        return null;
    }

    private function readMvhdDuration($handle, int $contentStart): ?int
    {
        fseek($handle, $contentStart);
        $versionByte = fread($handle, 1);
        if ($versionByte === false || $versionByte === '') {
            return null;
        }
        $version = ord($versionByte);
        $skip = $version === 1 ? 16 : 8; // creation_time + modification_time

        fseek($handle, $contentStart + 4 + $skip);
        $timescaleRaw = fread($handle, 4);
        if ($timescaleRaw === false || strlen($timescaleRaw) < 4) {
            return null;
        }
        $timescale = unpack('N', $timescaleRaw)[1];

        if ($version === 1) {
            $durationRaw = fread($handle, 8);
            if ($durationRaw === false || strlen($durationRaw) < 8) {
                return null;
            }
            $hi = unpack('N', substr($durationRaw, 0, 4))[1];
            $lo = unpack('N', substr($durationRaw, 4, 4))[1];
            $duration = ($hi << 32) | $lo;
        } else {
            $durationRaw = fread($handle, 4);
            if ($durationRaw === false || strlen($durationRaw) < 4) {
                return null;
            }
            $duration = unpack('N', $durationRaw)[1];
        }

        return $timescale > 0 ? (int) round($duration / $timescale) : null;
    }

    /** @return array{0:int,1:int} [width, height] – [0, 0] wenn nicht lesbar. */
    private function readTkhdDimensions($handle, int $contentStart): array
    {
        fseek($handle, $contentStart);
        $versionByte = fread($handle, 1);
        if ($versionByte === false || $versionByte === '') {
            return [0, 0];
        }
        $version = ord($versionByte);
        // v0: creation(4)+modification(4)+track_ID(4)+reserved(4)+duration(4) = 20
        // v1: creation(8)+modification(8)+track_ID(4)+reserved(4)+duration(8) = 32
        $afterVersionFlags = $version === 1 ? 32 : 20;
        // + reserved[2]*4(8) + layer(2) + alternate_group(2) + volume(2) + reserved(2) + matrix(36) = 52
        $offset = $contentStart + 4 + $afterVersionFlags + 52;

        fseek($handle, $offset);
        $raw = fread($handle, 8);
        if ($raw === false || strlen($raw) < 8) {
            return [0, 0];
        }
        $widthFixed = unpack('N', substr($raw, 0, 4))[1];
        $heightFixed = unpack('N', substr($raw, 4, 4))[1];

        return [(int) round($widthFixed / 65536), (int) round($heightFixed / 65536)];
    }

    // -------------------------------------------------- WebM-Metadaten (EBML)

    /**
     * Dauer + Breite/Höhe aus WebM lesen (EBML-Container, wie bei Matroska) –
     * ganz ohne ffmpeg, reines PHP-Byte-Parsing. Sucht im `Segment` das
     * `Info`-Element (Dauer) und im `Tracks`-Element die erste Video-Spur
     * (`Video`-Unterelement mit Pixelbreite/-höhe).
     *
     * @return array{duration_seconds:int|null,width:int|null,height:int|null}
     */
    private function readWebmMeta(string $path): array
    {
        $result = ['duration_seconds' => null, 'width' => null, 'height' => null];
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return $result;
        }

        try {
            $size = @filesize($path) ?: 0;
            $segment = $this->findEbmlElement($handle, 0, $size, 0x18538067); // Segment
            if ($segment === null) {
                return $result;
            }
            [$segStart, $segEnd] = $segment;

            $info = $this->findEbmlElement($handle, $segStart, $segEnd, 0x1549A966); // Info
            if ($info !== null) {
                $result['duration_seconds'] = $this->readWebmDuration($handle, $info[0], $info[1]);
            }

            $tracks = $this->findEbmlElement($handle, $segStart, $segEnd, 0x1654AE6B); // Tracks
            if ($tracks !== null) {
                [$w, $h] = $this->readWebmVideoDimensions($handle, $tracks[0], $tracks[1]);
                $result['width'] = $w > 0 ? $w : null;
                $result['height'] = $h > 0 ? $h : null;
            }
        } catch (\Throwable) {
            // Ungewöhnliche/kaputte Datei – ohne Metadaten weitermachen.
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * Erstes Element mit dieser ID innerhalb [$start, $end) suchen (keine
     * Rekursion in andere Elemente). Liefert [Inhalt-Start, Inhalt-Ende]
     * oder null. Ein „Größe unbekannt"-Element (alle Datenbits 1, bei
     * Live-Aufnahmen üblich) wird defensiv bis zum Ende des übergebenen
     * Bereichs angenommen.
     *
     * @return array{0:int,1:int}|null
     */
    private function findEbmlElement($handle, int $start, int $end, int $targetId): ?array
    {
        $pos = $start;
        while ($pos < $end) {
            fseek($handle, $pos);
            [$id, $idLen] = $this->readEbmlVint($handle, true);
            if ($id === null) {
                return null;
            }
            [$contentSize, $sizeLen] = $this->readEbmlVint($handle, false);
            if ($contentSize === null) {
                return null;
            }

            $contentStart = $pos + $idLen + $sizeLen;
            $contentEnd = $contentSize < 0 ? $end : min($contentStart + $contentSize, $end);
            if ($id === $targetId) {
                return [$contentStart, $contentEnd];
            }
            if ($contentEnd <= $pos) {
                return null; // Sicherheitsnetz gegen Endlosschleifen bei kaputten Daten
            }
            $pos = $contentEnd;
        }

        return null;
    }

    /**
     * EBML-„VINT" lesen: das erste Bit-Muster im ersten Byte bestimmt die
     * Länge (1–8 Byte). Bei Element-IDs bleibt das Längen-Markierungsbit
     * Teil des Werts (`$keepMarker=true`, so wie die bekannten IDs auch
     * dokumentiert sind), bei Größenangaben wird es abgezogen. Liefert -1
     * als Größe, wenn alle Datenbits gesetzt sind („Größe unbekannt").
     *
     * @return array{0:int|null,1:int} [Wert, gelesene Bytes]
     */
    private function readEbmlVint($handle, bool $keepMarker): array
    {
        $firstRaw = fread($handle, 1);
        if ($firstRaw === false || $firstRaw === '') {
            return [null, 0];
        }
        $first = ord($firstRaw);
        if ($first === 0) {
            return [null, 0];
        }

        $length = 1;
        $mask = 0x80;
        while (($first & $mask) === 0 && $length <= 8) {
            $mask >>= 1;
            $length++;
        }

        $value = $keepMarker ? $first : ($first & ($mask - 1));
        for ($i = 1; $i < $length; $i++) {
            $b = fread($handle, 1);
            if ($b === false || $b === '') {
                return [null, 0];
            }
            $value = ($value << 8) | ord($b);
        }

        if (!$keepMarker && $value === (2 ** (7 * $length)) - 1) {
            $value = -1; // „Größe unbekannt"
        }

        return [$value, $length];
    }

    private function readWebmDuration($handle, int $infoStart, int $infoEnd): ?int
    {
        $timecodeScale = 1000000; // Vorgabe laut Spezifikation (Nanosekunden)
        $tsElement = $this->findEbmlElement($handle, $infoStart, $infoEnd, 0x2AD7B1); // TimecodeScale
        if ($tsElement !== null) {
            $value = $this->readWebmUint($handle, $tsElement[0], $tsElement[1] - $tsElement[0]);
            if ($value !== null && $value > 0) {
                $timecodeScale = $value;
            }
        }

        $durElement = $this->findEbmlElement($handle, $infoStart, $infoEnd, 0x4489); // Duration
        if ($durElement === null) {
            return null;
        }
        $duration = $this->readWebmFloat($handle, $durElement[0], $durElement[1] - $durElement[0]);

        return $duration !== null ? (int) round($duration * $timecodeScale / 1_000_000_000) : null;
    }

    /** @return array{0:int,1:int} [width, height] – [0, 0] wenn nicht gefunden. */
    private function readWebmVideoDimensions($handle, int $tracksStart, int $tracksEnd): array
    {
        $pos = $tracksStart;
        while (($entry = $this->findEbmlElement($handle, $pos, $tracksEnd, 0xAE)) !== null) { // TrackEntry
            [$entryStart, $entryEnd] = $entry;

            $typeElement = $this->findEbmlElement($handle, $entryStart, $entryEnd, 0x83); // TrackType
            $isVideo = $typeElement !== null
                && $this->readWebmUint($handle, $typeElement[0], $typeElement[1] - $typeElement[0]) === 1;

            if ($isVideo) {
                $videoElement = $this->findEbmlElement($handle, $entryStart, $entryEnd, 0xE0); // Video
                if ($videoElement !== null) {
                    [$videoStart, $videoEnd] = $videoElement;
                    $wEl = $this->findEbmlElement($handle, $videoStart, $videoEnd, 0xB0); // PixelWidth
                    $hEl = $this->findEbmlElement($handle, $videoStart, $videoEnd, 0xBA); // PixelHeight
                    $w = $wEl !== null ? $this->readWebmUint($handle, $wEl[0], $wEl[1] - $wEl[0]) : null;
                    $h = $hEl !== null ? $this->readWebmUint($handle, $hEl[0], $hEl[1] - $hEl[0]) : null;
                    if ($w !== null && $h !== null) {
                        return [$w, $h];
                    }
                }
            }
            $pos = $entryEnd;
        }

        return [0, 0];
    }

    private function readWebmUint($handle, int $start, int $size): ?int
    {
        if ($size <= 0 || $size > 8) {
            return null;
        }
        fseek($handle, $start);
        $bytes = fread($handle, $size);
        if ($bytes === false || strlen($bytes) < $size) {
            return null;
        }
        $value = 0;
        for ($i = 0; $i < $size; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    private function readWebmFloat($handle, int $start, int $size): ?float
    {
        fseek($handle, $start);
        $bytes = fread($handle, $size);
        if ($bytes === false || strlen($bytes) < $size) {
            return null;
        }
        $unpacked = match ($size) {
            4 => unpack('G', $bytes), // big-endian float
            8 => unpack('E', $bytes), // big-endian double
            default => false,
        };

        return $unpacked !== false ? (float) $unpacked[1] : null;
    }

    // ---------------------------------------------------------------- intern

    private function detectMime(string $path, string $name): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            // @: manche Shared-Hoster haben eine unvollständige magic-DB und
            // lassen finfo warnen – das darf die JSON-Antwort nicht stören.
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) @finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($path);
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
        $out = tempnam($this->tmpDir(), 'gmedia_') . '.jpg';
        $cmd = escapeshellarg($bin) . ' ' . escapeshellarg($src . '[0]')
            . ' -auto-orient -quality 88 ' . escapeshellarg($out) . ' 2>&1';
        @exec($cmd, $lines, $code);
        if ($code === 0 && is_file($out) && @filesize($out) > 0) {
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
            ? @imagepng($dst, $destPath, 6)
            : @imagejpeg($dst, $destPath, 82);

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
        $angle = self::rotationAngleFor((int) ($exif['Orientation'] ?? 0));
        $rotated = $angle !== null ? @imagerotate($image, $angle, 0) : null;
        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /**
     * Original-JPEG physisch entsprechend seinem EXIF-Orientation-Tag drehen
     * und neu speichern (Original bekam bisher nur bei den Vorschau-Varianten
     * eine Drehung, nicht selbst). GD verwirft dabei alle Metadaten inkl.
     * Orientation-Tag – danach ist die Datei „normal" orientiert, ein
     * Browser dreht sie beim direkten Anzeigen also nicht noch einmal.
     */
    private function rotateOriginalIfNeeded(string $path): void
    {
        if (!extension_loaded('exif') || !extension_loaded('gd')) {
            return;
        }
        $exif = @exif_read_data($path);
        $angle = self::rotationAngleFor((int) ($exif['Orientation'] ?? 0));
        if ($angle === null) {
            return;
        }

        $image = @imagecreatefromjpeg($path);
        if (!$image instanceof \GdImage) {
            return;
        }
        $rotated = @imagerotate($image, $angle, 0);
        imagedestroy($image);
        if ($rotated instanceof \GdImage) {
            @imagejpeg($rotated, $path, 95);
            imagedestroy($rotated);
        }
    }

    /** EXIF-Orientation → Grad für imagerotate() (das dreht mathematisch gegen den Uhrzeigersinn). */
    private static function rotationAngleFor(int $orientation): ?int
    {
        return match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => null,
        };
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
