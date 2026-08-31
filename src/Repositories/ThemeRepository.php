<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ThemeRepository
{
    private ?bool $tableReady = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function all(): array
    {
        if (!$this->ensureTable()) {
            return [];
        }

        return $this->pdo->query('SELECT * FROM themes ORDER BY name')->fetchAll();
    }

    public function findBySlug(string $slug): ?array
    {
        if (!$this->ensureTable()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM themes WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    public function slugExists(string $slug): bool
    {
        return $this->findBySlug($slug) !== null;
    }

    public function create(string $slug, string $name, string $description, array $tokens, string $basedOn): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'INSERT INTO themes (slug, name, description, tokens, based_on)
             VALUES (:slug, :name, :description, :tokens, :based_on)'
        );
        $stmt->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'tokens' => json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'based_on' => $basedOn,
        ]);
    }

    public function rename(int $id, string $name): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE themes SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    public function updateTokens(int $id, array $tokens): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE themes SET tokens = :tokens WHERE id = :id');
        $stmt->execute([
            'tokens' => json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('DELETE FROM themes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function ensureTable(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS themes (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    slug VARCHAR(80) NOT NULL UNIQUE,
                    name VARCHAR(120) NOT NULL,
                    description VARCHAR(255) NOT NULL DEFAULT "",
                    tokens MEDIUMTEXT NOT NULL,
                    based_on VARCHAR(80) NOT NULL DEFAULT "",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->tableReady = true;
        } catch (\Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }
}
