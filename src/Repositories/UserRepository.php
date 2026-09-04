<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.name AS role_name, contacts.vorname, contacts.nachname
             FROM users
             JOIN roles ON roles.id = users.role_id
             LEFT JOIN contacts ON contacts.id = users.contact_id
             WHERE users.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.name AS role_name, contacts.vorname, contacts.nachname
             FROM users
             JOIN roles ON roles.id = users.role_id
             LEFT JOIN contacts ON contacts.id = users.contact_id
             WHERE users.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT users.*, roles.name AS role_name, contacts.vorname, contacts.nachname
             FROM users
             JOIN roles ON roles.id = users.role_id
             LEFT JOIN contacts ON contacts.id = users.contact_id
             ORDER BY users.created_at DESC'
        )->fetchAll();

        return array_map(self::stripSecrets(...), $rows);
    }

    /**
     * Aktive Nutzer:innen mit einer der angegebenen Rollen – z. B. das
     * Orga-Team für den Kontakt-Knopf.
     *
     * @param list<string> $roleNames
     * @return list<array{id:int,name:string,email:string}>
     */
    public function activeByRoleNames(array $roleNames): array
    {
        if ($roleNames === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT users.id, users.name, users.email
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.is_active = 1 AND roles.name IN ($placeholders)
             ORDER BY users.name ASC"
        );
        $stmt->execute(array_values($roleNames));

        return $stmt->fetchAll();
    }

    public function search(string $query, int $limit = 12): array
    {
        $term = '%' . trim($query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.name AS role_name, contacts.vorname, contacts.nachname
             FROM users
             JOIN roles ON roles.id = users.role_id
             LEFT JOIN contacts ON contacts.id = users.contact_id
             WHERE users.name LIKE :term_name
                OR users.email LIKE :term_email
                OR contacts.vorname LIKE :term_vorname
                OR contacts.nachname LIKE :term_nachname
             ORDER BY users.name ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([
            'term_name' => $term,
            'term_email' => $term,
            'term_vorname' => $term,
            'term_nachname' => $term,
        ]);

        return array_map(self::stripSecrets(...), $stmt->fetchAll());
    }

    /** Passwort-Hash aus einer Zeile entfernen, die in Views/Listen geht. */
    private static function stripSecrets(array $row): array
    {
        unset($row['password_hash']);

        return $row;
    }

    public function roles(): array
    {
        return $this->pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
    }

    public function roleIdByName(string $name): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role_id, is_active, contact_id)
             VALUES (:name, :email, :password_hash, :role_id, :is_active, :contact_id)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? 1,
            'contact_id' => $data['contact_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByContactId(int $contactId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.name AS role_name
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.contact_id = :contact_id
             LIMIT 1'
        );
        $stmt->execute(['contact_id' => $contactId]);

        return $stmt->fetch() ?: null;
    }

    public function updateLinkedAccount(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET
             name = :name,
             email = :email,
             role_id = :role_id,
             is_active = :is_active,
             contact_id = :contact_id
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? 1,
            'contact_id' => $data['contact_id'] ?? null,
        ]);
    }

    public function deactivateByContactId(int $contactId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = 0 WHERE contact_id = :contact_id'
        );
        $stmt->execute(['contact_id' => $contactId]);
    }

    public function adminExists(): bool
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE roles.name = 'admin'"
        );

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findRoleIdByName(string $roleName): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        $roleId = $stmt->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    public function touchLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function setActive(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }

    public function activeAdminCount(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*)
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE roles.name = 'admin' AND users.is_active = 1"
        );

        return (int) $stmt->fetchColumn();
    }
}
