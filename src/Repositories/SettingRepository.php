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

        return $this->brandingCache = $branding;
    }

    public function brandingDefaults(): array
    {
        // Neutrale Standardwerte für eine frische Installation. Auflösung:
        // Admin-Oberfläche (app_settings) > config('branding.<key ohne branding_>')
        // > diese Werte. Instanzspezifische Werte gehören in app_settings
        // (Branding-Seite bzw. Seed-Migration) oder in die config. Farben,
        // Schriften und Ecken stecken im Theme-System (Ordner themes/).
        $defaults = [
            'branding_app_name' => 'Adress-Zentrale',
            'branding_short_name' => 'Adress-Zentrale',
            'branding_version' => '0.2.0',
            'branding_public_site_label' => '',
            'branding_public_site_url' => '',
            'branding_login_headline' => 'Interner Bereich',
            'branding_login_intro' => 'Hier pflegt ihr Kontakte, Mailings und interne Organisationsdaten an einem Ort.',
            'branding_login_public_hint' => 'Infos zur Gruppe und die öffentliche Startseite findet ihr hier.',
            'branding_sidebar_copy' => 'Kontakte, Mailings und Organisation an einem Ort.',
            'branding_support_email' => '',
            'branding_logo_path' => '',
        ];

        foreach ($defaults as $key => $fallback) {
            if ($key === 'branding_logo_path' || $key === 'branding_version') {
                continue;
            }

            $defaults[$key] = branding_default(substr($key, strlen('branding_')), (string) $fallback);
        }

        return $defaults;
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

    /**
     * Sehen Nutzer:innen die Daten ihres eigenen verknüpften Kontakts immer
     * (Notizen ausgenommen)? Standard: ja.
     */
    public function ownContactAlwaysVisible(): bool
    {
        return $this->get('security_own_contact_visible', '1') !== '0';
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
            'events.manage'        => ['orga'],
            'orga.contact_target'  => ['orga'],
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

    /**
     * Entfernt einen Rollennamen aus allen gespeicherten Rechte- und
     * Sichtbarkeits-Listen – aufzurufen, nachdem eine Rolle gelöscht wurde.
     */
    public function pruneRole(string $roleName): void
    {
        foreach (array_keys($this->fieldVisibilityDefaults()) as $field) {
            $this->removeRoleFromSetting('security_visibility_' . $field, $roleName);
        }
        foreach (array_keys($this->permissionDefaults()) as $permission) {
            $this->removeRoleFromSetting('security_permission_' . str_replace('.', '_', $permission), $roleName);
        }

        $this->fieldVisibilityCache = null;
        $this->permissionMatrixCache = null;
    }

    private function removeRoleFromSetting(string $key, string $roleName): void
    {
        $stored = $this->get($key);
        if ($stored === null) {
            return; // Kein Override gespeichert – der Default greift, nichts zu tun.
        }

        $roles = array_values(array_filter(
            array_map('trim', explode(',', $stored)),
            static fn (string $role): bool => $role !== '' && $role !== $roleName
        ));

        $this->set($key, implode(',', $roles));
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
            'mail_orga_address' => '',
        ];
    }

    /**
     * Rollen, die Nachrichten über den „Orga-Team schreiben"-Knopf bekommen.
     * „admin" ist immer dabei.
     *
     * @return list<string>
     */
    public function orgaContactRoles(): array
    {
        $matrix = $this->permissionMatrix();
        $roles = $matrix['orga.contact_target'] ?? ($this->permissionDefaults()['orga.contact_target'] ?? []);
        if (!in_array('admin', $roles, true)) {
            $roles[] = 'admin';
        }

        return array_values(array_unique($roles));
    }

    /** Feste Orga-Mailadresse (Ausnahmefall) – leer = an die Rollen oben. */
    public function orgaContactAddress(): string
    {
        return trim((string) ($this->get('mail_orga_address') ?? ''));
    }

    /**
     * Selbst-Registrierung: ob die Selbst-Anmeldung (mit bekannter Adresse)
     * offen ist, welche Rolle neue Accounts bekommen und wie lange ein Link gilt.
     *
     * @return array{self_enabled: bool, default_role: string, link_hours: int}
     */
    public function registrationSettings(): array
    {
        return [
            'self_enabled' => (string) ($this->get('registration_self_enabled') ?? '0') === '1',
            'default_role' => trim((string) ($this->get('registration_default_role') ?? '')) ?: 'stufenmitglied',
            'link_hours' => max(1, (int) ($this->get('registration_link_hours') ?? 72)),
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

    public function memberContactFooter(): string
    {
        $stored = trim((string) $this->get('mail_member_contact_footer', ''));
        if ($stored !== '') {
            return $stored;
        }

        $b = $this->branding();
        $shortName = (string) ($b['branding_short_name'] ?? 'Adress-Zentrale');
        $appName = (string) ($b['branding_app_name'] ?? 'Adress-Zentrale');
        $supportEmail = trim((string) ($b['branding_support_email'] ?? ''));
        $contactLine = $supportEmail !== ''
            ? "Falls unsere Nachrichten fälschlich als Spam erkannt werden, nimm bitte {$supportEmail} in dein Adressbuch auf.\nWenn du keine weiteren Kontaktanfragen über dieses System erhalten möchtest, schreibe bitte an {$supportEmail}. Wir prüfen das dann mit dir."
            : "Wenn du keine weiteren Kontaktanfragen über dieses System erhalten möchtest, wende dich bitte an die Verwaltung von {$appName}.";

        return "Diese Nachricht wurde über die interne Kontaktfunktion von {$shortName} versendet und stammt von einer einzelnen Person, nicht von der Verwaltung.\nDu erhältst sie, weil deine Kontaktdaten in der {$appName} hinterlegt sind.\nAntworten auf diese Nachricht gehen direkt an die absendende Person.\n{$contactLine}";
    }

    public function memberContactSubjectPrefix(): string
    {
        $stored = trim((string) $this->get('mail_member_contact_prefix', ''));
        if ($stored !== '') {
            return $stored;
        }

        $b = $this->branding();
        $shortName = (string) ($b['branding_short_name'] ?? 'Kontakt');

        return '[' . $shortName . ' Kontakt]';
    }

    public function legalText(string $page): string
    {
        $stored = $this->get('legal_' . $page);
        if ($stored !== null && trim($stored) !== '') {
            return $stored;
        }

        return $this->defaultLegalText($page);
    }

    public function defaultLegalText(string $page): string
    {
        // Neutrales Geruest fuer frische Installationen. Die echten Texte gehoeren
        // in app_settings (Verwaltung -> Rechtliches). Fuer eine deutsche Website
        // sind vollstaendige Angaben Pflicht.
        return match ($page) {
            'impressum' => <<<'HTML'
<h3>Angaben gem&auml;&szlig; &sect; 5 DDG</h3>
<p>Bitte die verantwortliche Person bzw. Organisation, Anschrift und eine
Kontaktm&ouml;glichkeit eintragen (Verwaltung &rarr; Rechtliches).</p>

<h3>Kontakt</h3>
<p>Telefon: <br>E-Mail: </p>
HTML,
            'datenschutz' => <<<'HTML'
<h3>Datenschutzerkl&auml;rung</h3>
<p>Dieser Platzhaltertext muss vor dem produktiven Betrieb durch eine
vollst&auml;ndige Datenschutzerkl&auml;rung ersetzt werden (Verwaltung &rarr;
Rechtliches). Sie sollte mindestens die verantwortliche Stelle, Art und Zweck
der Datenverarbeitung, Rechtsgrundlagen, Speicherdauer, eingesetzte
Dienstleister sowie die Betroffenenrechte benennen.</p>
HTML,
            default => '',
        };
    }

    public function mailFooter(): string
    {
        $value = trim((string) $this->get('mail_footer', ''));

        return $value !== '' ? $value : $this->defaultMailFooter();
    }

    public function defaultMailFooter(): string
    {
        $support = trim((string) ($this->branding()['branding_support_email'] ?? ''));
        $contactLine = $support !== ''
            ? "Fragen oder Abmeldung: {$support}."
            : 'Antworten auf diese Nachricht gehen an das Team.';

        return (string) config('defaults.mail_footer', <<<TEXT
Du erhältst diese Nachricht, weil du auf dem Verteiler eingetragen bist.
Wir schreiben nur, wenn es wirklich etwas Relevantes gibt.
{$contactLine}
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
