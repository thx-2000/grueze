<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UploadService
{
    public function storePhoto(?array $file, ?string $existingPath = null): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Der Bildupload ist fehlgeschlagen.');
        }

        if (($file['size'] ?? 0) > (int) config('security.photo_max_size', 2097152)) {
            throw new RuntimeException('Das Profilbild ist zu groß.');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, config('security.allowed_photo_types', []), true)) {
            throw new RuntimeException('Das Profilbild muss JPG, PNG oder WEBP sein.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unbekannter Bildtyp.'),
        };

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = 'assets/uploads/' . $filename;
        $target = dirname(__DIR__, 2) . '/public/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Das Profilbild konnte nicht gespeichert werden.');
        }

        return $relativePath;
    }

    public function storeBrandAsset(?array $file, ?string $existingPath = null): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Der Logo-Upload ist fehlgeschlagen.');
        }

        if (($file['size'] ?? 0) > 3145728) {
            throw new RuntimeException('Das Logo ist zu groß.');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'], true)) {
            throw new RuntimeException('Das Logo muss JPG, PNG, WEBP oder SVG sein.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => throw new RuntimeException('Unbekannter Logotyp.'),
        };

        $filename = 'brand_' . bin2hex(random_bytes(12)) . '.' . $extension;
        $relativePath = 'assets/uploads/' . $filename;
        $target = dirname(__DIR__, 2) . '/public/' . $relativePath;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Das Logo konnte nicht gespeichert werden.');
        }

        return $relativePath;
    }

    public function storeAttachments(?array $files): array
    {
        if (!$files || empty($files['name'])) {
            return [];
        }

        $paths = [];
        $total = 0;
        $allowed = config('mail.allowed_attachment_types', []);
        $limit = (int) config('mail.max_attachment_size_total', 10485760);

        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (($files['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Mindestens ein Anhang konnte nicht hochgeladen werden.');
            }

            $tmpName = $files['tmp_name'][$index];
            $size = (int) ($files['size'][$index] ?? 0);
            $total += $size;
            if ($total > $limit) {
                throw new RuntimeException('Die Anhänge überschreiten das Gesamtlimit.');
            }

            $mime = mime_content_type($tmpName);
            if (!in_array($mime, $allowed, true)) {
                throw new RuntimeException('Ein Anhang hat einen nicht erlaubten Dateityp.');
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
            $targetName = bin2hex(random_bytes(12)) . '_' . $safeName;
            $relative = 'storage/tmp/' . $targetName;
            $target = dirname(__DIR__, 2) . '/' . $relative;

            if (!move_uploaded_file($tmpName, $target)) {
                throw new RuntimeException('Ein Anhang konnte nicht gespeichert werden.');
            }

            $paths[] = ['path' => $target, 'name' => $safeName];
        }

        return $paths;
    }

    public function cleanupAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (!empty($attachment['path']) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }
    }
}
