<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Ordner im Dokumente-Bereich, optional verschachtelt (parent_id). Die
 * Dateien selbst verwaltet DocumentStorageService / DocumentRepository –
 * hier stehen nur Titel, Beschreibung und die Gruppen-Zuordnung (wie bei
 * Galerien). Ein Unterordner erbt die Gruppen-Zuordnung NICHT automatisch
 * vom Elternordner – jeder Ordner ist rechtlich unabhängig, das hält das
 * Modell einfach und nachvollziehbar.
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
                    parent_id INT UNSIGNED NULL,
                    title VARCHAR(190) NOT NULL,
                    description TEXT NULL,
                    owner_group_id INT UNSIGNED NULL,
                    visible_group_id INT UNSIGNED NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_document_folders_parent (parent_id),
                    KEY idx_document_folders_owner_group (owner_group_id),
                    KEY idx_document_folders_visible_group (visible_group_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'ALTER TABLE document_folders ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL AFTER id'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    private const SELECT_BASE = 'SELECT f.*, og.name AS owner_group_name, vg.name AS visible_group_name,
                       COUNT(DISTINCT d.id) AS document_count, COUNT(DISTINCT sub.id) AS subfolder_count
                FROM document_folders f
                LEFT JOIN contact_groups og ON og.id = f.owner_group_id
                LEFT JOIN contact_groups vg ON vg.id = f.visible_group_id
                LEFT JOIN documents d ON d.folder_id = f.id
                LEFT JOIN document_folders sub ON sub.parent_id = f.id';

    /** Ordner auf oberster Ebene (kein Elternordner). @return list<array<string,mixed>> */
    public function topLevel(): array
    {
        $sql = self::SELECT_BASE . ' WHERE f.parent_id IS NULL GROUP BY f.id ORDER BY f.title ASC';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** Unterordner eines Ordners. @return list<array<string,mixed>> */
    public function childrenOf(int $parentId): array
    {
        $stmt = $this->pdo->prepare(self::SELECT_BASE . ' WHERE f.parent_id = :parent_id GROUP BY f.id ORDER BY f.title ASC');
        $stmt->execute(['parent_id' => $parentId]);

        return $stmt->fetchAll();
    }

    public function hasChildren(int $folderId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM document_folders WHERE parent_id = :id LIMIT 1');
        $stmt->execute(['id' => $folderId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Pfad vom Wurzelordner bis (ausschließlich) diesem Ordner – für die
     * Brotkrumen-Navigation. Bricht defensiv nach 20 Ebenen ab (falls sich
     * je ein Ring einschleicht).
     *
     * @return list<array<string,mixed>>
     */
    public function ancestors(int $folderId): array
    {
        $chain = [];
        $current = $this->find($folderId);
        $guard = 0;
        while ($current !== null && $current['parent_id'] !== null && $guard++ < 20) {
            $parent = $this->find((int) $current['parent_id']);
            if ($parent === null) {
                break;
            }
            array_unshift($chain, $parent);
            $current = $parent;
        }

        return $chain;
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
            'INSERT INTO document_folders (parent_id, title, description, owner_group_id, visible_group_id, created_by)
             VALUES (:parent_id, :title, :description, :owner_group_id, :visible_group_id, :created_by)'
        );
        $stmt->execute([
            'parent_id' => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
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

    /** Alle Ordner (flach, ohne Zähler) – für Picker, die jeden Ordner brauchen. */
    public function allFlat(): array
    {
        return $this->pdo->query('SELECT id, parent_id, title FROM document_folders ORDER BY title ASC')->fetchAll();
    }
}
