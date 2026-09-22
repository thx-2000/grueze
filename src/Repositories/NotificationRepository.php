<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Abos für Admin-Benachrichtigungen per Mail („bei jedem Login", „bei
 * Änderungen an dieser Person", …) und der Versand-Rhythmus dafür. Das
 * eigentliche Einreihen einzelner Ereignisse in `notification_queue`
 * passiert bewusst nicht hier, sondern direkt in `UserRepository::touchLogin()`
 * (Login) bzw. `LogRepository::addAudit()` (Änderung) – die zentralen
 * Stellen, durch die diese Ereignisse ohnehin schon laufen.
 */
final class NotificationRepository
{
    private static bool $schemaChecked = false;

    private const FREQUENCY_LABELS = [
        'sofort' => 'Sofort',
        'alle_5_min' => 'Alle 5 Minuten',
        'alle_10_min' => 'Alle 10 Minuten',
        'stuendlich' => 'Stündlich',
        'alle_6_stunden' => 'Alle 6 Stunden',
        'taeglich' => 'Täglich',
        'woechentlich' => 'Wöchentlich',
        'monatlich' => 'Monatlich',
    ];

    /** Minuten je Rhythmus – Grundlage für die Fälligkeitsprüfung im Scheduler. */
    private const FREQUENCY_MINUTES = [
        'sofort' => 0,
        'alle_5_min' => 5,
        'alle_10_min' => 10,
        'stuendlich' => 60,
        'alle_6_stunden' => 360,
        'taeglich' => 1440,
        'woechentlich' => 10080,
        'monatlich' => 43200,
    ];

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public static function frequencyLabels(): array
    {
        return self::FREQUENCY_LABELS;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS notification_subscriptions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    event_type ENUM('login', 'change') NOT NULL,
                    scope ENUM('all', 'contact', 'group') NOT NULL,
                    target_id INT UNSIGNED NULL,
                    frequency ENUM('sofort', 'alle_5_min', 'alle_10_min', 'stuendlich', 'alle_6_stunden', 'taeglich', 'woechentlich', 'monatlich') NOT NULL DEFAULT 'sofort',
                    last_sent_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_notification_subscriptions_user (user_id),
                    KEY idx_notification_subscriptions_match (event_type, scope, target_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS notification_queue (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    subscription_id INT UNSIGNED NOT NULL,
                    occurred_at DATETIME NOT NULL,
                    summary VARCHAR(500) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_notification_queue_subscription (subscription_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * Abos dieses Admins, angereichert um Kontakt-/Gruppenname zur Anzeige.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ns.*,
                    TRIM(CONCAT(c.vorname, " ", c.nachname)) AS contact_name,
                    g.name AS group_name
             FROM notification_subscriptions ns
             LEFT JOIN contacts c ON c.id = ns.target_id AND ns.scope = "contact"
             LEFT JOIN contact_groups g ON g.id = ns.target_id AND ns.scope = "group"
             WHERE ns.user_id = :uid
             ORDER BY ns.event_type ASC, ns.scope ASC, ns.created_at ASC'
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll();
    }

    /** Legt ein Abo an, sofern es dieses genaue Ziel für den Admin noch nicht gibt. */
    public function subscribe(int $userId, string $eventType, string $scope, ?int $targetId, string $frequency): void
    {
        if ($this->find($userId, $eventType, $scope, $targetId) !== null) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO notification_subscriptions (user_id, event_type, scope, target_id, frequency)
             VALUES (:uid, :event_type, :scope, :target_id, :frequency)'
        )->execute([
            'uid' => $userId,
            'event_type' => $eventType,
            'scope' => $scope,
            'target_id' => $targetId,
            'frequency' => $frequency,
        ]);
    }

    public function find(int $userId, string $eventType, string $scope, ?int $targetId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_subscriptions
             WHERE user_id = :uid AND event_type = :event_type AND scope = :scope
             AND target_id ' . ($targetId === null ? 'IS NULL' : '= :target_id') . '
             LIMIT 1'
        );
        $params = ['uid' => $userId, 'event_type' => $eventType, 'scope' => $scope];
        if ($targetId !== null) {
            $params['target_id'] = $targetId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function updateFrequency(int $id, int $userId, string $frequency): void
    {
        $this->pdo->prepare(
            'UPDATE notification_subscriptions SET frequency = :frequency WHERE id = :id AND user_id = :uid'
        )->execute(['frequency' => $frequency, 'id' => $id, 'uid' => $userId]);
    }

    /**
     * Löscht das Abo samt wartender Warteschlangen-Einträge. Räumt die
     * Queue explizit mit auf statt sich allein auf die FK-Kaskade zu
     * verlassen – die lazy `ensureSchema()`-Variante (ohne Migration) legt
     * die Tabellen ohne Fremdschlüssel an.
     */
    public function unsubscribe(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM notification_subscriptions WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        if ($stmt->fetchColumn() === false) {
            return;
        }

        $this->pdo->prepare('DELETE FROM notification_queue WHERE subscription_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM notification_subscriptions WHERE id = :id AND user_id = :uid')
            ->execute(['id' => $id, 'uid' => $userId]);
    }

    /**
     * Abos, für die gerade etwas in der Warteschlange liegt UND laut Rhythmus
     * fällig sind (bei „sofort" reicht ein einziger wartender Eintrag).
     *
     * @return list<array<string, mixed>>
     */
    public function dueSubscriptions(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ns.*, u.email AS user_email, u.name AS user_name,
                    COUNT(nq.id) AS pending_count
             FROM notification_subscriptions ns
             JOIN users u ON u.id = ns.user_id AND u.is_active = 1
             JOIN notification_queue nq ON nq.subscription_id = ns.id
             GROUP BY ns.id'
        );
        $rows = $stmt->fetchAll();

        $due = [];
        foreach ($rows as $row) {
            $minutes = self::FREQUENCY_MINUTES[$row['frequency']] ?? 0;
            $lastSent = $row['last_sent_at'] !== null ? strtotime((string) $row['last_sent_at']) : null;
            $dueNow = $minutes === 0 || $lastSent === null || $lastSent <= time() - ($minutes * 60);
            if ($dueNow) {
                $due[] = $row;
            }
        }

        return $due;
    }

    /** @return list<array<string, mixed>> */
    public function queueFor(int $subscriptionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_queue WHERE subscription_id = :id ORDER BY occurred_at ASC, id ASC'
        );
        $stmt->execute(['id' => $subscriptionId]);

        return $stmt->fetchAll();
    }

    public function markSent(int $subscriptionId): void
    {
        $this->pdo->prepare('DELETE FROM notification_queue WHERE subscription_id = :id')
            ->execute(['id' => $subscriptionId]);
        $this->pdo->prepare('UPDATE notification_subscriptions SET last_sent_at = NOW() WHERE id = :id')
            ->execute(['id' => $subscriptionId]);
    }
}
