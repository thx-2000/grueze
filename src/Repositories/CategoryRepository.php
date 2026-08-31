<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CategoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }

    /** Kategorien mit Anzahl zugeordneter Kontakte. */
    public function allWithCounts(): array
    {
        return $this->pdo->query(
            'SELECT categories.id, categories.name,
                    (SELECT COUNT(*) FROM contacts WHERE contacts.category_id = categories.id) AS contact_count
             FROM categories
             ORDER BY categories.name'
        )->fetchAll();
    }

    public function create(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
    }

    public function rename(int $id, string $name): void
    {
        $stmt = $this->pdo->prepare('UPDATE categories SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    /** Löscht die Kategorie; betroffene Kontakte verlieren nur die Zuordnung
        (FK ON DELETE SET NULL). */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categories WHERE name = :name';
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

