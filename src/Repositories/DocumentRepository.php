<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Einzelne Dateien eines Dokumente-Ordners. Physisch liegen sie unter
 * storage/documents/ (DocumentStorageService); hier stehen Pfad und Metadaten.
 */
final class DocumentRepository
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
                'CREATE TABLE IF NOT EXISTS documents (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    folder_id INT UNSIGNED NOT NULL,
                    title VARCHAR(190) NOT NULL,
                    description TEXT NULL,
                    original_name VARCHAR(255) NULL,
                    stored_path VARCHAR(255) NOT NULL,
                    preview_path VARCHAR(255) NULL,
                    mime VARCHAR(150) NOT NULL,
                    byte_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    uploaded_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_documents_folder (folder_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'ALTER TABLE documents ADD COLUMN IF NOT EXISTS preview_path VARCHAR(255) NULL AFTER stored_path'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Alle Dateien über alle Ordner hinweg, mit Ordnertitel – für Picker
     * (z. B. Termine-Ankündigungen, die auf ein Dokument verlinken).
     *
     * @return list<array<string,mixed>>
     */
    public function allWithFolder(): array
    {
        return $this->pdo->query(
            'SELECT d.id, d.title, d.folder_id, f.title AS folder_title
             FROM documents d
             JOIN document_folders f ON f.id = d.folder_id
             ORDER BY f.title ASC, d.title ASC'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public const SORT_MODES = ['title', 'newest', 'oldest', 'largest'];

    /**
     * @param string $sort   title (Standard) / newest / oldest / largest
     * @param string $search Filtert auf Titel, Dateiname und Beschreibung (Teilstring, klein/groß egal)
     */
    public function forFolder(int $folderId, string $sort = 'title', string $search = ''): array
    {
        $order = match ($sort) {
            'newest' => 'created_at DESC, title ASC',
            'oldest' => 'created_at ASC, title ASC',
            'largest' => 'byte_size DESC, title ASC',
            default => 'title ASC',
        };

        $sql = 'SELECT * FROM documents WHERE folder_id = :f';
        $params = ['f' => $folderId];
        if (trim($search) !== '') {
            // Named Platzhalter dürfen bei nativen Prepared Statements
            // (PDO::ATTR_EMULATE_PREPARES = false) nicht mehrfach vorkommen –
            // daher drei eigene Platzhalter statt dreimal :q.
            $sql .= ' AND (title LIKE :q1 OR original_name LIKE :q2 OR description LIKE :q3)';
            $needle = '%' . $search . '%';
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
        }
        $sql .= ' ORDER BY ' . $order;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function add(int $folderId, array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (folder_id, title, description, original_name, stored_path, preview_path, mime, byte_size, uploaded_by)
             VALUES (:folder_id, :title, :description, :original_name, :stored_path, :preview_path, :mime, :byte_size, :uploaded_by)'
        );
        $stmt->execute([
            'folder_id' => $folderId,
            'title' => $data['title'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'original_name' => $data['original_name'] ?? null,
            'stored_path' => $data['stored_path'],
            'preview_path' => $data['preview_path'] ?? null,
            'mime' => $data['mime'],
            'byte_size' => (int) ($data['byte_size'] ?? 0),
            'uploaded_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDetails(int $id, string $title, string $description): void
    {
        $this->pdo->prepare('UPDATE documents SET title = :title, description = :description WHERE id = :id')
            ->execute([
                'id' => $id,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
            ]);
    }

    /** Endgültiges Löschen – kein Papierkorb. Der Aufrufer räumt vorher die Datei weg. */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM documents WHERE id = :id')->execute(['id' => $id]);
    }

    public function deleteAllInFolder(int $folderId): void
    {
        $this->pdo->prepare('DELETE FROM documents WHERE folder_id = :f')->execute(['f' => $folderId]);
    }

    public function countForFolder(int $folderId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE folder_id = :f');
        $stmt->execute(['f' => $folderId]);

        return (int) $stmt->fetchColumn();
    }

    /** Gesamtgröße aller Dateien in Bytes – für die Sicherungs-Vorschau. */
    public function totalBytes(): int
    {
        return (int) $this->pdo->query('SELECT COALESCE(SUM(byte_size), 0) FROM documents')->fetchColumn();
    }
}
