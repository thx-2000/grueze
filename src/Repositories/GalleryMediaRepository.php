<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Einzelne Bilder/Videos einer Galerie. Physisch liegen die Dateien unter
 * storage/media/ (MediaService); hier stehen Pfade und Metadaten.
 */
final class GalleryMediaRepository
{
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS gallery_media (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    gallery_id INT UNSIGNED NULL,
                    kind ENUM(\'image\', \'video\') NOT NULL,
                    original_name VARCHAR(255) NULL,
                    stored_path VARCHAR(255) NOT NULL,
                    thumb_path VARCHAR(255) NULL,
                    web_path VARCHAR(255) NULL,
                    mime VARCHAR(100) NOT NULL,
                    byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    width INT UNSIGNED NULL,
                    height INT UNSIGNED NULL,
                    duration_seconds INT UNSIGNED NULL,
                    captured_at DATETIME NULL,
                    caption VARCHAR(500) NULL,
                    position INT NOT NULL DEFAULT 0,
                    uploaded_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    deleted_at DATETIME NULL,
                    KEY idx_gallery_media_gallery (gallery_id, deleted_at),
                    KEY idx_gallery_media_captured (gallery_id, captured_at),
                    KEY idx_gallery_media_position (gallery_id, position)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    public function add(int $galleryId, array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gallery_media
                (gallery_id, kind, original_name, stored_path, thumb_path, web_path, mime,
                 byte_size, width, height, duration_seconds, captured_at, position, uploaded_by)
             VALUES
                (:gallery_id, :kind, :original_name, :stored_path, :thumb_path, :web_path, :mime,
                 :byte_size, :width, :height, :duration_seconds, :captured_at, :position, :uploaded_by)'
        );
        $stmt->execute([
            'gallery_id' => $galleryId,
            'kind' => $data['kind'],
            'original_name' => $data['original_name'] ?? null,
            'stored_path' => $data['stored_path'],
            'thumb_path' => $data['thumb_path'] ?? null,
            'web_path' => $data['web_path'] ?? null,
            'mime' => $data['mime'],
            'byte_size' => (int) ($data['byte_size'] ?? 0),
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'captured_at' => $data['captured_at'] ?? null,
            'position' => $this->nextPosition($galleryId),
            'uploaded_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function nextPosition(int $galleryId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1 FROM gallery_media WHERE gallery_id = :g'
        );
        $stmt->execute(['g' => $galleryId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Medien einer Galerie in der gewünschten Sortierung.
     *
     * @return list<array<string,mixed>>
     */
    public function forGallery(int $galleryId, string $sortMode): array
    {
        $order = match ($sortMode) {
            'uploaded' => 'm.created_at ASC, m.id ASC',
            'manual' => 'm.position ASC, m.id ASC',
            default => 'COALESCE(m.captured_at, m.created_at) ASC, m.id ASC',
        };

        $stmt = $this->pdo->prepare(
            'SELECT m.* FROM gallery_media m
             WHERE m.gallery_id = :g AND m.deleted_at IS NULL
             ORDER BY ' . $order
        );
        $stmt->execute(['g' => $galleryId]);

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $withTrashed = false): ?array
    {
        $sql = 'SELECT * FROM gallery_media WHERE id = :id'
            . ($withTrashed ? '' : ' AND deleted_at IS NULL');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function updateCaption(int $id, string $caption): void
    {
        $this->pdo->prepare('UPDATE gallery_media SET caption = :c WHERE id = :id')
            ->execute(['c' => $caption !== '' ? mb_substr($caption, 0, 500) : null, 'id' => $id]);
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $galleryId, array $orderedIds): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE gallery_media SET position = :p WHERE id = :id AND gallery_id = :g'
        );
        $position = 1;
        foreach ($orderedIds as $id) {
            $stmt->execute(['p' => $position++, 'id' => (int) $id, 'g' => $galleryId]);
        }
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare('UPDATE gallery_media SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL')
            ->execute(['id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->pdo->prepare('UPDATE gallery_media SET deleted_at = NULL WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function hardDelete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM gallery_media WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Alle (auch gelöschten) Medienzeilen einer Galerie – für das endgültige
     * Entfernen inkl. Dateien.
     *
     * @return list<array<string,mixed>>
     */
    public function allForGallery(int $galleryId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gallery_media WHERE gallery_id = :g');
        $stmt->execute(['g' => $galleryId]);

        return $stmt->fetchAll();
    }

    /** Einzeln gelöschte Medien (Galerie selbst noch aktiv) mit abgelaufener Frist. */
    public function expiredTrashed(int $days): array
    {
        if ($days < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM gallery_media
             WHERE deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL :days DAY)'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countForGallery(int $galleryId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM gallery_media WHERE gallery_id = :g AND deleted_at IS NULL'
        );
        $stmt->execute(['g' => $galleryId]);

        return (int) $stmt->fetchColumn();
    }
}
