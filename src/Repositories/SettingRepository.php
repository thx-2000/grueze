<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class SettingRepository
{
    private ?bool $tableReady = null;
    private ?array $brandingCache = null;
    private ?array $mailSettingsCache = null;
    private ?array $fieldVisibilityCache = null;
    private ?array $permissionMatrixCache = null;

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
        $this->mailSettingsCache = null;
        $this->fieldVisibilityCache = null;
        $this->permissionMatrixCache = null;
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

        $legacyReplacements = [
            'branding_app_name' => [
                'GRUEZE' => 'Adress-Zentrale',
            ],
            'branding_short_name' => [
                'GRUEZE' => 'GRUEZE',
            ],
            'branding_public_site_label' => [
                'grueze.eu' => 'example.org',
            ],
            'branding_public_site_url' => [
                'https://grueze.eu' => 'https://example.org',
            ],
            'branding_login_public_hint' => [
                'Weitere Infos und die öffentliche Startseite findet ihr unter grueze.eu.' => 'Weitere Infos und die öffentliche Startseite findet ihr unter example.org.',
            ],
        ];

        foreach ($legacyReplacements as $key => $map) {
            $current = (string) ($branding[$key] ?? '');
            if (array_key_exists($current, $map)) {
                $branding[$key] = $map[$current];
            }
        }

        return $this->brandingCache = $branding;
    }

    public function brandingDefaults(): array
    {
        return [
            'branding_app_name' => 'Adress-Zentrale',
            'branding_short_name' => 'GRUEZE',
            'branding_version' => '0.2.0',
            'branding_public_site_label' => 'example.org',
            'branding_public_site_url' => 'https://example.org',
            'branding_login_intro' => 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.',
            'branding_login_public_hint' => 'Weitere Infos und die öffentliche Startseite findet ihr unter example.org.',
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

    public function fieldVisibilityDefaults(): array
    {
        return [
            'address'  => ['admin', 'orga'],
            'birthday' => ['admin', 'orga'],
            'emails'   => ['admin', 'orga'],
            'phones'   => ['admin', 'orga'],
            'notes'    => ['admin', 'orga'],
            'login'    => ['admin', 'orga'],
        ];
    }

    public function fieldVisibility(): array
    {
        if ($this->fieldVisibilityCache !== null) {
            return $this->fieldVisibilityCache;
        }

        $result = [];
        foreach ($this->fieldVisibilityDefaults() as $field => $defaultRoles) {
            $stored = $this->get('security_visibility_' . $field);
            if ($stored !== null) {
                $result[$field] = $stored === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $stored))));
            } else {
                $result[$field] = $defaultRoles;
            }
        }

        return $this->fieldVisibilityCache = $result;
    }

    public function permissionDefaults(): array
    {
        return [
            'contacts.manage'      => ['orga'],
            'contacts.delete'      => ['orga'],
            'categories.manage'    => ['orga'],
            'contacts.export'      => [],
            'contacts.copy_emails' => ['orga'],
            'audit.view'           => [],
            'users.manage'         => [],
            'mail.send'            => ['orga'],
            'mail.contact_single'  => ['stufenmitglied'],
            'mail.view_log'        => ['orga'],
            'settings.manage'      => ['orga'],
        ];
    }

    public function permissionMatrix(): array
    {
        if ($this->permissionMatrixCache !== null) {
            return $this->permissionMatrixCache;
        }

        $result = [];
        foreach ($this->permissionDefaults() as $permission => $defaultRoles) {
            $stored = $this->get('security_permission_' . str_replace('.', '_', $permission));
            if ($stored !== null) {
                $result[$permission] = $stored === ''
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $stored))));
            } else {
                $result[$permission] = $defaultRoles;
            }
        }

        return $this->permissionMatrixCache = $result;
    }

    public function mailSettings(): array
    {
        if ($this->mailSettingsCache !== null) {
            return $this->mailSettingsCache;
        }

        $settings = $this->mailSettingsDefaults();
        foreach (array_keys($settings) as $key) {
            $stored = $this->get($key);
            if ($stored !== null && trim($stored) !== '') {
                $settings[$key] = $stored;
            }
        }

        $settings['mail_smtp_port'] = (string) ((int) $settings['mail_smtp_port']);
        $settings['mail_imap_port'] = (string) ((int) $settings['mail_imap_port']);
        $settings['mail_imap_save_sent'] = $settings['mail_imap_save_sent'] === '0' ? '0' : '1';

        return $this->mailSettingsCache = $settings;
    }

    public function mailSettingsDefaults(): array
    {
        $identity = (array) config('mail.identities.0', []);
        $replyTo = (array) config('mail.reply_to_options.0', []);

        return [
            'mail_identity_key' => (string) ($identity['key'] ?? 'orga'),
            'mail_identity_name' => (string) ($identity['name'] ?? 'Mailer'),
            'mail_identity_email' => (string) ($identity['email'] ?? ''),
            'mail_smtp_host' => (string) ($identity['smtp_host'] ?? ''),
            'mail_smtp_port' => (string) ($identity['smtp_port'] ?? 587),
            'mail_smtp_encryption' => (string) ($identity['smtp_encryption'] ?? 'tls'),
            'mail_smtp_username' => (string) ($identity['smtp_username'] ?? ''),
            'mail_smtp_password' => (string) ($identity['smtp_password'] ?? ''),
            'mail_imap_save_sent' => !isset($identity['imap_save_sent']) || (bool) $identity['imap_save_sent'] ? '1' : '0',
            'mail_imap_host' => (string) ($identity['imap_host'] ?? ($identity['smtp_host'] ?? '')),
            'mail_imap_port' => (string) ($identity['imap_port'] ?? 993),
            'mail_imap_encryption' => (string) ($identity['imap_encryption'] ?? 'ssl'),
            'mail_imap_username' => (string) ($identity['imap_username'] ?? ($identity['smtp_username'] ?? '')),
            'mail_imap_password' => (string) ($identity['imap_password'] ?? ($identity['smtp_password'] ?? '')),
            'mail_imap_sent_mailboxes' => implode("\n", (array) ($identity['imap_sent_mailboxes'] ?? ['INBOX.Sent', 'Sent', 'INBOX.Gesendet', 'Gesendet'])),
            'mail_reply_to_key' => (string) ($replyTo['key'] ?? 'orga_reply'),
            'mail_reply_to_name' => (string) ($replyTo['name'] ?? ($identity['name'] ?? 'Reply-To')),
            'mail_reply_to_email' => (string) ($replyTo['email'] ?? ''),
            'mail_bcc_email' => (string) ($identity['bcc_email'] ?? ''),
        ];
    }

    public function mailIdentity(): array
    {
        $settings = $this->mailSettings();

        return [
            'key' => $settings['mail_identity_key'],
            'name' => $settings['mail_identity_name'],
            'email' => $settings['mail_identity_email'],
            'smtp_host' => $settings['mail_smtp_host'],
            'smtp_port' => (int) $settings['mail_smtp_port'],
            'smtp_encryption' => $settings['mail_smtp_encryption'],
            'smtp_username' => $settings['mail_smtp_username'],
            'smtp_password' => $settings['mail_smtp_password'],
            'imap_save_sent' => $settings['mail_imap_save_sent'] !== '0',
            'imap_host' => $settings['mail_imap_host'],
            'imap_port' => (int) $settings['mail_imap_port'],
            'imap_encryption' => $settings['mail_imap_encryption'],
            'imap_username' => $settings['mail_imap_username'],
            'imap_password' => $settings['mail_imap_password'],
            'imap_sent_mailboxes' => $this->mailSentMailboxes(),
            'bcc_email' => trim((string) $settings['mail_bcc_email']),
        ];
    }

    public function mailIdentities(): array
    {
        return [$this->mailIdentity()];
    }

    public function mailReplyToOptions(): array
    {
        $settings = $this->mailSettings();

        return [[
            'key' => $settings['mail_reply_to_key'],
            'name' => $settings['mail_reply_to_name'],
            'email' => $settings['mail_reply_to_email'],
        ]];
    }

    public function defaultMailSenderKey(): string
    {
        return (string) $this->mailSettings()['mail_identity_key'];
    }

    public function defaultMailReplyToKey(): string
    {
        return (string) $this->mailSettings()['mail_reply_to_key'];
    }

    public function mailSentMailboxes(): array
    {
        $settings = $this->mailSettings();
        $candidates = preg_split('/\R+/', (string) $settings['mail_imap_sent_mailboxes']) ?: [];
        $mailboxes = [];

        foreach ($candidates as $candidate) {
            $mailbox = trim((string) $candidate);
            if ($mailbox === '') {
                continue;
            }

            $mailboxes[] = $mailbox;
        }

        return $mailboxes !== [] ? array_values(array_unique($mailboxes)) : ['INBOX.Sent', 'Sent'];
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
