<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Ordner im Dokumente-Bereich. Die Dateien selbst verwaltet
 * DocumentStorageService / DocumentRepository – hier stehen nur Titel,
 * Beschreibung und die Gruppen-Zuordnung (wie bei Galerien).
 */
final class DocumentFolderRepository
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
                'CREATE TABLE IF NOT EXISTS document_folders (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(190) NOT NULL,
                    description TEXT NULL,
                    owner_group_id INT UNSIGNED NULL,
                    visible_group_id INT UNSIGNED NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_document_folders_owner_group (owner_group_id),
                    KEY idx_document_folders_visible_group (visible_group_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * Alle Ordner mit Dokumentenzahl.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $sql = 'SELECT f.*,
                       og.name AS owner_group_name,
                       vg.name AS visible_group_name,
                       COUNT(d.id) AS document_count
                FROM document_folders f
                LEFT JOIN contact_groups og ON og.id = f.owner_group_id
                LEFT JOIN contact_groups vg ON vg.id = f.visible_group_id
                LEFT JOIN documents d ON d.folder_id = f.id
                GROUP BY f.id
                ORDER BY f.title ASC';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql = 'SELECT f.*, og.name AS owner_group_name, vg.name AS visible_group_name
                FROM document_folders f
                LEFT JOIN contact_groups og ON og.id = f.owner_group_id
                LEFT JOIN contact_groups vg ON vg.id = f.visible_group_id
                WHERE f.id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_folders (title, description, owner_group_id, visible_group_id, created_by)
             VALUES (:title, :description, :owner_group_id, :visible_group_id, :created_by)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'owner_group_id' => !empty($data['owner_group_id']) ? (int) $data['owner_group_id'] : null,
            'visible_group_id' => !empty($data['visible_group_id']) ? (int) $data['visible_group_id'] : null,
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE document_folders SET title = :title, description = :description, visible_group_id = :visible_group_id
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'visible_group_id' => !empty($data['visible_group_id']) ? (int) $data['visible_group_id'] : null,
        ]);
    }

    /** Endgültiges Löschen – kein Papierkorb. Der Aufrufer räumt vorher die Dateien weg. */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM document_folders WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Gibt es einen Ordner, der zu einer dieser Gruppen gehört oder auf sie
     * beschränkt ist? Für die Rail-Navigation von Gruppenmitgliedern ohne
     * globales Dokumente-Recht.
     *
     * @param list<int> $groupIds
     */
    public function hasFoldersForGroups(array $groupIds): bool
    {
        $groupIds = array_values(array_filter(array_map('intval', $groupIds), static fn (int $id): bool => $id > 0));
        if ($groupIds === []) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM document_folders
             WHERE owner_group_id IN ({$placeholders}) OR visible_group_id IN ({$placeholders})
             LIMIT 1"
        );
        $stmt->execute(array_merge($groupIds, $groupIds));

        return $stmt->fetchColumn() !== false;
    }
}
