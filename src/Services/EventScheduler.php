<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\EventRepository;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

/**
 * Zeitgesteuerte Aufgaben rund um Abstimmungen – ohne echten Cron lauffähig:
 *  1. abgelaufene Abstimmungen schließen (Status 'closed'),
 *  2. 48 h vor Fristende einmalig an alle erinnern, die noch nicht abgestimmt
 *     haben,
 *  3. nach dem Schließen (bzw. Festlegen) das Ergebnis an den gewählten
 *     Verteiler mailen.
 *
 * Aufruf über `/intern/cron?key=…` (empfohlen) oder – als Rückfallebene für
 * selten besuchte Instanzen – gedrosselt aus `public/index.php`.
 */
final class EventScheduler
{
    public function __construct(
        private EventRepository $events,
        private UserRepository $users,
        private SettingRepository $settings,
        private MailService $mailer,
        private LogRepository $logs,
    ) {
    }

    /**
     * Alle drei Aufgaben abarbeiten.
     *
     * @return array{closed: int, reminded: int, results: int, upcoming: int, mails: int, errors: int}
     */
    public function run(): array
    {
        $stats = ['closed' => 0, 'reminded' => 0, 'results' => 0, 'upcoming' => 0, 'mails' => 0, 'errors' => 0];

        foreach ($this->events->idsDueForClose() as $eventId) {
            $this->events->setStatus($eventId, 'closed');
            $stats['closed']++;
        }

        foreach ($this->events->idsDueForReminder() as $eventId) {
            $sent = $this->sendReminder($eventId);
            $stats['mails'] += $sent['sent'];
            $stats['errors'] += $sent['failed'];
            $this->events->markReminderSent($eventId);
            $stats['reminded']++;
        }

        foreach ($this->events->idsDueForEventReminder() as $eventId) {
            $sent = $this->sendEventReminder($eventId);
            $stats['mails'] += $sent['sent'];
            $stats['errors'] += $sent['failed'];
            $this->events->markEventReminderSent($eventId);
            $stats['upcoming']++;
        }

        foreach ($this->events->idsDueForResultMail() as $eventId) {
            $sent = $this->sendResult($eventId);
            $stats['mails'] += $sent['sent'];
            $stats['errors'] += $sent['failed'];
            $this->events->markResultMailSent($eventId);
            $stats['results']++;
        }

        $this->settings->set('scheduler_last_run', (string) time());

        return $stats;
    }

    /** @return array{sent: int, failed: int} */
    private function sendReminder(int $eventId): array
    {
        $event = $this->events->find($eventId);
        if ($event === null) {
            return ['sent' => 0, 'failed' => 0];
        }

        $recipients = $this->events->nonVoterRecipients($eventId);
        if ($recipients === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $identity = $this->settings->mailIdentity();
        $subject = $this->prefix() . ' Erinnerung: ' . $event['title'];
        $deadline = $this->formatDeadline($event['closes_at'] ?? null);
        $base = url('/abstimmen');

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $person) {
            $body = 'Hallo ' . $this->firstName($person['name']) . ",\n\n"
                . 'für „' . $event['title'] . '" fehlt noch deine Rückmeldung. '
                . ($deadline !== '' ? 'Die Abstimmung endet am ' . $deadline . ".\n\n" : "\n\n")
                . 'Hier ist dein persönlicher Link:' . "\n" . $base . '?token=' . $person['token'] . "\n\n"
                . 'Danke!';
            $failed += $this->deliver($identity, $person['email'], $subject, $body, (int) $event['created_by']) ? 0 : 1;
            $sent += 1;
        }

        return ['sent' => $sent - $failed, 'failed' => $failed];
    }

    /** Vorab-Erinnerung X Tage vor dem festgelegten Termin an alle Zusagen. */
    private function sendEventReminder(int $eventId): array
    {
        $event = $this->events->find($eventId);
        if ($event === null) {
            return ['sent' => 0, 'failed' => 0];
        }

        $recipients = $this->events->decidedYesRecipients($eventId);
        if ($recipients === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $when = '';
        foreach ((array) ($event['options'] ?? []) as $o) {
            if ((int) $o['id'] === (int) ($event['decided_option_id'] ?? 0)) {
                $when = event_option_label($o);
            }
        }

        $identity = $this->settings->mailIdentity();
        $subject = $this->prefix() . ' Erinnerung: ' . $event['title'];
        $ics = trim((string) ($event['ical_uid'] ?? '')) !== ''
            ? url('/termine/termin.ics') . '?k=' . $event['ical_uid']
            : '';

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $person) {
            $body = 'Hallo ' . $this->firstName($person['name']) . ",\n\n"
                . 'kurze Erinnerung: „' . $event['title'] . '"'
                . ($when !== '' ? ' ist am ' . $when : ' steht bald an') . '.'
                . (trim((string) ($event['location'] ?? '')) !== '' ? "\nOrt: " . $event['location'] : '')
                . ($ics !== '' ? "\n\nIn den Kalender: " . $ics : '')
                . "\n\nBis dann!";
            $failed += $this->deliver($identity, $person['email'], $subject, $body, (int) $event['created_by']) ? 0 : 1;
            $sent += 1;
        }

        return ['sent' => $sent - $failed, 'failed' => $failed];
    }

