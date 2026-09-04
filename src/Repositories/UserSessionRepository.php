<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Angemeldete Sitzungen: pro Browser-Session eine Zeile, bei jedem Request
 * aufgefrischt. Dient der Verwaltung als „wer ist gerade online" und als
 * Anmelde-Verlauf; zusätzlich kann eine Sitzung aus der Ferne beendet werden
 * (`revoked_at`) – der nächste Request aus dieser Sitzung meldet sich dann ab.
 */
final class UserSessionRepository
{
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /**
     * Tabelle notfalls selbst anlegen – falls neuer Code hochgeladen wurde,
     * bevor „Verwaltung → Aktualisieren" gelaufen ist. Maßgeblich bleibt die
     * Migration `2026-09-22-anmelde-uebersicht`.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_sessions (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    session_hash CHAR(64) NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    ip_address VARCHAR(64) NOT NULL DEFAULT \'\',
                    user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    ended_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    UNIQUE KEY uniq_user_sessions_hash (session_hash),
                    INDEX idx_user_sessions_user (user_id),
                    INDEX idx_user_sessions_seen (last_seen_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Ohne Rechte für DDL o. Ä. – dann greift die reguläre Migration.
        }
    }

    private function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    /**
     * Sitzung anlegen bzw. auffrischen. Gibt `true` zurück, wenn diese Sitzung
     * aus der Ferne beendet wurde – die aufrufende Stelle soll dann abmelden.
     */
    public function touch(string $sessionId, int $userId, string $ip, string $userAgent): bool
    {
        // IP-Adresse nur speichern, wenn die Installation das ausdrücklich will
        // (security.store_ip). Standard: aus – datenschutzfreundlich.
        if (!(bool) config('security.store_ip', false)) {
            $ip = '';
        }

        $hash = $this->hash($sessionId);
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions (session_hash, user_id, ip_address, user_agent)
             VALUES (:h, :u, :ip, :ua)
             ON DUPLICATE KEY UPDATE
                last_seen_at = NOW(),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                ended_at = NULL'
        );
        $stmt->execute([
            'h' => $hash,
            'u' => $userId,
            'ip' => substr($ip, 0, 64),
            'ua' => substr($userAgent, 0, 255),
        ]);

        $check = $this->pdo->prepare('SELECT revoked_at FROM user_sessions WHERE session_hash = :h LIMIT 1');
        $check->execute(['h' => $hash]);
        $revokedAt = $check->fetchColumn();

        return $revokedAt !== false && $revokedAt !== null;
    }

    /** Beim Abmelden: Sitzung als beendet markieren. */
    public function end(string $sessionId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET ended_at = NOW() WHERE session_hash = :h AND ended_at IS NULL'
        );
        $stmt->execute(['h' => $this->hash($sessionId)]);
    }

    /** Eine Sitzung aus der Ferne beenden (Verwaltung). */
    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at = NOW(), ended_at = COALESCE(ended_at, NOW()) WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Gerade aktive Sitzungen: nicht beendet, nicht widerrufen, innerhalb des
     * Zeitfensters zuletzt gesehen.
     *
     * @return list<array<string,mixed>>
     */
    public function active(int $withinSeconds): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.name AS user_name, u.email AS user_email, r.name AS role_name
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE s.ended_at IS NULL AND s.revoked_at IS NULL
               AND s.last_seen_at >= (NOW() - INTERVAL :sec SECOND)
             ORDER BY s.last_seen_at DESC'
        );
        $stmt->bindValue(':sec', $withinSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Anmelde-Verlauf: die letzten Sitzungen unabhängig vom Zustand.
     *
     * @return list<array<string,mixed>>
     */
    public function history(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.name AS user_name, u.email AS user_email, r.name AS role_name
             FROM user_sessions s
             JOIN users u ON u.id = s.user_id
             JOIN roles r ON r.id = u.role_id
             ORDER BY s.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Alte Sitzungszeilen entfernen (Verlauf begrenzen). */
    public function pruneOld(int $days = 90): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM user_sessions WHERE created_at < (NOW() - INTERVAL :days DAY)'
            );
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Gespeicherte IP-Adressen entfernen – z. B. nachdem `store_ip` aus ist. */
    public function forgetIps(): int
    {
        try {
            return (int) $this->pdo->exec("UPDATE user_sessions SET ip_address = '' WHERE ip_address <> ''");
        } catch (\Throwable) {
            return 0;
        }
    }
}
