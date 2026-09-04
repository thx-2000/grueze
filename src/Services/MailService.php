<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogRepository;
use RuntimeException;

/**
 * Zentraler Mailversand. Zwei Wege nach außen:
 *
 *  - **PHPMailer über SMTP**, sobald `phpmailer/phpmailer` per Composer da ist
 *    (empfohlen; nur so gehen Anhänge).
 *  - **`mail()`-Fallback** sonst – reiner Text, keine Anhänge.
 *
 * Nach jedem erfolgreichen Versand wird eine Kopie in den „Gesendet"-Ordner
 * des Postfachs gelegt (`archiveSentCopy`), damit versendete Mails auch im
 * Webmail auftauchen. Weil die PHP-`imap`-Erweiterung auf Shared Hosting oft
 * fehlt, gibt es dafür eine zweistufige Strategie: erst die Erweiterung,
 * sonst ein minimaler, handgeschriebener IMAP-Client über einen TLS-Socket.
 *
 * `$identity` ist das Array aus `SettingRepository::mailIdentity()`
 * (Absender, SMTP- und IMAP-Zugang, Sent-Ordner-Kandidaten).
 */
final class MailService
{
    public function __construct(private LogRepository $logs)
    {
    }

    /**
     * System-Mail (Reset-Link, Erinnerung, Ergebnis …): kein Eintrag im
     * `mail_log`, Fehler werden als Exception nach oben gereicht.
     */
    public function sendSystemMail(array $identity, string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $this->sendRaw($identity, $to, $subject, $body, $replyTo ?: $identity['email'], []);
    }

