<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class SettingRepository
{
    private ?bool $tableReady = null;
    private ?array $brandingCache = null;

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
        $this->brandingCache = null;
    }

    public function branding(): array
    {
        if ($this->brandingCache !== null) {
            return $this->brandingCache;
        }

        $branding = $this->brandingDefaults();
        foreach (array_keys($branding) as $key) {
            $stored = $this->get($key);
            if ($stored !== null && trim($stored) !== '') {
                $branding[$key] = $stored;
            }
        }

        return $this->brandingCache = $branding;
    }

    public function brandingDefaults(): array
    {
        return [
            'branding_app_name' => (string) config('app.name', 'Adress-Zentrale'),
            'branding_short_name' => 'GRUEZE',
            'branding_public_site_label' => 'example.org',
            'branding_public_site_url' => 'https://example.org',
            'branding_login_intro' => 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.',
            'branding_login_public_hint' => 'Infos zum Treffen und die öffentliche Startseite findet ihr unter example.org.',
            'branding_sidebar_copy' => 'Kontakte, Mailings und Organisation an einem Ort.',
            'branding_support_email' => 'kontakt@example.org',
            'branding_logo_path' => '',
            'branding_font_display' => '-apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif',
            'branding_font_body' => '-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif',
            'branding_color_bg' => '#e8ebe5',
            'branding_color_bg_alt' => '#dde1da',
            'branding_color_surface' => 'rgba(255, 255, 255, 0.82)',
            'branding_color_surface_strong' => 'rgba(255, 255, 255, 0.92)',
            'branding_color_surface_soft' => 'rgba(243, 245, 240, 0.92)',
            'branding_color_text' => '#181a15',
            'branding_color_muted' => '#5d6258',
            'branding_color_primary' => '#2d3128',
            'branding_color_primary_strong' => '#141610',
            'branding_color_secondary' => '#f0a317',
            'branding_color_accent' => '#d8ef54',
            'branding_color_highlight' => '#eef4c5',
            'branding_color_border' => 'rgba(38, 42, 32, 0.12)',
            'branding_color_danger' => '#b64521',
            'branding_color_success' => '#3f7558',
        ];
    }

    public function brandingThemeVariables(): array
    {
        $branding = $this->branding();

        return [
            '--font-display' => (string) $branding['branding_font_display'],
            '--font-body' => (string) $branding['branding_font_body'],
            '--color-bg' => (string) $branding['branding_color_bg'],
            '--color-bg-alt' => (string) $branding['branding_color_bg_alt'],
            '--color-surface' => (string) $branding['branding_color_surface'],
            '--color-surface-strong' => (string) $branding['branding_color_surface_strong'],
            '--color-surface-soft' => (string) $branding['branding_color_surface_soft'],
            '--color-text' => (string) $branding['branding_color_text'],
            '--color-muted' => (string) $branding['branding_color_muted'],
            '--color-primary' => (string) $branding['branding_color_primary'],
            '--color-primary-strong' => (string) $branding['branding_color_primary_strong'],
            '--color-secondary' => (string) $branding['branding_color_secondary'],
            '--color-accent' => (string) $branding['branding_color_accent'],
            '--color-highlight' => (string) $branding['branding_color_highlight'],
            '--color-border' => (string) $branding['branding_color_border'],
            '--color-danger' => (string) $branding['branding_color_danger'],
            '--color-success' => (string) $branding['branding_color_success'],
        ];
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

    public function subjectPrefixOptions(): array
    {
        $stored = trim((string) $this->get('subject_prefixes', ''));
        $candidates = $stored !== ''
            ? preg_split('/\R+/', $stored) ?: []
            : $this->defaultSubjectPrefixOptions();

        $options = [];
        foreach ($candidates as $candidate) {
            $prefix = trim((string) $candidate);
            if ($prefix === '') {
                continue;
            }

            $options[] = $prefix;
        }

        return $options !== [] ? array_values(array_unique($options)) : ['[Verteiler]'];
    }

    public function defaultSubjectPrefixOptions(): array
    {
        $candidates = (array) config('defaults.subject_prefixes', ['[Verteiler]']);
        $options = [];

        foreach ($candidates as $candidate) {
            $prefix = trim((string) $candidate);
            if ($prefix === '') {
                continue;
            }

            $options[] = $prefix;
        }

        return $options !== [] ? array_values(array_unique($options)) : ['[Verteiler]'];
    }

    public function subjectPrefixesText(): string
    {
        return implode("\n", $this->subjectPrefixOptions());
    }

    public function defaultSubjectPrefixesText(): string
    {
        return implode("\n", $this->defaultSubjectPrefixOptions());
    }

    public function defaultSubjectPrefix(): string
    {
        return $this->subjectPrefixOptions()[0] ?? '[Verteiler]';
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
