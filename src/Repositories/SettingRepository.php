<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class SettingRepository
{
    private ?bool $tableReady = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        if (!$this->ensureTable()) {
            return $default;
        }

        $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : $default;
    }

    public function set(string $key, string $value): void
    {
        if (!$this->ensureTable()) {
            throw new RuntimeException('Die Einstellungs-Tabelle konnte nicht angelegt werden.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }

    public function mailFooter(): string
    {
        $value = trim((string) $this->get('mail_footer', ''));

        return $value !== '' ? $value : $this->defaultMailFooter();
    }

    public function defaultMailFooter(): string
    {
        return (string) config('defaults.mail_footer', <<<'TEXT'
Du erhältst diese Nachricht, weil du auf dem Verteiler eingetragen bist.
Wir möchten den Mailverkehr möglichst gering halten und schreiben daher nur, wenn es wirklich etwas Relevantes gibt.
Antworten auf diese Nachricht gehen an das Orga-Team.
Falls unsere Nachrichten fälschlich als Spam erkannt werden, nimm bitte kontakt@example.org und mailer@example.org in dein Adressbuch auf.
Wenn du keine weiteren Nachrichten erhalten möchtest, schreibe bitte an kontakt@example.org. Wir nehmen dich dann aus dem Verteiler.
TEXT);
    }

    private function ensureTable(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
                    setting_value MEDIUMTEXT NOT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->tableReady = true;
        } catch (\Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }
}
