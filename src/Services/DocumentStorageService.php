<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Nimmt Uploads für den Dokumente-Bereich entgegen (PDF, Word, Excel, …) und
 * legt sie unter storage/documents/ ab (außerhalb des Webroots, getrennt von
 * den Galerie-Medien). Anders als bei Fotos/Videos gibt es keine
 * Vorschau-Erzeugung – nur Ablegen und wieder Ausliefern.
 *
 * Die erlaubte Dateiendung entscheidet über den MIME-Typ bei der Auslieferung
 * (nicht die vom Server erkannte Art) – manche Hoster erkennen Office-Formate
 * nur pauschal als „application/zip".
 */
final class DocumentStorageService
{
    /** Rückfall, falls in config/config.php kein `documents`-Block steht. */
    public const DEFAULT_EXTENSIONS = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'rtf' => 'application/rtf',
        'zip' => 'application/zip',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /** Formate, für die bei Verfügbarkeit von LibreOffice eine PDF-Vorschau erzeugt wird. */
    private const OFFICE_PREVIEW_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];

    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = dirname(__DIR__, 2) . '/storage/documents';
    }

    /** Absoluter Pfad zu einem gespeicherten Dokumentpfad (nie aus Nutzereingabe). */
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

    /** @return array<string,string> Endung (klein, ohne Punkt) => MIME-Typ */
    public function allowedExtensions(): array
    {
        $configured = (array) config('documents.allowed_extensions', self::DEFAULT_EXTENSIONS);

        return $configured !== [] ? $configured : self::DEFAULT_EXTENSIONS;
    }

    public function maxBytes(): int
    {
        return (int) config('documents.max_bytes', 26214624) ?: 26214624;
    }

    /**
     * Ob eine PDF-Vorschau für Office-Formate erzeugt werden kann – braucht
     * LibreOffice (`soffice`) auf dem Server, was auf Shared Hosting meist
     * fehlt. Ohne das bleibt alles wie bisher (Browser entscheidet selbst,
     * meist Direkt-Download).
     */
    public function officePreviewAvailable(): bool
    {
        return $this->officeConvertBin() !== null;
    }

    private function officeConvertBin(): ?string
    {
        $configured = trim((string) config('documents.office_convert_bin', ''));
        if ($configured !== '' && @is_executable($configured)) {
            return $configured;
        }
        foreach (['/usr/bin/soffice', '/usr/bin/libreoffice'] as $candidate) {
            if (@is_executable($candidate)) {
                return $candidate;
            }
        }
        $which = trim((string) @shell_exec('command -v soffice 2>/dev/null'));

        return $which !== '' ? $which : null;
    }

    /**
     * Office-Datei per LibreOffice (headless) zu PDF wandeln – nur wenn
     * verfügbar. Liefert den relativen Vorschau-Pfad oder null, wenn es
     * nicht klappt (Datei bleibt trotzdem normal herunterladbar).
     */
    private function makeOfficePreview(string $sourceAbs, string $ext, string $dir, string $shard, string $id): ?string
    {
        if (!in_array($ext, self::OFFICE_PREVIEW_EXTENSIONS, true)) {
            return null;
        }
        $bin = $this->officeConvertBin();
        if ($bin === null) {
            return null;
        }

        $cmd = escapeshellarg($bin) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($dir) . ' '
            . escapeshellarg($sourceAbs) . ' 2>&1';
        @exec($cmd, $lines, $code);

        // LibreOffice benennt die Ausgabe nach dem Quelldateinamen (ohne Endung) + .pdf.
        $producedAbs = $dir . '/' . pathinfo($sourceAbs, PATHINFO_FILENAME) . '.pdf';
        if ($code !== 0 || !is_file($producedAbs) || @filesize($producedAbs) <= 0) {
            if (is_file($producedAbs)) {
                @unlink($producedAbs);
            }

            return null;
        }

        $previewAbs = $dir . '/' . $id . '_preview.pdf';
        if (!@rename($producedAbs, $previewAbs)) {
            @unlink($producedAbs);

            return null;
        }

        return $shard . '/' . $id . '_preview.pdf';
    }

    /**
     * Eine hochgeladene Datei aufnehmen.
     *
     * @param bool $uploaded true = echter PHP-Upload (move_uploaded_file),
     *                       false = Datei liegt schon auf der Platte, z. B.
     *                       beim Wiederherstellen einer Sicherung (copy())
     * @return array<string,mixed> Spalten für documents (original_name, stored_path, mime, byte_size)
     */
    public function ingest(string $tmpPath, string $originalName, int $size, bool $uploaded = true): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $this->allowedExtensions();
        if ($ext === '' || !isset($allowed[$ext])) {
            throw new RuntimeException('Dateityp wird nicht unterstützt: .' . ($ext !== '' ? $ext : '?') . '.');
        }

        $max = $this->maxBytes();
        if ($size > $max) {
            throw new RuntimeException('Die Datei ist zu groß (max. ' . MediaService::humanBytes($max) . ').');
        }

        $id = bin2hex(random_bytes(16));
        $shard = substr($id, 0, 2) . '/' . substr($id, 2, 2);
        $dir = $this->baseDir . '/' . $shard;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Der Dokumentenordner konnte nicht angelegt werden.');
        }

        $storedRel = $shard . '/' . $id . '.' . $ext;
        $storedAbs = $dir . '/' . $id . '.' . $ext;
        $placed = $uploaded ? move_uploaded_file($tmpPath, $storedAbs) : copy($tmpPath, $storedAbs);
        if (!$placed) {
            throw new RuntimeException('Die Datei konnte nicht gespeichert werden.');
        }

        $previewRel = $this->makeOfficePreview($storedAbs, $ext, $dir, $shard, $id);

        return [
            'original_name' => self::cleanName($originalName),
            'stored_path' => $storedRel,
            'preview_path' => $previewRel,
            'mime' => $allowed[$ext],
            'byte_size' => @filesize($storedAbs) ?: $size,
        ];
    }

    public function deleteFile(array $documentRow): void
    {
        foreach (['stored_path', 'preview_path'] as $col) {
            $rel = (string) ($documentRow[$col] ?? '');
            if ($rel === '') {
                continue;
            }
            $abs = $this->baseDir . '/' . ltrim($rel, '/');
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    private static function cleanName(string $name): string
    {
        $name = basename(trim($name));
        $name = preg_replace('/[\x00-\x1f]/', '', $name) ?? '';

        return mb_substr($name, 0, 255);
    }
}
