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
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /**
     * token_sha notfalls selbst nachrüsten – falls neuer Code läuft, bevor
     * „Verwaltung → Aktualisieren" die Migration eingespielt hat.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'ALTER TABLE registration_invites
                    ADD COLUMN IF NOT EXISTS token_sha CHAR(64) NULL AFTER token_hash,
                    ADD KEY IF NOT EXISTS idx_registration_invites_token_sha (token_sha)'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * Legt eine Einladung an und gibt den rohen Token zurück. Ältere offene
     * Einladungen für dieselbe Adresse werden entwertet.
     */
    public function create(string $email, ?int $contactId, ?int $createdBy, int $hours, ?string $note = null, ?string $ipHash = null): string
    {
        $this->revokePendingForEmail($email);

        $token = bin2hex(random_bytes(24));
        $stmt = $this->pdo->prepare(
            'INSERT INTO registration_invites (email, contact_id, note, token_hash, token_sha, created_by, ip_hash, status, expires_at)
             VALUES (:email, :contact_id, :note, :token_hash, :token_sha, :created_by, :ip_hash, \'pending\', :expires_at)'
        );
        $stmt->execute([
            'email' => $email,
            'contact_id' => $contactId,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'token_hash' => password_hash($token, PASSWORD_DEFAULT),
            'token_sha' => hash('sha256', $token),
            'created_by' => $createdBy,
            'ip_hash' => $ipHash,
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $hours) * 3600),
        ]);

        return $token;
    }

    /** Anfrage einer unbekannten Adresse – wartet auf Freigabe, kein Link. */
    public function createAwaitingApproval(string $email, ?string $note, ?string $ipHash, int $hours): void
    {
        $this->revokePendingForEmail($email);
        $stmt = $this->pdo->prepare(
            'INSERT INTO registration_invites (email, contact_id, note, token_hash, created_by, ip_hash, status, expires_at)
             VALUES (:email, NULL, :note, :token_hash, NULL, :ip_hash, \'awaiting_approval\', :expires_at)'
        );
        $stmt->execute([
            'email' => $email,
            'note' => $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null,
            'token_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            'ip_hash' => $ipHash,
            'expires_at' => date('Y-m-d H:i:s', time() + max(1, $hours * 4) * 3600),
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM registration_invites WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function setStatus(int $id, string $status): void
    {
        $this->pdo->prepare('UPDATE registration_invites SET status = :s WHERE id = :id')
            ->execute(['s' => $status, 'id' => $id]);
    }

    /** Zahl der Anfragen von dieser Quelle in den letzten $minutes Minuten. */
    public function recentCountByIp(string $ipHash, int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM registration_invites
             WHERE ip_hash = :ip AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':ip', $ipHash);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> offene Freigabe-Anfragen */
    public function awaitingApproval(): array
    {
        return $this->pdo->query(
            "SELECT * FROM registration_invites WHERE status = 'awaiting_approval' ORDER BY created_at ASC"
        )->fetchAll();
    }

    /** @return array<string,mixed>|null Einladung samt Kontaktname, wenn Token gültig */
    public function findValidByToken(string $token): ?array
    {
        $token = trim($token);
        // Formatprüfung zuerst: unpassende Eingaben rühren die Datenbank nicht an.
        if (!preg_match('/^[a-f0-9]{48}$/i', $token)) {
            return null;
        }

        // Schneller Weg: die eine Zeile über den SHA-Index holen, danach genau
        // ein bcrypt-Vergleich (konstantzeitig).
        $stmt = $this->pdo->prepare(
            "SELECT ri.*, c.vorname, c.nachname
             FROM registration_invites ri
             LEFT JOIN contacts c ON c.id = ri.contact_id
             WHERE ri.token_sha = :sha AND ri.status = 'pending' AND ri.expires_at >= NOW()
             LIMIT 1"
        );
        $stmt->execute(['sha' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if ($row) {
            return password_verify($token, (string) $row['token_hash']) ? $row : null;
        }

        // Rückfall nur für Einladungen von vor der token_sha-Migration – eng
        // begrenzt und verschwindet, sobald diese Alt-Einladungen abgelaufen sind.
        $legacy = $this->pdo->query(
            "SELECT ri.*, c.vorname, c.nachname
             FROM registration_invites ri
             LEFT JOIN contacts c ON c.id = ri.contact_id
             WHERE ri.token_sha IS NULL AND ri.status = 'pending' AND ri.expires_at >= NOW()
             ORDER BY ri.id DESC
             LIMIT 20"
        )->fetchAll();
        foreach ($legacy as $candidate) {
            if (password_verify($token, (string) $candidate['token_hash'])) {
                return $candidate;
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
