<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LogRepository;
use RuntimeException;

final class MailService
{
    public function __construct(private LogRepository $logs)
    {
    }

    public function sendSystemMail(array $identity, string $to, string $subject, string $body, ?string $replyTo = null): void
    {
        $this->sendRaw($identity, $to, $subject, $body, $replyTo ?: $identity['email'], []);
    }

    public function sendMergedMail(array $identity, array $replyTo, array $contact, string $subject, string $message, string $salutationMode, array $attachments, int $userId): array
    {
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

    private function sendRaw(array $identity, string $to, string $subject, string $body, string $replyTo, array $attachments): void
    {
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
            $mailer->addReplyTo($replyTo, $identity['name']);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->isHTML(false);

            foreach ($attachments as $attachment) {
                $mailer->addAttachment($attachment['path'], $attachment['name']);
            }

            $mailer->send();
            $this->archiveSentCopy($identity, $mailer->getSentMIMEMessage());
            return;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $identity['name'] . ' <' . $identity['email'] . '>',
            'Reply-To: ' . $replyTo,
        ];

        if ($attachments !== []) {
            throw new RuntimeException('Anhänge benötigen PHPMailer via Composer.');
        }

        $success = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
        if (!$success) {
            throw new RuntimeException('Mailversand fehlgeschlagen. Bitte SMTP prüfen.');
        }

        $this->archiveSentCopy($identity, $this->buildPlainTextMime($identity, $to, $replyTo, $subject, $body, $headers));
    }

    private function archiveSentCopy(array $identity, string $rawMessage): void
    {
        $config = $this->imapConfig($identity);
        if (!(bool) ($config['enabled'] ?? true)) {
            return;
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
            error_log('Mailer: Kopie konnte nicht im IMAP-Gesendet-Ordner abgelegt werden.');
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

    private function appendWithImapSocket($streamConfig, string $message): bool
    {
        $scheme = ($streamConfig['encryption'] ?? 'ssl') === 'ssl' ? 'ssl' : 'tcp';
        $address = sprintf('%s://%s:%d', $scheme, $streamConfig['host'], (int) $streamConfig['port']);
        $stream = @stream_socket_client($address, $errorCode, $errorMessage, 10);

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

        return sprintf('{%s:%d/imap/%s/novalidate-cert}%s', $config['host'], (int) $config['port'], $flag, $mailbox);
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

    private function normalizeMime(string $value): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $value));
    }

    private function resolveSalutation(array $contact, string $salutationMode): string
    {
        return match ($salutationMode) {
            'liebe' => 'Liebe',
            'lieber' => 'Lieber',
            'hallo' => 'Hallo',
            default => match (strtolower((string) ($contact['geschlecht'] ?? ''))) {
                'm' => 'Lieber',
                'w' => 'Liebe',
                default => 'Hallo',
            },
        };
    }
}
