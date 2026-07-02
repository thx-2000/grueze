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

    public function sendMergedMail(array $identity, array $replyTo, array $contact, string $subject, string $message, array $attachments, int $userId): array
    {
        $to = $contact['emails'][0]['email'] ?? null;
        if (!$to) {
            return ['ok' => false, 'error' => 'Kein Empfänger vorhanden.'];
        }

        $personalized = str_replace(
            ['{Vorname}', '{Nachname}'],
            [$contact['vorname'], $contact['nachname']],
            $message
        );

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
    }
}
