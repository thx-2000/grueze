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
        // Läuft die Aktion unter „Als Benutzer anmelden"? Dann festhalten, wer
        // wirklich am Steuer sitzt – sonst würde die Aktion dem Zielkonto
        // zugeschrieben.
        $impersonated = (int) ($_SESSION['impersonated_user_id'] ?? 0);
        $original = (int) ($_SESSION['user_id'] ?? 0);
        if ($impersonated > 0 && $original > 0 && $impersonated !== $original && $userId === $impersonated) {
            $details = trim($details . ' [ausgeführt über „Als Benutzer angemeldet" durch Konto #' . $original . ']');
        }

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
        } else {
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

        if ($action !== 'impersonation_started' && $action !== 'impersonation_stopped') {
            $this->queueChangeNotifications($contactId, $details);
        }
    }

    /**
     * Trägt „bei Änderungen benachrichtigen"-Abos (alle / dieser Kontakt /
     * dessen Gruppen) in `notification_queue` ein. Läuft hier statt in einem
     * separaten Service, weil `addAudit()` der einzige Ort ist, durch den
     * jede protokollierte Änderung ohnehin schon läuft – egal ob Kontakt,
     * Weitere Personen, Galerie oder Dokumente.
     */
    private function queueChangeNotifications(?int $contactId, string $summary): void
    {
        try {
            $groupIds = [];
            if ($contactId !== null) {
                $groupStmt = $this->pdo->prepare('SELECT group_id FROM contact_group_members WHERE contact_id = :cid');
                $groupStmt->execute(['cid' => $contactId]);
                $groupIds = array_map('intval', $groupStmt->fetchAll(PDO::FETCH_COLUMN));
            }

            $this->queueForMatchingSubscriptions('change', $contactId, $groupIds, $summary);
        } catch (\Throwable) {
            // Benachrichtigungstabellen fehlen noch – Migration holt es nach.
        }
    }

    /** @param list<int> $groupIds */
    private function queueForMatchingSubscriptions(string $eventType, ?int $contactId, array $groupIds, string $summary): void
    {
        $conditions = ["ns.scope = 'all'"];
        $params = ['event_type' => $eventType, 'summary' => mb_substr($summary, 0, 500)];

        if ($contactId !== null) {
            $conditions[] = "(ns.scope = 'contact' AND ns.target_id = :contact_id)";
            $params['contact_id'] = $contactId;
        }
        if ($groupIds !== []) {
            $placeholders = [];
            foreach (array_values($groupIds) as $i => $groupId) {
                $key = 'group_id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = $groupId;
            }
            $conditions[] = "(ns.scope = 'group' AND ns.target_id IN (" . implode(',', $placeholders) . '))';
        }

        $sql = 'INSERT INTO notification_queue (subscription_id, occurred_at, summary)
                SELECT ns.id, NOW(), :summary
                FROM notification_subscriptions ns
                WHERE ns.event_type = :event_type AND (' . implode(' OR ', $conditions) . ')';

        $this->pdo->prepare($sql)->execute($params);
    }

    public function auditEntries(): array
    {
        $rows = $this->pdo->query(
            'SELECT audit_log.*, users.name AS user_name, contacts.vorname, contacts.nachname
             FROM audit_log
             JOIN users ON users.id = audit_log.user_id
             LEFT JOIN contacts ON contacts.id = audit_log.contact_id
             ORDER BY audit_log.created_at DESC
             LIMIT 200'
        )->fetchAll();

        foreach ($rows as $index => $row) {
            $decoded = !empty($row['changes'])
                ? json_decode((string) $row['changes'], true)
                : [];
            $rows[$index]['changes'] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
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

    /**
     * Die Herkunft eines Login-Versuchs wird nur pseudonym gespeichert: Für das
     * Rate-Limit reicht ein stabiler Hash der IP, die IP selbst braucht das
     * Log nirgends (sie wird auch nirgends angezeigt).
     */
    private function ipKey(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }
        $pepper = trim((string) config('security.hash_pepper', ''));

        return hash('sha256', $pepper . '|login-attempt|' . $ip);
    }

    public function addLoginAttempt(string $email, string $ip, bool $successful): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, successful) VALUES (:email, :ip, :successful)'
        );
        $stmt->execute([
            'email' => $email,
            'ip' => $this->ipKey($ip),
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
        $stmt->bindValue(':ip', $this->ipKey($ip));
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Fehlversuche von dieser IP über alle Konten – gegen Credential Stuffing. */
    public function recentFailedAttemptsByIp(string $ip, int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip AND successful = 0
             AND attempted_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)'
        );
        $stmt->bindValue(':ip', $this->ipKey($ip));
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** Älter als N Tage aus dem Login-Versuchs-Log entfernen. */
    public function pruneLoginAttempts(int $days = 30): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
            );
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Verbrauchte / abgelaufene Passwort-Reset-Zeilen aufräumen. */
    public function pruneExpiredPasswordResets(): int
    {
        try {
            return (int) $this->pdo->exec(
                'DELETE FROM password_resets
                 WHERE used_at IS NOT NULL OR expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}

