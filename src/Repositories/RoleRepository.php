<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Rollen: interner Schlüssel (`name`) + frei editierbarer Anzeigename (`label`)
 * + Beschreibung. Rechte- und Sichtbarkeits-Matrix hängen am `name`, daher
 * bleibt der nach dem Anlegen unveränderlich.
 */
final class RoleRepository
{
    public const PROTECTED_NAME = 'admin';

    private ?bool $schemaReady = null;

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $this->ensureSchema();

        return $this->pdo->query('SELECT id, name, label, description FROM roles ORDER BY id')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT id, name, label, description FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** name → label (Anzeigename), für Badges und Auswahllisten. */
    public function labelMap(): array
    {
        $map = [];
        foreach ($this->all() as $role) {
            $label = trim((string) ($role['label'] ?? ''));
            $map[$role['name']] = $label !== '' ? $label : (string) $role['name'];
        }

        return $map;
    }

    public function labelExists(string $label, ?int $exceptId = null): bool
    {
        $this->ensureSchema();
        $sql = 'SELECT COUNT(*) FROM roles WHERE LOWER(label) = LOWER(:label)';
        $params = ['label' => $label];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $label, string $description): int
    {
        $this->ensureSchema();
        $name = $this->uniqueNameFrom($label);
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (name, label, description) VALUES (:name, :label, :description)'
        );
        $stmt->execute(['name' => $name, 'label' => $label, 'description' => $description]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateMeta(int $id, string $label, string $description): void
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('UPDATE roles SET label = :label, description = :description WHERE id = :id');
        $stmt->execute(['label' => $label, 'description' => $description, 'id' => $id]);
    }

    /**
     * Internen Schlüssel einer Rolle ändern. „admin" bleibt fix. Die Rechte- und
     * Sichtbarkeitseinträge zieht der Aufrufer nach (SettingRepository).
     *
     * @return array{old: string, new: string}|null  null = nicht möglich
     */
    public function renameSlug(int $id, string $desiredSlug): ?array
    {
        $this->ensureSchema();
        $role = $this->find($id);
        if ($role === null || $role['name'] === self::PROTECTED_NAME) {
            return null;
        }

        $slug = $this->slugify($desiredSlug);
        if ($slug === '' || $slug === self::PROTECTED_NAME) {
            return null;
        }
        if ($slug === $role['name']) {
            return ['old' => $role['name'], 'new' => $slug];
        }

        $suffix = 2;
        $candidate = $slug;
        while ($this->slugTaken($candidate, $id)) {
            $candidate = substr($slug, 0, 37) . '-' . $suffix;
            $suffix++;
        }

        $this->pdo->prepare('UPDATE roles SET name = :name WHERE id = :id')
            ->execute(['name' => $candidate, 'id' => $id]);

        return ['old' => (string) $role['name'], 'new' => $candidate];
    }

    /** Erste Nicht-Admin-Rolle (kleinste id) – Fallback für die Standard-Rolle. */
    public function firstNonAdminName(): ?string
    {
        $this->ensureSchema();
        $stmt = $this->pdo->query(
            "SELECT name FROM roles WHERE name <> '" . self::PROTECTED_NAME . "' ORDER BY id LIMIT 1"
        );
        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return substr($slug, 0, 40);
    }

    private function slugTaken(string $slug, int $exceptId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = :name AND id <> :id');
        $stmt->execute(['name' => $slug, 'id' => $exceptId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM roles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function userCount(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE role_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn();
    }

    private function uniqueNameFrom(string $label): string
    {
        $base = strtolower(trim($label));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '' || strlen($base) < 2) {
            $base = 'rolle';
        }
        $base = substr($base, 0, 40);

        $name = $base;
        $suffix = 2;
        while ($this->nameExists($name)) {
            $name = substr($base, 0, 37) . '-' . $suffix;
            $suffix++;
        }

        return $name;
    }

    private function nameExists(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = :name');
        $stmt->execute(['name' => $name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Fügt die label-Spalte lazy hinzu, falls die Migration noch aussteht. */
    private function ensureSchema(): void
    {
        if ($this->schemaReady === true) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE roles ADD COLUMN IF NOT EXISTS label VARCHAR(80) NOT NULL DEFAULT '' AFTER name");
            $this->pdo->exec(
                "UPDATE roles SET label = CONCAT(UPPER(SUBSTRING(name, 1, 1)), SUBSTRING(name, 2)) WHERE label = ''"
            );
        } catch (\Throwable) {
            // Ältere MariaDB ohne IF NOT EXISTS: Spalte ist dann bereits da.
        }

        $this->schemaReady = true;
    }
}