    /**
     * Eine personalisierte Mailing-Mail an genau einen Kontakt. Wird vom
     * Batch-Versand (`MailController`) je Empfänger aufgerufen; protokolliert
     * jeden Versuch im `mail_log` und gibt `['ok' => bool, 'error' => ?string]`
     * zurück, statt zu werfen – der Batch soll bei einem Fehler weiterlaufen.
     *
     * @return array{ok: bool, error?: string}
     */
    public function sendMergedMail(array $identity, array $replyTo, array $contact, string $subject, string $message, string $salutationMode, array $attachments, int $userId): array
    {
        // Erste hinterlegte Adresse ist die Zieladresse.
        $to = $contact['emails'][0]['email'] ?? null;
        if (!$to) {
            return ['ok' => false, 'error' => 'Kein Empfänger vorhanden.'];
        }

        $personalized = $this->renderMessageTemplate($contact, $message, $salutationMode);

        try {
            $this->sendRaw($identity, $to, $subject, $personalized, $replyTo['email'], $attachments);
            $this->logs->addMailLog([
                'user_id' => $userId,
                'contact_id' => $contact['id'],
                'empfaenger_email' => $to,
                'betreff' => $subject,
                'status' => 'gesendet',
                'fehlermeldung' => null,
            ]);

            return ['ok' => true];
        } catch (\Throwable $exception) {
            $this->logs->addMailLog([
                'user_id' => $userId,
                'contact_id' => $contact['id'],
                'empfaenger_email' => $to,
                'betreff' => $subject,
                'status' => 'fehlgeschlagen',
                'fehlermeldung' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Ersetzt die Platzhalter `{Anrede}` / `{Vorname}` / `{Nachname}` im
     * Nachrichtentext. `{Abstimmungslink}` wird nicht hier, sondern vorher im
     * `MailController` je Empfänger gesetzt (braucht den Token).
     */
    public function renderMessageTemplate(array $contact, string $message, string $salutationMode = 'auto'): string
    {
        return str_replace(
            ['{Anrede}', '{Vorname}', '{Nachname}'],
            [
                $this->resolveSalutation($contact, $salutationMode),
                $contact['vorname'] ?? '',
                $contact['nachname'] ?? '',
            ],
            $message
        );
    }

    /** Adressen dürfen keine Zeilenumbrüche enthalten (Header-Injection). */
    private function safeAddress(string $value): string
    {
        $clean = trim((string) preg_replace('/[\r\n\x00].*/s', '', $value));
        if ($clean === '' || preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException('Ungültige Mailadresse.');
        }

        return $clean;
    }

    /**
     * Reply-To kann eine kommagetrennte Liste sein („nur ich" vs. „die ganze
     * Gruppenleitung"). Zerlegen, säubern, doppelte entfernen.
     *
     * @return list<string>
     */
    private function replyToList(string $value, string $fallback): array
    {
        $out = [];
        foreach (preg_split('/[,;]+/', $value) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            try {
                $addr = $this->safeAddress($part);
            } catch (\Throwable) {
                continue;
            }
            if (!in_array($addr, $out, true)) {
                $out[] = $addr;
            }
        }
        if ($out === [] && $fallback !== '') {
            $out[] = $this->safeAddress($fallback);
        }

        return $out;
    }

    /**
     * Der eigentliche Versand. Zwei Pfade (PHPMailer/SMTP oder `mail()`), danach
     * jeweils die Sent-Ordner-Kopie. Alle Adress- und Namensbestandteile werden
     * vorher von Zeilenumbrüchen befreit (Header-Injection).
     */
    private function sendRaw(array $identity, string $to, string $subject, string $body, string $replyTo, array $attachments): void
    {
        $to = $this->safeAddress($to);
        $replyToAddrs = $this->replyToList($replyTo, (string) ($identity['email'] ?? ''));
        $identity['email'] = $this->safeAddress((string) ($identity['email'] ?? ''));
        $identity['name'] = trim((string) preg_replace('/[\r\n]+/', ' ', (string) ($identity['name'] ?? '')));

        // --- Pfad 1: PHPMailer über SMTP (bevorzugt, kann Anhänge) ---
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $identity['smtp_host'];
            $mailer->Port = (int) $identity['smtp_port'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $identity['smtp_username'];
            $mailer->Password = $identity['smtp_password'];
            $mailer->SMTPSecure = $identity['smtp_encryption'] ?: \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($identity['email'], $identity['name']);
            $mailer->addAddress($to);
            foreach ($replyToAddrs as $addr) {
                $mailer->addReplyTo($addr);
            }
            if (!empty($identity['bcc_email'])) {
                $mailer->addBCC((string) $identity['bcc_email']);
            }
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->isHTML(false);

            foreach ($attachments as $attachment) {
                $mailer->addAttachment($attachment['path'], $attachment['name']);
            }

            $mailer->send();
            // PHPMailer liefert die fertige MIME-Nachricht – die legen wir 1:1
            // in „Gesendet" ab.
            $this->archiveSentCopy($identity, $mailer->getSentMIMEMessage());
            return;
        }

        // --- Pfad 2: `mail()`-Fallback (nur Text, keine Anhänge) ---
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $identity['name'] . ' <' . $identity['email'] . '>',
            'Reply-To: ' . implode(', ', $replyToAddrs),
        ];
        if (!empty($identity['bcc_email'])) {
            $headers[] = 'Bcc: ' . $identity['bcc_email'];
        }

        if ($attachments !== []) {
            throw new RuntimeException('Anhänge benötigen PHPMailer via Composer.');
        }

        $success = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$success) {
            throw new RuntimeException('Mailversand fehlgeschlagen. Bitte SMTP prüfen.');
        }

        // `mail()` gibt uns die gesendete Nachricht nicht zurück – für die
        // Archiv-Kopie bauen wir sie aus denselben Teilen nach.
        $this->archiveSentCopy($identity, $this->buildPlainTextMime($identity, $to, implode(', ', $replyToAddrs), $subject, $body, $headers));
    }

    /**
     * Kopie der gesendeten Mail in den „Gesendet"-Ordner des Postfachs legen.
     * Reihenfolge: (1) PHP-`imap`-Erweiterung, falls vorhanden; (2) sonst ein
     * eigener IMAP-Client über einen TLS-Socket. Beide probieren die
     * konfigurierten Ordnernamen der Reihe nach durch (Provider benennen den
     * Sent-Ordner unterschiedlich). Scheitert alles, nur `error_log` – der
     * Versand selbst gilt trotzdem als erfolgreich.
     */
    private function archiveSentCopy(array $identity, string $rawMessage): void
    {
        $config = $this->imapConfig($identity);
        if (!(bool) ($config['enabled'] ?? true)) {
            return; // In den Mail-Einstellungen abgeschaltet.
        }

        $message = $this->normalizeMime($rawMessage);
        $saved = false;

        if (function_exists('imap_open') && function_exists('imap_append')) {
            $saved = $this->appendWithImapExtension($config, $message);
        }

        if (!$saved) {
            $saved = $this->appendWithImapSocket($config, $message);
        }

        if (!$saved) {
            error_log(system_label() . ' Mailer: Kopie konnte nicht im IMAP-Gesendet-Ordner abgelegt werden.');
        }
    }

    private function imapConfig(array $identity): array
    {
        return [
            'enabled' => $identity['imap_save_sent'] ?? true,
            'host' => $identity['imap_host'] ?? $identity['smtp_host'],
            'port' => (int) ($identity['imap_port'] ?? 993),
            'encryption' => strtolower((string) ($identity['imap_encryption'] ?? 'ssl')),
            'username' => $identity['imap_username'] ?? $identity['smtp_username'],
            'password' => $identity['imap_password'] ?? $identity['smtp_password'],
            'mailboxes' => $identity['imap_sent_mailboxes'] ?? ['INBOX.Sent', 'Sent', 'INBOX.Gesendet', 'Gesendet'],
        ];
    }

    private function appendWithImapExtension(array $config, string $message): bool
    {
        foreach ((array) $config['mailboxes'] as $mailbox) {
            $target = $this->imapMailboxString($config, (string) $mailbox);
            $imap = @imap_open($target, (string) $config['username'], (string) $config['password']);
            if ($imap === false) {
                continue;
            }

            $ok = @imap_append($imap, $target, $message, '\\Seen');
            @imap_close($imap);

            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minimaler IMAP-Client für genau einen Zweck: die gesendete Mail per
     * `APPEND` in den Sent-Ordner schreiben. Ablauf nach RFC 3501:
     * Server-Greeting lesen → bei `tls` STARTTLS + Crypto anschalten →
     * `LOGIN` → `APPEND` mit Literal-Syntax (`{Länge}` + `+`-Continuation,
     * dann die Nachricht) → `LOGOUT`. Der Socket prüft das Zertifikat
     * (`verify_peer` + SNI).
     */
    private function appendWithImapSocket($streamConfig, string $message): bool
    {
        // `ssl` = implizites TLS (Port 993); `tls` = STARTTLS auf Klartext-Port.
        $scheme = ($streamConfig['encryption'] ?? 'ssl') === 'ssl' ? 'ssl' : 'tcp';
        $address = sprintf('%s://%s:%d', $scheme, $streamConfig['host'], (int) $streamConfig['port']);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => (string) $streamConfig['host'],
            ],
        ]);
        $stream = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$stream) {
            return false;
        }

        stream_set_timeout($stream, 10);

        if (!$this->imapReadGreeting($stream)) {
            fclose($stream);
            return false;
        }

        if (($streamConfig['encryption'] ?? 'ssl') === 'tls') {
            if (!$this->imapCommand($stream, 'a1 STARTTLS')) {
                fclose($stream);
                return false;
            }

            if (!@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($stream);
                return false;
            }
        }

        if (!$this->imapCommand($stream, 'a2 LOGIN ' . $this->imapQuote((string) $streamConfig['username']) . ' ' . $this->imapQuote((string) $streamConfig['password']))) {
            fclose($stream);
            return false;
        }

        foreach ((array) $streamConfig['mailboxes'] as $mailbox) {
            if ($this->imapAppendCommand($stream, 'a3', (string) $mailbox, $message)) {
                $this->imapCommand($stream, 'a4 LOGOUT');
                fclose($stream);
                return true;
            }
        }

        $this->imapCommand($stream, 'a4 LOGOUT');
        fclose($stream);
        return false;
    }