    /** @return array{sent: int, failed: int} */
    private function sendResult(int $eventId): array
    {
        $event = $this->events->find($eventId);
        if ($event === null) {
            return ['sent' => 0, 'failed' => 0];
        }

        $mode = (string) ($event['result_recipients'] ?? '');
        $recipients = $this->resultRecipients($eventId, $mode);
        if ($recipients === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $identity = $this->settings->mailIdentity();
        $subject = $this->prefix() . ' Ergebnis: ' . $event['title'];
        $summary = $this->resultSummary($event);
        $forInfo = in_array($mode, ['orga', 'admin'], true)
            ? "\n\n(Diese Info geht an das " . ($mode === 'admin' ? 'Admin-Team' : 'Orga-Team') . ", nicht an die Teilnehmenden.)"
            : '';

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $person) {
            $body = 'Hallo ' . $this->firstName($person['name']) . ",\n\n"
                . 'die Abstimmung „' . $event['title'] . '" ist abgeschlossen.' . "\n\n"
                . $summary . $forInfo;
            $failed += $this->deliver($identity, $person['email'], $subject, $body, (int) $event['created_by']) ? 0 : 1;
            $sent += 1;
        }

        return ['sent' => $sent - $failed, 'failed' => $failed];
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    private function resultRecipients(int $eventId, string $mode): array
    {
        return match ($mode) {
            'voted' => $this->events->resultContactRecipients($eventId, true),
            'invited' => $this->events->resultContactRecipients($eventId, false),
            'orga' => $this->userRecipients($this->orgaTeamRoleNames()),
            'admin' => $this->userRecipients(['admin']),
            default => [],
        };
    }

    /**
     * Wer zählt zum „Orga-Team"? Die Rollen aus dem Recht `orga.contact_target`
     * plus immer Admin – so bleibt es nach einem Umbenennen des Rollen-
     * Schlüssels korrekt (der Wert wird beim Rename mitgezogen).
     *
     * @return list<string>
     */
    private function orgaTeamRoleNames(): array
    {
        $roles = $this->settings->permissionMatrix()['orga.contact_target'] ?? [];

        return array_values(array_unique(array_merge(['admin'], $roles)));
    }

    /**
     * @param list<string> $roles
     * @return list<array{name: string, email: string}>
     */
    private function userRecipients(array $roles): array
    {
        $out = [];
        foreach ($this->users->activeByRoleNames($roles) as $user) {
            $email = trim((string) ($user['email'] ?? ''));
            if ($email !== '') {
                $out[] = ['name' => trim((string) ($user['name'] ?? '')), 'email' => $email];
            }
        }

        return $out;
    }

    private function resultSummary(array $event): string
    {
        $options = $event['options'] ?? [];
        $tally = $event['tally'] ?? [];
        $decidedId = (int) ($event['decided_option_id'] ?? 0);

        if ($decidedId > 0) {
            foreach ($options as $option) {
                if ((int) $option['id'] === $decidedId) {
                    $line = 'Festgelegt: ' . event_option_label($option);
                    if (trim((string) ($event['ical_uid'] ?? '')) !== '') {
                        $line .= "\n\nIn den Kalender: " . url('/termine/termin.ics') . '?k=' . $event['ical_uid'];
                    }

                    return $line;
                }
            }
        }

        $lines = [];
        foreach ($options as $option) {
            $counts = $tally[(int) $option['id']] ?? ['yes' => 0, 'maybe' => 0, 'no' => 0];
            $lines[] = '– ' . event_option_label($option) . ': '
                . (int) $counts['yes'] . '× Ja, '
                . (int) $counts['maybe'] . '× Vielleicht, '
                . (int) $counts['no'] . '× Nein';
        }

        return $lines === [] ? 'Es wurden keine Optionen erfasst.' : "Zwischenstand:\n" . implode("\n", $lines);
    }

    private function deliver(array $identity, string $to, string $subject, string $body, int $actingUserId): bool
    {
        try {
            $this->mailer->sendSystemMail($identity, $to, $subject, $body);
            $status = 'gesendet';
            $error = null;
            $ok = true;
        } catch (\Throwable $exception) {
            $status = 'fehlgeschlagen';
            $error = $exception->getMessage();
            $ok = false;
        }

        $this->logs->addMailLog([
            'user_id' => $actingUserId,
            'contact_id' => null,
            'empfaenger_email' => $to,
            'betreff' => $subject,
            'status' => $status,
            'fehlermeldung' => $error,
        ]);

        return $ok;
    }

    private function prefix(): string
    {
        $short = trim((string) branding_value('branding_short_name', ''));

        return '[' . ($short !== '' ? $short : 'Termine') . ']';
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? $name;
    }

    private function formatDeadline(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }
        try {
            return (new \DateTimeImmutable($value))->format('d.m.Y') . ' um '
                . (new \DateTimeImmutable($value))->format('H:i') . ' Uhr';
        } catch (\Throwable) {
            return '';
        }
    }
}
