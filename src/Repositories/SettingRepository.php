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
        // Standardwerte. Jeder Eintrag kann per config('branding.<key ohne
        // branding_-Präfix>') vorbelegt werden, ohne die laufende Instanz zu
        // verändern (deren config keine branding-Sektion enthält). Die
        // Admin-Oberfläche (app_settings) hat weiterhin Vorrang vor beidem.
        // Farben, Fonts und Eckenradien liegen seit v0.8.0 im Theme-System
        // (ThemeService / Ordner themes/), nicht mehr hier.
        $defaults = [
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
            : "Falls du keine weiteren Kontaktanfragen über dieses System erhalten möchtest, wende dich bitte ans Orga-Team.";

        return "Diese Nachricht wurde von einem {$shortName}-Stufenmitglied über die interne Kontaktfunktion versendet und stammt nicht vom Orga-Team.\nDu erhältst sie, weil deine Kontaktdaten in der {$appName} hinterlegt sind.\nAntworten auf diese Nachricht gehen direkt an die absendende Person.\n{$contactLine}";
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
        return match ($page) {
            'impressum' => <<<'HTML'
<h3>Orga-Team</h3>
<p>i. V. Vorname Nachname<br>Musterstraße 1<br>12345 Musterstadt</p>

<h3>Kontakt</h3>
<p>Telefon: 0000 0000000<br>E-Mail: <a href="mailto:kontakt@example.org">kontakt@example.org</a></p>
HTML,
            'datenschutz' => <<<'HTML'
<h3>1. Datenschutz auf einen Blick</h3>
<h4>Allgemeine Hinweise</h4>
<p>Die folgenden Hinweise geben einen einfachen &Uuml;berblick dar&uuml;ber, was mit Ihren personenbezogenen Daten passiert, wenn Sie diese Website besuchen. Personenbezogene Daten sind alle Daten, mit denen Sie pers&ouml;nlich identifiziert werden k&ouml;nnen. Ausf&uuml;hrliche Informationen zum Thema Datenschutz entnehmen Sie unserer unter diesem Text aufgef&uuml;hrten Datenschutzerkl&auml;rung.</p>
<h4>Datenerfassung auf dieser Website</h4>
<p><strong>Wer ist verantwortlich f&uuml;r die Datenerfassung auf dieser Website?</strong></p>
<p>Die Datenverarbeitung auf dieser Website erfolgt durch den Websitebetreiber. Dessen Kontaktdaten k&ouml;nnen Sie dem Abschnitt &bdquo;Hinweis zur verantwortlichen Stelle&ldquo; in dieser Datenschutzerkl&auml;rung entnehmen.</p>
<p><strong>Wie erfassen wir Ihre Daten?</strong></p>
<p>Ihre Daten werden zum einen dadurch erhoben, dass Sie uns diese mitteilen. Hierbei kann es sich zum Beispiel um Daten handeln, die Sie in ein Kontaktformular eingeben.</p>
<p>Andere Daten werden automatisch oder nach Ihrer Einwilligung beim Besuch der Website durch unsere IT-Systeme erfasst. Das sind vor allem technische Daten wie Internetbrowser, Betriebssystem oder Uhrzeit des Seitenaufrufs. Die Erfassung dieser Daten erfolgt automatisch, sobald Sie diese Website betreten.</p>
<p><strong>Wof&uuml;r nutzen wir Ihre Daten?</strong></p>
<p>Ein Teil der Daten wird erhoben, um eine fehlerfreie Bereitstellung der Website zu gew&auml;hrleisten. Andere Daten k&ouml;nnen zur Analyse Ihres Nutzerverhaltens verwendet werden. Sofern &uuml;ber die Website Vertr&auml;ge geschlossen oder angebahnt werden k&ouml;nnen, werden die &uuml;bermittelten Daten auch f&uuml;r Vertragsangebote, Bestellungen oder sonstige Auftragsanfragen verarbeitet.</p>
<p><strong>Welche Rechte haben Sie bez&uuml;glich Ihrer Daten?</strong></p>
<p>Sie haben jederzeit das Recht, unentgeltlich Auskunft &uuml;ber Herkunft, Empf&auml;nger und Zweck Ihrer gespeicherten personenbezogenen Daten zu erhalten. Sie haben au&szlig;erdem ein Recht, die Berichtigung oder L&ouml;schung dieser Daten zu verlangen. Wenn Sie eine Einwilligung zur Datenverarbeitung erteilt haben, k&ouml;nnen Sie diese Einwilligung jederzeit f&uuml;r die Zukunft widerrufen. Au&szlig;erdem haben Sie das Recht, unter bestimmten Umst&auml;nden die Einschr&auml;nkung der Verarbeitung Ihrer personenbezogenen Daten zu verlangen. Des Weiteren steht Ihnen ein Beschwerderecht bei der zust&auml;ndigen Aufsichtsbeh&ouml;rde zu.</p>
<p>Hierzu sowie zu weiteren Fragen zum Thema Datenschutz k&ouml;nnen Sie sich jederzeit an uns wenden.</p>

<h3>2. Hosting</h3>
<p>Wir hosten die Inhalte unserer Website bei folgendem Anbieter:</p>
<h4>All-Inkl</h4>
<p>Anbieter ist die ALL-INKL.COM - Neue Medien M&uuml;nnich, Inh. Ren&eacute; M&uuml;nnich, Hauptstra&szlig;e 68, 02742 Friedersdorf. Details entnehmen Sie der Datenschutzerkl&auml;rung von All-Inkl: <a href="https://all-inkl.com/datenschutzinformationen/" target="_blank" rel="noopener noreferrer">https://all-inkl.com/datenschutzinformationen/</a>.</p>
<p>Die Verwendung von All-Inkl erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Wir haben ein berechtigtes Interesse an einer m&ouml;glichst zuverl&auml;ssigen Darstellung unserer Website. Sofern eine entsprechende Einwilligung abgefragt wurde, erfolgt die Verarbeitung ausschlie&szlig;lich auf Grundlage von Art. 6 Abs. 1 lit. a DSGVO und &sect; 25 Abs. 1 TDDDG, soweit die Einwilligung die Speicherung von Cookies oder den Zugriff auf Informationen im Endger&auml;t des Nutzers umfasst. Die Einwilligung ist jederzeit widerrufbar.</p>
<p><strong>Auftragsverarbeitung</strong></p>
<p>Wir haben einen Vertrag &uuml;ber Auftragsverarbeitung zur Nutzung des oben genannten Dienstes geschlossen. Hierbei handelt es sich um einen datenschutzrechtlich vorgeschriebenen Vertrag, der gew&auml;hrleistet, dass dieser die personenbezogenen Daten unserer Websitebesucher nur nach unseren Weisungen und unter Einhaltung der DSGVO verarbeitet.</p>

<h3>3. Allgemeine Hinweise und Pflichtinformationen</h3>
<h4>Datenschutz</h4>
<p>Die Betreiber dieser Seiten nehmen den Schutz Ihrer pers&ouml;nlichen Daten sehr ernst. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend den gesetzlichen Datenschutzvorschriften sowie dieser Datenschutzerkl&auml;rung.</p>
<p>Wenn Sie diese Website benutzen, werden verschiedene personenbezogene Daten erhoben. Personenbezogene Daten sind Daten, mit denen Sie pers&ouml;nlich identifiziert werden k&ouml;nnen. Die vorliegende Datenschutzerkl&auml;rung erl&auml;utert, welche Daten wir erheben und wof&uuml;r wir sie nutzen. Sie erl&auml;utert auch, wie und zu welchem Zweck das geschieht.</p>
<p>Wir weisen darauf hin, dass die Daten&uuml;bertragung im Internet, zum Beispiel bei der Kommunikation per E-Mail, Sicherheitsl&uuml;cken aufweisen kann. Ein l&uuml;ckenloser Schutz der Daten vor dem Zugriff durch Dritte ist nicht m&ouml;glich.</p>
<h4>Hinweis zur verantwortlichen Stelle</h4>
<p>Die verantwortliche Stelle f&uuml;r die Datenverarbeitung auf dieser Website ist:</p>
<p>Vorname Nachname<br>Musterstraße 1<br>12345 Musterstadt</p>
<p>Telefon: 0000 0000000<br>E-Mail: <a href="mailto:kontakt@example.org">kontakt@example.org</a></p>
<p>Verantwortliche Stelle ist die nat&uuml;rliche oder juristische Person, die allein oder gemeinsam mit anderen &uuml;ber die Zwecke und Mittel der Verarbeitung von personenbezogenen Daten entscheidet.</p>
<h4>Speicherdauer</h4>
<p>Soweit innerhalb dieser Datenschutzerkl&auml;rung keine speziellere Speicherdauer genannt wurde, verbleiben Ihre personenbezogenen Daten bei uns, bis der Zweck f&uuml;r die Datenverarbeitung entf&auml;llt. Wenn Sie ein berechtigtes L&ouml;schersuchen geltend machen oder eine Einwilligung zur Datenverarbeitung widerrufen, werden Ihre Daten gel&ouml;scht, sofern wir keine anderen rechtlich zul&auml;ssigen Gr&uuml;nde f&uuml;r die Speicherung Ihrer personenbezogenen Daten haben. Im letztgenannten Fall erfolgt die L&ouml;schung nach Fortfall dieser Gr&uuml;nde.</p>
<h4>Empf&auml;nger von personenbezogenen Daten</h4>
<p>Im Rahmen unserer Gesch&auml;ftst&auml;tigkeit arbeiten wir mit verschiedenen externen Stellen zusammen. Dabei ist teilweise auch eine &Uuml;bermittlung von personenbezogenen Daten an diese externen Stellen erforderlich. Wir geben personenbezogene Daten nur dann an externe Stellen weiter, wenn dies im Rahmen einer Vertragserf&uuml;llung erforderlich ist, wenn wir gesetzlich hierzu verpflichtet sind, wenn wir ein berechtigtes Interesse an der Weitergabe haben oder wenn eine sonstige Rechtsgrundlage die Datenweitergabe erlaubt.</p>
<h4>Widerruf Ihrer Einwilligung zur Datenverarbeitung</h4>
<p>Viele Datenverarbeitungsvorg&auml;nge sind nur mit Ihrer ausdr&uuml;cklichen Einwilligung m&ouml;glich. Sie k&ouml;nnen eine bereits erteilte Einwilligung jederzeit widerrufen. Die Rechtm&auml;&szlig;igkeit der bis zum Widerruf erfolgten Datenverarbeitung bleibt vom Widerruf unber&uuml;hrt.</p>
<h4>Beschwerderecht bei der zust&auml;ndigen Aufsichtsbeh&ouml;rde</h4>
<p>Im Falle von Verst&ouml;&szlig;en gegen die DSGVO steht den Betroffenen ein Beschwerderecht bei einer Aufsichtsbeh&ouml;rde zu, insbesondere in dem Mitgliedstaat ihres gew&ouml;hnlichen Aufenthalts, ihres Arbeitsplatzes oder des Orts des mutma&szlig;lichen Versto&szlig;es.</p>
<h4>Recht auf Daten&uuml;bertragbarkeit</h4>
<p>Sie haben das Recht, Daten, die wir auf Grundlage Ihrer Einwilligung oder in Erf&uuml;llung eines Vertrags automatisiert verarbeiten, an sich oder an einen Dritten in einem g&auml;ngigen, maschinenlesbaren Format aush&auml;ndigen zu lassen.</p>
<h4>Auskunft, Berichtigung und L&ouml;schung</h4>
<p>Sie haben im Rahmen der geltenden gesetzlichen Bestimmungen jederzeit das Recht auf unentgeltliche Auskunft &uuml;ber Ihre gespeicherten personenbezogenen Daten, deren Herkunft und Empf&auml;nger und den Zweck der Datenverarbeitung sowie gegebenenfalls ein Recht auf Berichtigung oder L&ouml;schung dieser Daten.</p>
<h4>Recht auf Einschr&auml;nkung der Verarbeitung</h4>
<ul>
    <li>Wenn Sie die Richtigkeit Ihrer bei uns gespeicherten personenbezogenen Daten bestreiten, ben&ouml;tigen wir in der Regel Zeit, um dies zu &uuml;berpr&uuml;fen.</li>
    <li>Wenn die Verarbeitung Ihrer personenbezogenen Daten unrechtm&auml;&szlig;ig geschah oder geschieht, k&ouml;nnen Sie statt der L&ouml;schung die Einschr&auml;nkung der Datenverarbeitung verlangen.</li>
    <li>Wenn wir Ihre personenbezogenen Daten nicht mehr ben&ouml;tigen, Sie sie jedoch zur Aus&uuml;bung, Verteidigung oder Geltendmachung von Rechtsanspr&uuml;chen ben&ouml;tigen, haben Sie das Recht, statt der L&ouml;schung die Einschr&auml;nkung der Verarbeitung Ihrer personenbezogenen Daten zu verlangen.</li>
    <li>Wenn Sie einen Widerspruch nach Art. 21 Abs. 1 DSGVO eingelegt haben, muss eine Abw&auml;gung zwischen Ihren und unseren Interessen vorgenommen werden.</li>
</ul>
<h4>SSL- bzw. TLS-Verschl&uuml;sselung</h4>
<p>Diese Seite nutzt aus Sicherheitsgr&uuml;nden und zum Schutz der &Uuml;bertragung vertraulicher Inhalte eine SSL- beziehungsweise TLS-Verschl&uuml;sselung.</p>

<h3>4. Datenerfassung auf dieser Website</h3>
<h4>Server-Log-Dateien</h4>
<p>Der Provider der Seiten erhebt und speichert automatisch Informationen in so genannten Server-Log-Dateien, die Ihr Browser automatisch an uns &uuml;bermittelt. Dies sind:</p>
<ul>
    <li>Browsertyp und Browserversion</li>
    <li>verwendetes Betriebssystem</li>
    <li>Referrer-URL</li>
    <li>Hostname des zugreifenden Rechners</li>
    <li>Uhrzeit der Serveranfrage</li>
    <li>IP-Adresse</li>
</ul>
<p>Eine Zusammenf&uuml;hrung dieser Daten mit anderen Datenquellen wird nicht vorgenommen.</p>

<h3>5. Newsletter</h3>
<h4>Newsletterdaten</h4>
<p>Wenn Sie den auf der Website angebotenen Newsletter beziehen m&ouml;chten, ben&ouml;tigen wir von Ihnen eine E-Mail-Adresse sowie Informationen, welche uns die &Uuml;berpr&uuml;fung gestatten, dass Sie Inhaber der angegebenen E-Mail-Adresse sind und mit dem Empfang des Newsletters einverstanden sind. Weitere Daten werden nicht beziehungsweise nur auf freiwilliger Basis erhoben. Diese Daten verwenden wir ausschlie&szlig;lich f&uuml;r den Versand der angeforderten Informationen und geben diese nicht an Dritte weiter.</p>
<p>Die Verarbeitung der in das Newsletteranmeldeformular eingegebenen Daten erfolgt ausschlie&szlig;lich auf Grundlage Ihrer Einwilligung. Die erteilte Einwilligung zur Speicherung der Daten, der E-Mail-Adresse sowie deren Nutzung zum Versand des Newsletters k&ouml;nnen Sie jederzeit widerrufen. Die Rechtm&auml;&szlig;igkeit der bereits erfolgten Datenverarbeitungsvorg&auml;nge bleibt vom Widerruf unber&uuml;hrt.</p>
<p>Die von Ihnen zum Zwecke des Newsletter-Bezugs bei uns hinterlegten Daten werden von uns bis zu Ihrer Austragung aus dem Newsletter bei uns beziehungsweise dem Newsletterdiensteanbieter gespeichert und nach der Abbestellung des Newsletters oder nach Zweckfortfall aus der Verteilerliste gel&ouml;scht.</p>
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
