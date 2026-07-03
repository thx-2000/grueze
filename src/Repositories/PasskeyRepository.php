<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class PasskeyRepository
{
    private ?bool $available = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $this->pdo->query('SELECT 1 FROM user_passkeys LIMIT 1');
            $this->available = true;
        } catch (Throwable) {
            $this->available = false;
        }

        return $this->available;
    }

    public function byUserId(int $userId): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM user_passkeys
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public function countByUserIds(array $userIds): array
    {
        if (!$this->isAvailable() || $userIds === []) {
            return [];
        }

        $userIds = array_values(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT user_id, COUNT(*) AS passkey_count
             FROM user_passkeys
             WHERE user_id IN (' . $placeholders . ')
             GROUP BY user_id'
        );
        $stmt->execute($userIds);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['user_id']] = (int) $row['passkey_count'];
        }

        return $counts;
    }

    public function findByCredentialId(string $credentialId): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT user_passkeys.*, users.name AS user_name, users.email AS user_email, users.is_active
             FROM user_passkeys
             JOIN users ON users.id = user_passkeys.user_id
             WHERE credential_id = :credential_id
             LIMIT 1'
        );
        $stmt->bindValue('credential_id', $credentialId, PDO::PARAM_LOB);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_passkeys (
                user_id,
                credential_id,
                public_key_pem,
                algorithm,
                sign_count,
                transports,
                label,
                aaguid,
                created_at,
                last_used_at,
                last_used_ip
             ) VALUES (
                :user_id,
                :credential_id,
                :public_key_pem,
                :algorithm,
                :sign_count,
                :transports,
                :label,
                :aaguid,
                NOW(),
                NULL,
                NULL
             )'
        );
        $stmt->bindValue('user_id', (int) $data['user_id'], PDO::PARAM_INT);
        $stmt->bindValue('credential_id', $data['credential_id'], PDO::PARAM_LOB);
        $stmt->bindValue('public_key_pem', (string) $data['public_key_pem']);
        $stmt->bindValue('algorithm', (int) ($data['algorithm'] ?? -7), PDO::PARAM_INT);
        $stmt->bindValue('sign_count', (int) ($data['sign_count'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue('transports', (string) ($data['transports'] ?? '[]'));
        $stmt->bindValue('label', (string) ($data['label'] ?? ''));
        $stmt->bindValue('aaguid', (string) ($data['aaguid'] ?? ''));
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function updateUsage(int $id, int $signCount, ?string $ip): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE user_passkeys
             SET sign_count = :sign_count,
                 last_used_at = NOW(),
                 last_used_ip = :last_used_ip
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'sign_count' => $signCount,
            'last_used_ip' => $ip,
        ]);
    }

    public function deleteForUser(int $id, int $userId): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM user_passkeys WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    public function deleteAllForUserId(int $userId): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $stmt = $this->pdo->prepare('DELETE FROM user_passkeys WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }
}
