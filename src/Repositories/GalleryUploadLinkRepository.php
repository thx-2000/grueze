<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Weitergabe-Links zum Beisteuern von Fotos/Videos ohne Login.
 *
 * Der rohe Token steht nur im Link; in der DB liegt ein bcrypt-Hash plus ein
 * SHA-256-Index für den schnellen, konstantzeitigen Zugriff (wie beim
 * Passwort-Reset). `gallery_id = NULL` bedeutet Auffangraum.
 */
final class GalleryUploadLinkRepository
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
                'CREATE TABLE IF NOT EXISTS gallery_upload_links (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    gallery_id INT UNSIGNED NULL,
                    token_hash VARCHAR(255) NOT NULL,
                    token_sha CHAR(64) NOT NULL,
                    label VARCHAR(120) NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    max_uploads INT UNSIGNED NULL,
                    upload_count INT UNSIGNED NOT NULL DEFAULT 0,
                    last_upload_at DATETIME NULL,
                    KEY idx_gul_token_sha (token_sha),
                    KEY idx_gul_gallery (gallery_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'ALTER TABLE gallery_media ADD COLUMN IF NOT EXISTS via_link TINYINT(1) NOT NULL DEFAULT 0 AFTER uploaded_by'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    public static function hashSha(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /** @return string roher Token für den Link */
    public function create(?int $galleryId, ?string $label, int $days, ?int $maxUploads, ?int $userId): string
    {
        $token = bin2hex(random_bytes(20));
        $stmt = $this->pdo->prepare(
            'INSERT INTO gallery_upload_links
                (gallery_id, token_hash, token_sha, label, created_by, expires_at, max_uploads)
             VALUES
                (:gallery_id, :token_hash, :token_sha, :label, :created_by,
                 :expires_at, :max_uploads)'
        );
        $stmt->execute([
            'gallery_id' => $galleryId,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'token_sha' => self::hashSha($token),
            'label' => $label !== null && $label !== '' ? mb_substr($label, 0, 120) : null,
            'created_by' => $userId,
            'expires_at' => $days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
            'max_uploads' => $maxUploads !== null && $maxUploads > 0 ? $maxUploads : null,
        ]);

        return $token;
    }

    /** @return array<string,mixed>|null gültiger Link samt Galerie-Infos */
    public function findValidByToken(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{40}$/i', $token)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT l.*, g.title AS gallery_title, g.deleted_at AS gallery_deleted
             FROM gallery_upload_links l
             LEFT JOIN galleries g ON g.id = l.gallery_id
             WHERE l.token_sha = :sha
               AND l.revoked_at IS NULL
               AND (l.expires_at IS NULL OR l.expires_at >= NOW())
               AND (l.max_uploads IS NULL OR l.upload_count < l.max_uploads)
             LIMIT 1'
        );
        $stmt->execute(['sha' => self::hashSha($token)]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($token, (string) $row['token_hash'])) {
            return null;
        }
        // Ziel-Galerie im Papierkorb → Link tot.
        if ($row['gallery_id'] !== null && $row['gallery_deleted'] !== null) {
            return null;
        }

        return $row;
    }

    public function noteUpload(int $id): void
    {
        $this->pdo->prepare(
            'UPDATE gallery_upload_links SET upload_count = upload_count + 1, last_upload_at = NOW() WHERE id = :id'
        )->execute(['id' => $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gallery_upload_links WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function revoke(int $id): void
    {
        $this->pdo->prepare('UPDATE gallery_upload_links SET revoked_at = NOW() WHERE id = :id AND revoked_at IS NULL')
            ->execute(['id' => $id]);
    }

    /**
     * Aktive Links – optional auf eine Galerie gefiltert. `null` filtert nicht,
     * `0` liefert nur Auffangraum-Links.
     *
     * @return list<array<string,mixed>>
     */
    public function active(?int $galleryFilter = null): array
    {
        $sql = 'SELECT l.*, g.title AS gallery_title
                FROM gallery_upload_links l
                LEFT JOIN galleries g ON g.id = l.gallery_id
                WHERE l.revoked_at IS NULL
                  AND (l.expires_at IS NULL OR l.expires_at >= NOW())';
        $params = [];
        if ($galleryFilter === 0) {
            $sql .= ' AND l.gallery_id IS NULL';
        } elseif ($galleryFilter !== null) {
            $sql .= ' AND l.gallery_id = :g';
            $params['g'] = $galleryFilter;
        }
        $sql .= ' ORDER BY l.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Abgelaufene/widerrufene Links nach einer Frist ganz entfernen. */
    public function pruneOld(int $graceDays = 30): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM gallery_upload_links
                 WHERE (revoked_at IS NOT NULL AND revoked_at < (NOW() - INTERVAL :d1 DAY))
                    OR (expires_at IS NOT NULL AND expires_at < (NOW() - INTERVAL :d2 DAY))'
            );
            $stmt->bindValue(':d1', $graceDays, PDO::PARAM_INT);
            $stmt->bindValue(':d2', $graceDays, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
