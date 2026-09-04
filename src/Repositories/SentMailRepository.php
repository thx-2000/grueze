<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Verlauf der gesendeten Serien-Mails. Eine Zeile je abgeschlossenem
 * Versandauftrag – mit Betreff, Text (Rohfassung mit Platzhaltern) und der
 * Empfängerliste inkl. Zustellstatus, damit Sende-Berechtigte eine Nachricht
 * später ansehen und ganz oder an einzelne Personen erneut verschicken können.
 */
final class SentMailRepository
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
                'CREATE TABLE IF NOT EXISTS sent_mails (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    sender_name VARCHAR(190) NOT NULL DEFAULT \'\',
                    kind VARCHAR(20) NOT NULL DEFAULT \'rundmail\',
                    subject VARCHAR(255) NOT NULL,
                    subject_prefix VARCHAR(120) NOT NULL DEFAULT \'\',
                    body MEDIUMTEXT NOT NULL,
                    salutation_mode VARCHAR(20) NOT NULL DEFAULT \'auto\',
                    sender_key VARCHAR(64) NOT NULL DEFAULT \'\',
                    reply_to_key VARCHAR(64) NOT NULL DEFAULT \'\',
                    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
                    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
                    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
                    recipients LONGTEXT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sent_mails_user (user_id, created_at),
                    INDEX idx_sent_mails_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * @param array{
     *   user_id:int, sender_name:string, kind:string, subject:string,
     *   subject_prefix:string, body:string, salutation_mode:string,
     *   sender_key:string, reply_to_key:string,
     *   recipients:list<array{contact_id:int,email:string,name:string,status:string,error:?string}>
     * } $data
     */
    public function record(array $data): int
    {
        $recipients = $data['recipients'];
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $row) {
            if (($row['status'] ?? '') === 'gesendet') {
                $sent++;
            } else {
                $failed++;
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sent_mails
             (user_id, sender_name, kind, subject, subject_prefix, body, salutation_mode,
              sender_key, reply_to_key, recipient_count, sent_count, failed_count, recipients)
             VALUES
             (:user_id, :sender_name, :kind, :subject, :subject_prefix, :body, :salutation_mode,
              :sender_key, :reply_to_key, :recipient_count, :sent_count, :failed_count, :recipients)'
        );
        $stmt->execute([
            'user_id' => $data['user_id'],
            'sender_name' => mb_substr($data['sender_name'], 0, 190),
            'kind' => mb_substr($data['kind'], 0, 20),
            'subject' => mb_substr($data['subject'], 0, 255),
            'subject_prefix' => mb_substr($data['subject_prefix'], 0, 120),
            'body' => $data['body'],
            'salutation_mode' => mb_substr($data['salutation_mode'], 0, 20),
            'sender_key' => mb_substr($data['sender_key'], 0, 64),
            'reply_to_key' => mb_substr($data['reply_to_key'], 0, 64),
            'recipient_count' => count($recipients),
            'sent_count' => $sent,
            'failed_count' => $failed,
            'recipients' => json_encode(array_values($recipients), JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> */
    public function all(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.user_id, s.sender_name, s.kind, s.subject, s.subject_prefix,
                    s.recipient_count, s.sent_count, s.failed_count, s.created_at,
                    u.name AS current_sender_name
             FROM sent_mails s
             LEFT JOIN users u ON u.id = s.user_id
             ORDER BY s.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.name AS current_sender_name
             FROM sent_mails s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['recipients'] = json_decode((string) $row['recipients'], true) ?: [];

        return $row;
    }

    public function pruneOld(int $days): int
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sent_mails WHERE created_at < (NOW() - INTERVAL :days DAY)');
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
