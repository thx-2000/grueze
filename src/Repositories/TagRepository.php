<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TagRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM tags ORDER BY name')->fetchAll();
    }

    /** Tags mit Anzahl zugeordneter Kontakte. */
    public function allWithCounts(): array
    {
        return $this->pdo->query(
            'SELECT tags.id, tags.name,
                    (SELECT COUNT(*) FROM contact_tags WHERE contact_tags.tag_id = tags.id) AS contact_count
             FROM tags
             ORDER BY tags.name'
        )->fetchAll();
    }

    public function create(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tags (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tags WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** @return list<int> Kontakt-IDs mit diesem Tag */
    public function contactIdsForTag(int $tagId): array
    {
        $stmt = $this->pdo->prepare('SELECT contact_id FROM contact_tags WHERE tag_id = :id');
        $stmt->execute(['id' => $tagId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function rename(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE tags SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    /** Löscht den Tag; die Zuordnungen verschwinden mit (FK ON DELETE CASCADE),
        die Kontakte bleiben unverändert. */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tags WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM tags WHERE name = :name';
        $params = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }
}
