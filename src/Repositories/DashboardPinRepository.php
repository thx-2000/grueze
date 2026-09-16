<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Persönlich angepinnte Kacheln auf der Startseite (pro Zugang, nicht pro
 * Kontakt) – aus dem Einstellungen-Hub oder generisch von jeder anderen
 * Seite aus anpinnbar. Reihenfolge per `position`, per Ziehen änderbar.
 */
final class DashboardPinRepository
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
                'CREATE TABLE IF NOT EXISTS dashboard_pins (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    path VARCHAR(255) NOT NULL,
                    label VARCHAR(190) NOT NULL,
                    icon VARCHAR(40) NULL,
                    description VARCHAR(255) NULL,
                    position INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_dashboard_pins (user_id, path),
                    KEY idx_dashboard_pins_user (user_id, position)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /** @return list<array<string,mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM dashboard_pins WHERE user_id = :uid ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll();
    }

    /** ID der Pin-Zeile für diesen Pfad, falls die Person ihn schon angepinnt hat. */
    public function idFor(int $userId, string $path): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM dashboard_pins WHERE user_id = :uid AND path = :path LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'path' => mb_substr($path, 0, 255)]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Anpinnen – still, wenn schon angepinnt (kein Fehler, kein Duplikat).
     * Neue Kacheln landen am Ende der bestehenden Reihenfolge.
     */
    public function pin(int $userId, string $path, string $label, ?string $icon = null, ?string $description = null): void
    {
        $path = mb_substr($path, 0, 255);
        if ($this->idFor($userId, $path) !== null) {
            return;
        }

        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM dashboard_pins WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $nextPosition = (int) $stmt->fetchColumn();

        $this->pdo->prepare(
            'INSERT INTO dashboard_pins (user_id, path, label, icon, description, position)
             VALUES (:uid, :path, :label, :icon, :description, :position)'
        )->execute([
            'uid' => $userId,
            'path' => $path,
            'label' => mb_substr($label, 0, 190) ?: $path,
            'icon' => $icon !== null && $icon !== '' ? mb_substr($icon, 0, 40) : null,
            'description' => $description !== null && $description !== '' ? mb_substr($description, 0, 255) : null,
            'position' => $nextPosition,
        ]);
    }

    /** Nur die eigenen Kacheln lassen sich lösen (scoped auf user_id). */
    public function unpin(int $id, int $userId): void
    {
        $this->pdo->prepare('DELETE FROM dashboard_pins WHERE id = :id AND user_id = :uid')
            ->execute(['id' => $id, 'uid' => $userId]);
    }

    /** @param list<int> $orderedIds */
    public function reorder(int $userId, array $orderedIds): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE dashboard_pins SET position = :p WHERE id = :id AND user_id = :uid'
        );
        $position = 1;
        foreach ($orderedIds as $id) {
            $stmt->execute(['p' => $position++, 'id' => (int) $id, 'uid' => $userId]);
        }
    }
}
