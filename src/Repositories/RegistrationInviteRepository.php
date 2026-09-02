<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Einladungs-/Registrierungs-Token. Der rohe Token steht nur im Link; in der
 * Datenbank liegt ein Hash (wie beim Passwort-Reset).
 */
final class RegistrationInviteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Legt eine Einladung an und gibt den rohen Token zurück. Ältere offene
     * Einladungen für dieselbe Adresse werden entwertet.
     */
    public function create(string $email, ?int $contactId, ?int $createdBy, int $hours, string $status = 'pending'): string
    {
        $this->revokePendingForEmail($email);

        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            'INSERT INTO registration_invites (email, contact_id, token_hash, created_by, status, expires_at)
             VALUES (:email, :contact_id, :token_hash, :created_by, :status, :expires_at)'
        );
        $stmt->execute([
            'email' => $email,
            'contact_id' => $contactId,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'created_by' => $createdBy,
            'status' => $status,
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $hours) * 3600),
        ]);

        return $token;
    }

    /** @return array<string,mixed>|null Einladung samt Kontaktname, wenn Token gültig */
    public function findValidByToken(string $token): ?array
    {
        $rows = $this->pdo->query(
            "SELECT ri.*, c.vorname, c.nachname
             FROM registration_invites ri
             LEFT JOIN contacts c ON c.id = ri.contact_id
             WHERE ri.status = 'pending' AND ri.expires_at >= NOW()
             ORDER BY ri.id DESC
             LIMIT 50"
        )->fetchAll();

        foreach ($rows as $row) {
            if (password_verify($token, (string) $row['token_hash'])) {
                return $row;
            }
        }

        return null;
    }

    public function markUsed(int $id): void
    {
        $this->pdo->prepare("UPDATE registration_invites SET status = 'used', used_at = NOW() WHERE id = :id")
            ->execute(['id' => $id]);
    }

    public function pendingForEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM registration_invites
             WHERE email = :email AND status = 'pending' AND expires_at >= NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function revokePendingForEmail(string $email): void
    {
        $this->pdo->prepare(
            "UPDATE registration_invites SET status = 'revoked'
             WHERE email = :email AND status IN ('pending', 'awaiting_approval')"
        )->execute(['email' => $email]);
    }

    /** Offene Einladungen für die Verwaltungs-Übersicht. */
    public function open(): array
    {
        return $this->pdo->query(
            "SELECT ri.*, c.vorname, c.nachname, u.name AS creator_name
             FROM registration_invites ri
             LEFT JOIN contacts c ON c.id = ri.contact_id
             LEFT JOIN users u ON u.id = ri.created_by
             WHERE ri.status = 'pending'
             ORDER BY ri.created_at DESC"
        )->fetchAll();
    }
}
