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

    public function create(string $name): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO tags (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
    }
}