    private function imapReadGreeting($stream): bool
    {
        $line = fgets($stream);

        return is_string($line) && str_starts_with($line, '* OK');
    }

    private function imapCommand($stream, string $command): bool
    {
        fwrite($stream, $command . "\r\n");
        $tag = strtok($command, ' ');

        while (($line = fgets($stream)) !== false) {
            if (str_starts_with($line, $tag . ' ')) {
                return str_contains($line, 'OK');
            }
        }

        return false;
    }

    /**
     * `APPEND <ordner> (\Seen) {<n>}` – der Server antwortet mit `+` (bereit
     * für ein Literal von n Bytes), dann schicken wir die Nachricht und lesen
     * die abschließende getaggte Antwort. `strlen` (Bytes, nicht Zeichen) ist
     * hier korrekt – das IMAP-Literal zählt Oktette.
     */
    private function imapAppendCommand($stream, string $tag, string $mailbox, string $message): bool
    {
        fwrite($stream, sprintf('%s APPEND %s (\\Seen) {%d}', $tag, $this->imapQuote($mailbox), strlen($message)) . "\r\n");

        while (($line = fgets($stream)) !== false) {
            if (str_starts_with($line, '+')) {
                fwrite($stream, $message . "\r\n");
                break;
            }

            if (str_starts_with($line, $tag . ' ')) {
                return false;
            }
        }

        while (($line = fgets($stream)) !== false) {
            if (str_starts_with($line, $tag . ' ')) {
                return str_contains($line, 'OK');
            }
        }

        return false;
    }

