<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function addAudit(int $userId, ?int $contactId, string $action, string $details): void
    {
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

