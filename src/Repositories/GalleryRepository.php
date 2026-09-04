<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Galerien (Foto-/Video-Sammlungen). Die Dateien selbst verwaltet
 * MediaService / GalleryMediaRepository – hier stehen nur Titel, Beschreibung,
 * optionaler Termin-Bezug und der Papierkorb-Status.
 */
final class GalleryRepository
{
    private static bool $schemaChecked = false;

    public const SORT_MODES = ['captured', 'uploaded', 'manual'];

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
                'CREATE TABLE IF NOT EXISTS galleries (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(190) NOT NULL,
                    description TEXT NULL,
                    gallery_date DATE NULL,
                    event_id INT UNSIGNED NULL,
                    sort_mode ENUM(\'captured\', \'uploaded\', \'manual\') NOT NULL DEFAULT \'captured\',
                    cover_media_id INT UNSIGNED NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    deleted_at DATETIME NULL,
                    KEY idx_galleries_event (event_id),
                    KEY idx_galleries_deleted (deleted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * Alle aktiven Galerien mit Medienzahl und Cover-Info.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT g.*,
                       e.title AS event_title,
                       COUNT(m.id) AS media_count,
                       SUM(CASE WHEN m.kind = \'video\' THEN 1 ELSE 0 END) AS video_count,
                       COALESCE(cover.thumb_path, cover.web_path) AS cover_path,
                       cover.kind AS cover_kind
                FROM galleries g
                LEFT JOIN events e ON e.id = g.event_id
                LEFT JOIN gallery_media m ON m.gallery_id = g.id AND m.deleted_at IS NULL
                LEFT JOIN gallery_media cover ON cover.id = g.cover_media_id AND cover.deleted_at IS NULL
                WHERE g.deleted_at IS NULL
                GROUP BY g.id
                ORDER BY COALESCE(g.gallery_date, DATE(g.created_at)) DESC, g.id DESC';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $withTrashed = false): ?array
    {
        $sql = 'SELECT g.*, e.title AS event_title
                FROM galleries g
                LEFT JOIN events e ON e.id = g.event_id
                WHERE g.id = :id' . ($withTrashed ? '' : ' AND g.deleted_at IS NULL');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO galleries (title, description, gallery_date, event_id, sort_mode, created_by)
             VALUES (:title, :description, :gallery_date, :event_id, :sort_mode, :created_by)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'gallery_date' => ($data['gallery_date'] ?? '') !== '' ? $data['gallery_date'] : null,
            'event_id' => !empty($data['event_id']) ? (int) $data['event_id'] : null,
            'sort_mode' => in_array($data['sort_mode'] ?? '', self::SORT_MODES, true) ? $data['sort_mode'] : 'captured',
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE galleries
             SET title = :title, description = :description, gallery_date = :gallery_date,
                 event_id = :event_id, sort_mode = :sort_mode
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'gallery_date' => ($data['gallery_date'] ?? '') !== '' ? $data['gallery_date'] : null,
            'event_id' => !empty($data['event_id']) ? (int) $data['event_id'] : null,
            'sort_mode' => in_array($data['sort_mode'] ?? '', self::SORT_MODES, true) ? $data['sort_mode'] : 'captured',
        ]);
    }

    public function setCover(int $galleryId, ?int $mediaId): void
    {
        $this->pdo->prepare('UPDATE galleries SET cover_media_id = :m WHERE id = :id')
            ->execute(['m' => $mediaId, 'id' => $galleryId]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare('UPDATE galleries SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL')
            ->execute(['id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->pdo->prepare('UPDATE galleries SET deleted_at = NULL WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function hardDelete(int $id): void
    {
        // gallery_media hängt per FK ON DELETE CASCADE – die Dateien räumt der
        // Controller vorher über MediaService weg.
        $this->pdo->prepare('DELETE FROM galleries WHERE id = :id')->execute(['id' => $id]);
    }

    /** @return list<array<string,mixed>> Galerien im Papierkorb */
    public function trashed(): array
    {
        return $this->pdo->query(
            'SELECT g.*, COUNT(m.id) AS media_count
             FROM galleries g
             LEFT JOIN gallery_media m ON m.gallery_id = g.id
             WHERE g.deleted_at IS NOT NULL
             GROUP BY g.id
             ORDER BY g.deleted_at DESC'
        )->fetchAll();
    }

    /** IDs von Galerien, deren Papierkorb-Frist abgelaufen ist. */
    public function expiredTrashIds(int $days): array
    {
        if ($days < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM galleries WHERE deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL :days DAY)'
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Für die Termin-Detailseite: Galerien zu einem Termin. */
    public function forEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.title, COUNT(m.id) AS media_count
             FROM galleries g
             LEFT JOIN gallery_media m ON m.gallery_id = g.id AND m.deleted_at IS NULL
             WHERE g.event_id = :e AND g.deleted_at IS NULL
             GROUP BY g.id
             ORDER BY g.id DESC'
        );
        $stmt->execute(['e' => $eventId]);

        return $stmt->fetchAll();
    }
}
