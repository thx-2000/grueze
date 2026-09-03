<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Daten-Check-Links: Token, mit dem eine Person ihre eigenen Kontaktdaten ohne
 * Login prüfen und korrigieren kann. Der Token steht nur als SHA-256-Hash in
 * der Datenbank; die Klartext-Form sieht nur, wer den Link erzeugt.
 */
final class DataCheckRepository
{
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS contact_data_checks (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    contact_id INT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL,
                    UNIQUE KEY uq_contact_data_checks_token (token_hash),
                    KEY idx_contact_data_checks_contact (contact_id),
                    CONSTRAINT fk_cdc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
                    CONSTRAINT fk_cdc_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /**
     * Neuen Link erzeugen. Frühere offene Links desselben Kontakts werden
     * ungültig, damit immer nur einer aktiv ist.
     *
     * @return string Klartext-Token für die URL
     */
    public function create(int $contactId, ?int $userId, int $days): string
    {
        $this->revokeForContact($contactId);

        $token = bin2hex(random_bytes(32));
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_data_checks (contact_id, token_hash, created_by, expires_at)
             VALUES (:contact_id, :token_hash, :created_by, DATE_ADD(NOW(), INTERVAL :days DAY))'
        );
        $stmt->bindValue(':contact_id', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', self::hashToken($token));
        $stmt->bindValue(':created_by', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $token;
    }

    /**
     * Gültigen Link zu einem Token finden – nur wenn nicht abgelaufen und der
     * Kontakt weder archiviert noch im Papierkorb liegt.
     *
     * @return array<string,mixed>|null
     */
    public function findValidByToken(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT dc.*, c.vorname, c.nachname
             FROM contact_data_checks dc
             JOIN contacts c ON c.id = dc.contact_id
             WHERE dc.token_hash = :hash
               AND dc.expires_at >= NOW()
               AND c.archived_at IS NULL AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['hash' => self::hashToken($token)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $this->pdo->prepare('UPDATE contact_data_checks SET used_at = NOW() WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function revokeForContact(int $contactId): void
    {
        $this->pdo->prepare('DELETE FROM contact_data_checks WHERE contact_id = :id')
            ->execute(['id' => $contactId]);
    }

    /** Aktiver (nicht abgelaufener) Link eines Kontakts – für die Detailseite. */
    public function activeForContact(int $contactId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contact_data_checks
             WHERE contact_id = :id AND expires_at >= NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $contactId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function purgeExpired(): int
    {
        return $this->pdo->exec('DELETE FROM contact_data_checks WHERE expires_at < (NOW() - INTERVAL 7 DAY)') ?: 0;
    }
}
