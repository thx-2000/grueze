<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\SettingRepository;

/**
 * Verschickt fällige Sammelmails für Admin-Benachrichtigungen (Login /
 * Änderung). Aufruf über `/intern/cron` neben den bestehenden Schedulern –
 * „sofort" bedeutet dadurch „beim nächsten Cron-Lauf", nicht in Echtzeit.
 */
final class NotificationDigestScheduler
{
    public function __construct(
        private NotificationRepository $notifications,
        private SettingRepository $settings,
        private MailService $mailer,
    ) {
    }

    /** @return array{sent: int, errors: int} */
    public function run(): array
    {
        $stats = ['sent' => 0, 'errors' => 0];
        $identity = $this->settings->mailIdentity();

        foreach ($this->notifications->dueSubscriptions() as $subscription) {
            $queue = $this->notifications->queueFor((int) $subscription['id']);
            if ($queue === []) {
                continue;
            }

            try {
                $this->mailer->sendSystemMail(
                    $identity,
                    (string) $subscription['user_email'],
                    $this->subjectFor($subscription, count($queue)),
                    $this->bodyFor($subscription, $queue)
                );
                $stats['sent']++;
            } catch (\Throwable) {
                $stats['errors']++;
                continue; // In der Warteschlange lassen, beim nächsten Lauf erneut versuchen.
            }

            $this->notifications->markSent((int) $subscription['id']);
        }

        return $stats;
    }

    private function subjectFor(array $subscription, int $count): string
    {
        $kind = $subscription['event_type'] === 'login' ? 'Login' : 'Änderung';

        return $count === 1
            ? 'Benachrichtigung: ' . $kind
            : $count . ' Benachrichtigungen: ' . $kind;
    }

    /** @param list<array<string, mixed>> $queue */
    private function bodyFor(array $subscription, array $queue): string
    {
        $lines = [];
        foreach ($queue as $entry) {
            $lines[] = '– ' . format_datetime((string) $entry['occurred_at']) . ': ' . $entry['summary'];
        }

        return implode("\n", $lines)
            . "\n\n---\nDiese Mail folgt aus deinem Benachrichtigungs-Abo (\""
            . ($subscription['event_type'] === 'login' ? 'Bei Login' : 'Bei Änderung')
            . '", Rhythmus „' . (NotificationRepository::frequencyLabels()[$subscription['frequency']] ?? $subscription['frequency'])
            . '"). Anpassen unter „Meine Benachrichtigungen" in der Verwaltung.';
    }
}
