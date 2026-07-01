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
            'SELECT users.*, roles.name AS role_name FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.*, roles.name AS role_name FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT users.*, roles.name AS role_name FROM users
             JOIN roles ON roles.id = users.role_id
             ORDER BY users.created_at DESC'
        )->fetchAll();
    }

    public function roles(): array
    {
        return $this->pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role_id, is_active)
             VALUES (:name, :email, :password_hash, :role_id, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? 1,
        ]);

        return (int) $this->pdo->lastInsertId();
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
}
