<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContactRepository;
use App\Repositories\GreetingRepository;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

/**
 * Automatischer Geburtstagsversand: einmal am Tag (ab der eingestellten Uhrzeit)
 * bekommt jede Person mit hinterlegtem Geburtstag und Mailadresse einen zufällig
 * aus dem Grüße-Pool gezogenen Geburtstagsgruß. Wird vom Cron-Endpunkt und der
 * gedrosselten Rückfallebene mitgelaufen.
 *
 * Einstellungen (app_settings):
 *  - greetings_birthday_auto        '1' = an (Standard aus)
 *  - greetings_birthday_auto_time   'HH:MM' (Standard '08:00')
 *  - greetings_birthday_auto_subject Betreff, {Vorname} wird ersetzt
 *  - greetings_birthday_auto_last_run  intern: 'Y-m-d' des letzten Laufs
 */
final class GreetingScheduler
{
    public function __construct(
        private ContactRepository $contacts,
        private GreetingRepository $greetings,
        private SettingRepository $settings,
        private UserRepository $users,
        private MailService $mailer,
        private LogRepository $logs,
    ) {
    }

    /** @return array{sent:int, failed:int, skipped:int} */
    public function run(): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        if ($this->settings->get('greetings_birthday_auto') !== '1') {
            return $stats;
        }

        $now = new \DateTimeImmutable('now');
        $today = $now->format('Y-m-d');

        if ($this->settings->get('greetings_birthday_auto_last_run') === $today) {
            return $stats;
        }

        $time = $this->normalizeTime((string) $this->settings->get('greetings_birthday_auto_time', '08:00'));
        if ($now->format('H:i') < $time) {
            return $stats; // heute noch zu früh
        }

        // Vor dem Versenden markieren – ein Abbruch mitten drin darf morgen nicht
        // alles noch einmal schicken.
        $this->settings->set('greetings_birthday_auto_last_run', $today);

        $people = $this->contacts->birthdaysToday();
        if ($people === []) {
            return $stats;
        }

        $texts = $this->greetings->assign(array_map(static fn (array $p): int => $p['id'], $people), 'birthday');
        if ($texts === []) {
            $stats['skipped'] = count($people); // kein aktiver Text im Pool
            return $stats;
        }

        $identity = $this->settings->mailIdentity();
        $replyTo = $this->replyToAddress();
        $footer = trim(apply_branding_placeholders($this->settings->mailFooter()));
        $subjectTpl = trim((string) $this->settings->get('greetings_birthday_auto_subject', ''))
            ?: 'Alles Gute zum Geburtstag!';
        $actingUserId = $this->systemUserId();

        foreach ($people as $person) {
            $text = (string) ($texts[$person['id']] ?? '');
            if ($text === '') {
                $stats['skipped']++;
                continue;
            }

            $contact = $this->contacts->find($person['id']);
            $body = $contact !== null
                ? $this->mailer->renderMessageTemplate($contact, $text, 'auto')
                : str_replace('{Vorname}', $person['vorname'], $text);
            if ($footer !== '') {
                $body .= "\n\n" . $footer;
            }
            $subject = str_replace('{Vorname}', $person['vorname'], $subjectTpl);

            try {
                $this->mailer->sendSystemMail($identity, $person['email'], $subject, $body, $replyTo);
                $stats['sent']++;
                $status = 'gesendet';
                $error = null;
            } catch (\Throwable $exception) {
                $stats['failed']++;
                $status = 'fehlgeschlagen';
                $error = $exception->getMessage();
            }

            if ($actingUserId > 0) {
                $this->logs->addMailLog([
                    'user_id' => $actingUserId,
                    'contact_id' => $person['id'],
                    'empfaenger_email' => $person['email'],
                    'betreff' => $subject,
                    'status' => $status,
                    'fehlermeldung' => $error,
                ]);
            }
        }

        return $stats;
    }

    private function normalizeTime(string $value): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            $h = min(23, max(0, (int) $m[1]));
            $min = min(59, max(0, (int) $m[2]));

            return sprintf('%02d:%02d', $h, $min);
        }

        return '08:00';
    }

    private function replyToAddress(): ?string
    {
        $options = $this->settings->mailReplyToOptions();
        $email = trim((string) ($options[0]['email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    private function systemUserId(): int
    {
        foreach ($this->users->activeByRoleNames(['admin']) as $admin) {
            return (int) $admin['id'];
        }

        return 0;
    }
}
