<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Gespeicherte Empfängerlisten der Rundmail: benannte Momentaufnahmen einer
 * Kontaktauswahl (Kontakt-IDs als JSON).
 */
final class RecipientListRepository
{
    private ?bool $tableReady = null;

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{id:int,name:string,contact_ids:list<int>,created_at:string}> */
    public function all(): array
    {
        if (!$this->ensureTable()) {
            return [];
        }

        $rows = $this->pdo->query('SELECT id, name, contact_ids, created_at FROM mail_recipient_lists ORDER BY name')->fetchAll();

        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'contact_ids' => $this->decodeIds($row['contact_ids']),
            'created_at' => (string) $row['created_at'],
        ], $rows);
    }

    /** @return array{id:int,name:string,contact_ids:list<int>}|null */
    public function find(int $id): ?array
    {
        if (!$this->ensureTable()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, name, contact_ids FROM mail_recipient_lists WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'contact_ids' => $this->decodeIds($row['contact_ids']),
        ];
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        if (!$this->ensureTable()) {
            return false;
        }

        $sql = 'SELECT 1 FROM mail_recipient_lists WHERE name = :name';
        $params = ['name' => $name];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    /** @param list<int> $contactIds */
    public function create(string $name, array $contactIds, ?int $userId): int
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare(
            'INSERT INTO mail_recipient_lists (name, contact_ids, created_by) VALUES (:name, :ids, :user)'
        );
        $stmt->execute([
            'name' => $name,
            'ids' => $this->encodeIds($contactIds),
            'user' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE mail_recipient_lists SET name = :name WHERE id = :id');
        $stmt->execute(['name' => $name, 'id' => $id]);
    }

    /** @param list<int> $contactIds */
    public function replaceMembers(int $id, array $contactIds): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('UPDATE mail_recipient_lists SET contact_ids = :ids WHERE id = :id');
        $stmt->execute(['ids' => $this->encodeIds($contactIds), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare('DELETE FROM mail_recipient_lists WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param list<int> $ids */
    private function encodeIds(array $ids): string
    {
        $clean = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $n): bool => $n > 0)));

        return json_encode($clean, JSON_THROW_ON_ERROR);
    }

    /** @return list<int> */
    private function decodeIds(mixed $raw): array
    {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn (int $n): bool => $n > 0)));
    }

    private function ensureTable(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS mail_recipient_lists (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    contact_ids MEDIUMTEXT NOT NULL,
                    created_by INT UNSIGNED NULL,
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
