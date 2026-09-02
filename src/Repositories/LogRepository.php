<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LogRepository
{
    private ?bool $hasChangesColumn = null;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, array{from: string, to: string}> $changes Feld → alt/neu
     */
    public function addAudit(int $userId, ?int $contactId, string $action, string $details, array $changes = []): void
    {
        if ($this->changesColumnAvailable()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO audit_log (user_id, contact_id, action, details, changes)
                 VALUES (:user_id, :contact_id, :action, :details, :changes)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'contact_id' => $contactId,
                'action' => $action,
                'details' => $details,
                'changes' => $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (user_id, contact_id, action, details) VALUES (:user_id, :contact_id, :action, :details)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'contact_id' => $contactId,
            'action' => $action,
            'details' => $details,
        ]);
    }

    public function auditEntries(): array
    {
        return $this->pdo->query(
            'SELECT audit_log.*, users.name AS user_name, contacts.vorname, contacts.nachname
             FROM audit_log
             JOIN users ON users.id = audit_log.user_id
             LEFT JOIN contacts ON contacts.id = audit_log.contact_id
             ORDER BY audit_log.created_at DESC
             LIMIT 200'
        )->fetchAll();
    }

    /**
     * Änderungsverlauf eines einzelnen Kontakts, neueste zuerst. `changes` ist
     * bereits als Array dekodiert (leer, wenn nichts protokolliert wurde).
     *
     * @return list<array<string, mixed>>
     */
    public function contactAuditTrail(int $contactId, int $limit = 100): array
    {
        $withChanges = $this->changesColumnAvailable();
        $stmt = $this->pdo->prepare(
            'SELECT audit_log.id, audit_log.action, audit_log.details, audit_log.created_at, '
            . ($withChanges ? 'audit_log.changes,' : "NULL AS changes,")
            . ' users.name AS user_name
             FROM audit_log
             JOIN users ON users.id = audit_log.user_id
             WHERE audit_log.contact_id = :contact_id
             ORDER BY audit_log.created_at DESC, audit_log.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':contact_id', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as $index => $row) {
            $decoded = $row['changes'] !== null && $row['changes'] !== ''
                ? json_decode((string) $row['changes'], true)
                : [];
            $rows[$index]['changes'] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }

    private function changesColumnAvailable(): bool
    {
        if ($this->hasChangesColumn === null) {
            try {
                $this->pdo->query('SELECT changes FROM audit_log LIMIT 0');
                $this->hasChangesColumn = true;
            } catch (\PDOException) {
                $this->hasChangesColumn = false;
            }
        }

        return $this->hasChangesColumn;
    }

    public function addMailLog(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mail_log (user_id, contact_id, empfaenger_email, betreff, status, fehlermeldung)
             VALUES (:user_id, :contact_id, :empfaenger_email, :betreff, :status, :fehlermeldung)'
        );
        $stmt->execute($data);
    }

    public function mailEntries(): array
    {
        return $this->pdo->query(
            'SELECT mail_log.*, users.name AS user_name, contacts.vorname, contacts.nachname
             FROM mail_log
             JOIN users ON users.id = mail_log.user_id
             LEFT JOIN contacts ON contacts.id = mail_log.contact_id
             ORDER BY mail_log.gesendet_am DESC
             LIMIT 200'
        )->fetchAll();
    }

    public function addLoginAttempt(string $email, string $ip, bool $successful): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, successful) VALUES (:email, :ip, :successful)'
        );
        $stmt->execute([
            'email' => $email,
            'ip' => $ip,
            'successful' => $successful ? 1 : 0,
        ]);
    }

    public function recentFailedAttempts(string $email, string $ip, int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :email AND ip_address = :ip AND successful = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', $ip);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}