    private function imapMailboxString(array $config, string $mailbox): string
    {
        $flag = match ($config['encryption']) {
            'tls' => 'tls',
            'notls' => 'notls',
            default => 'ssl',
        };

        return sprintf('{%s:%d/imap/%s/validate-cert}%s', $config['host'], (int) $config['port'], $flag, $mailbox);
    }

    private function imapQuote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function buildPlainTextMime(array $identity, string $to, string $replyTo, string $subject, string $body, array $headers): string
    {
        $baseHeaders = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $identity['name'] . ' <' . $identity['email'] . '>',
            'To: ' . $to,
            'Reply-To: ' . $replyTo,
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        ];

        return implode("\r\n", array_merge($baseHeaders, $headers)) . "\r\n\r\n" . $this->normalizeMime($body);
    }

    /** Zeilenenden auf CRLF vereinheitlichen (RFC 5322 / IMAP APPEND). */
    private function normalizeMime(string $value): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $value));
    }

    /**
     * Wert für den `{Anrede}`-Platzhalter. `$salutationMode` kommt aus dem
     * Mailformular: `liebe` / `lieber` / `hallo` erzwingen die Anrede für alle;
     * `auto` (Standard) richtet sich nach dem Kontakt-Anrede-Feld
     * (`contacts.anrede`: `m` → „Lieber", `w` → „Liebe", leer → „Hallo").
     */
    private function resolveSalutation(array $contact, string $salutationMode): string
    {
        return match ($salutationMode) {
            'liebe' => 'Liebe',
            'lieber' => 'Lieber',
            'hallo' => 'Hallo',
            default => match (strtolower((string) ($contact['anrede'] ?? $contact['geschlecht'] ?? ''))) {
                'm' => 'Lieber',
                'w' => 'Liebe',
                default => 'Hallo',
            },
        };
    }
}
