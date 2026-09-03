<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;

/**
 * Gruppen-Mail (Stufe C): jedes Gruppenmitglied darf einmal formulieren und an
 * alle senden. Weiche Tagesgrenze je Person (Standard 2) – darüber geht die Mail
 * trotzdem raus, aber das Admin-Team wird informiert. „Notbremse" pro Gruppe
 * (`contact_groups.mail_locked`) stoppt den Versand für alle außer Admin.
 */
final class GroupMailService
{
    public function __construct(
        private GroupRepository $groups,
        private UserRepository $users,
        private SettingRepository $settings,
        private MailService $mailer,
        private LogRepository $logs,
    ) {
    }

    public function softLimit(): int
    {
        return max(1, (int) config('groups.mail_soft_limit', 2));
    }

    public function maxRecipients(): int
    {
        return max(10, (int) config('groups.mail_max_recipients', 250));
    }

    public function sentTodayBy(int $userId): int
    {
        return $this->groups->senderMailsToday($userId);
    }

    /**
     * @param array<string,mixed> $group  aus GroupRepository::find()
     * @param array<string,mixed> $sender aktueller User (mit name/email)
     * @return array{sent:int, failed:int, skipped:int, recipients:list<string>,
     *               failedNames:list<string>, noEmail:list<string>, softLimitHit:bool}
     */
    /**
     * @param string $replyToMode 'self' = Antworten nur an den Absender,
     *   'leads' = an den Absender und alle Personen mit Gruppenleitung
     */
    public function send(array $group, array $sender, string $subject, string $message, bool $senderIsAdmin, string $replyToMode = 'self'): array
    {
        $groupId = (int) $group['id'];
        $members = $this->groups->membersOf($groupId);

        $withEmail = [];
        $noEmail = [];
        foreach ($members as $member) {
            $name = trim($member['vorname'] . ' ' . $member['nachname']);
            $email = trim((string) ($member['email'] ?? ''));
            if ($email === '') {
                $noEmail[] = $name;
            } else {
                $withEmail[] = ['name' => $name, 'email' => $email];
            }
        }

        $todayCount = $senderIsAdmin ? 0 : $this->sentTodayBy((int) ($sender['id'] ?? 0));
        $softLimitHit = !$senderIsAdmin && $todayCount >= $this->softLimit();

        $identity = $this->settings->mailIdentity();
        $senderName = trim((string) ($sender['name'] ?? '')) ?: 'Ein Gruppenmitglied';
        $senderEmail = trim((string) ($sender['email'] ?? '')) ?: null;
        $fullSubject = $this->clip('[' . $group['name'] . '] ' . $subject, 190);

        // Reply-To: nur der Absender – oder der Absender plus die gesamte
        // Gruppenleitung.
        $replyTo = [];
        if ($senderEmail !== null) {
            $replyTo[] = $senderEmail;
        }
        if ($replyToMode === 'leads') {
            foreach ($this->groups->leadRecipients((int) $group['id']) as $lead) {
                if (!in_array($lead['email'], $replyTo, true)) {
                    $replyTo[] = $lead['email'];
                }
            }
        }
        $replyToHeader = implode(', ', $replyTo) ?: null;

        $footer = "\n\n—\nDiese Nachricht ging an alle in der Gruppe „" . $group['name'] . '".'
            . "\nAbgesendet von " . $senderName
            . ($senderEmail !== null ? ' <' . $senderEmail . '>' : '') . '.';
        $body = rtrim($message) . $footer;

        $senderUserId = (int) ($sender['id'] ?? 0);
        $sent = [];
        $failed = [];
        foreach ($withEmail as $recipient) {
            try {
                $this->mailer->sendSystemMail($identity, $recipient['email'], $fullSubject, $body, $replyToHeader);
                $sent[] = $recipient['name'];
                $status = 'gesendet';
                $error = null;
            } catch (\Throwable $exception) {
                $failed[] = $recipient['name'];
                $status = 'fehlgeschlagen';
                $error = $exception->getMessage();
            }
            $this->logs->addMailLog([
                'user_id' => $senderUserId,
                'contact_id' => null,
                'empfaenger_email' => $recipient['email'],
                'betreff' => $fullSubject,
                'status' => $status,
                'fehlermeldung' => $error,
            ]);
        }

        $this->groups->logGroupMail([
            'group_id' => $groupId,
            'sender_user_id' => (int) ($sender['id'] ?? 0),
            'sender_name' => $senderName,
            'subject' => $subject,
            'recipient_count' => count($sent),
            'error_count' => count($failed),
            'soft_limit_hit' => $softLimitHit,
        ]);

        $this->confirmToSender($identity, $sender, $group, $subject, $sent, $failed, $noEmail, $replyToMode);

        if ($failed !== [] || $softLimitHit) {
            $this->notifyAdmins($identity, $senderName, $group, count($sent), $failed, $softLimitHit, $todayCount + 1);
        }

        return [
            'sent' => count($sent),
            'failed' => count($failed),
            'skipped' => count($noEmail),
            'recipients' => $sent,
            'failedNames' => $failed,
            'noEmail' => $noEmail,
            'softLimitHit' => $softLimitHit,
        ];
    }

    /**
     * @param list<string> $sent
     * @param list<string> $failed
     * @param list<string> $noEmail
     */
    private function confirmToSender(
        array $identity,
        array $sender,
        array $group,
        string $subject,
        array $sent,
        array $failed,
        array $noEmail,
        string $replyToMode = 'self'
    ): void {
        $to = trim((string) ($sender['email'] ?? ''));
        if ($to === '') {
            return;
        }

        $lines = [
            'Deine Nachricht „' . $subject . '" an die Gruppe „' . $group['name'] . '" ist raus.',
            'Antworten gehen an ' . ($replyToMode === 'leads' ? 'dich und die Gruppenleitung' : 'dich') . '.',
            '',
            'Zugestellt an ' . count($sent) . ' ' . (count($sent) === 1 ? 'Person' : 'Personen') . ':',
            $sent === [] ? '– niemand' : $this->nameList($sent),
        ];
        if ($failed !== []) {
            $lines[] = '';
            $lines[] = 'Nicht zugestellt (' . count($failed) . '): ' . $this->nameList($failed);
            $lines[] = 'Bitte wende dich ans Admin-Team – es wurde ebenfalls informiert.';
        }
        if ($noEmail !== []) {
            $lines[] = '';
            $lines[] = count($noEmail) . ' Mitglieder haben keine Mailadresse und wurden nicht angeschrieben: ' . $this->nameList($noEmail);
        }

        try {
            $this->mailer->sendSystemMail(
                $identity,
                $to,
                $this->clip('[Bestätigung] ' . $subject, 190),
                implode("\n", $lines)
            );
        } catch (\Throwable) {
            // Bestätigung ist Komfort – Fehler nicht weiterreichen.
        }
    }

    /** @param list<string> $failed */
    private function notifyAdmins(
        array $identity,
        string $senderName,
        array $group,
        int $sentCount,
        array $failed,
        bool $softLimitHit,
        int $todayNumber
    ): void {
        $admins = $this->users->activeByRoleNames(['admin']);
        if ($admins === []) {
            return;
        }

        $lines = [
            $senderName . ' hat eine Nachricht an die Gruppe „' . $group['name'] . '" geschickt ('
                . $sentCount . ' ' . ($sentCount === 1 ? 'Empfänger' : 'Empfänger') . ').',
        ];
        if ($softLimitHit) {
            $lines[] = '';
            $lines[] = 'Das ist heute bereits die ' . $todayNumber . '. Gruppen-Mail dieser Person – '
                . 'die weiche Grenze von ' . $this->softLimit() . ' ist überschritten.';
        }
        if ($failed !== []) {
            $lines[] = '';
            $lines[] = 'Bei ' . count($failed) . ' Empfängern schlug der Versand fehl: ' . $this->nameList($failed);
        }
        $lines[] = '';
        $lines[] = 'Bei Missbrauch lässt sich der Gruppen-Versand unter „Verwaltung → Gruppen → '
            . $group['name'] . '" sperren.';

        $subject = $this->clip('[Hinweis] Gruppen-Mail: ' . $group['name'], 190);
        foreach ($admins as $admin) {
            $to = trim((string) ($admin['email'] ?? ''));
            if ($to === '') {
                continue;
            }
            try {
                $this->mailer->sendSystemMail($identity, $to, $subject, implode("\n", $lines));
            } catch (\Throwable) {
                // best effort
            }
        }
    }

    /**
     * Der Gruppenleitung (bzw. ersatzweise Orga/Admin) eine Beitrittsanfrage
     * melden.
     */
    public function notifyJoinRequest(array $group, string $requesterName, string $message): void
    {
        $recipients = $this->groups->leadRecipients((int) $group['id']);
        if ($recipients === []) {
            $orgaRoles = array_values(array_unique(array_merge(
                ['admin'],
                $this->settings->permissionMatrix()['orga.contact_target'] ?? []
            )));
            foreach ($this->users->activeByRoleNames($orgaRoles) as $user) {
                $email = trim((string) ($user['email'] ?? ''));
                if ($email !== '') {
                    $recipients[] = ['name' => trim((string) ($user['name'] ?? '')), 'email' => $email];
                }
            }
        }
        if ($recipients === []) {
            return;
        }

        $identity = $this->settings->mailIdentity();
        $short = trim((string) branding_value('branding_short_name', ''));
        $subject = $this->clip('[' . ($short !== '' ? $short : 'Gruppen') . '] Beitrittsanfrage: ' . $group['name'], 190);
        $body = $requesterName . ' möchte der Gruppe „' . $group['name'] . '" beitreten.'
            . ($message !== '' ? "\n\nNachricht:\n" . $message : '')
            . "\n\nAnnehmen oder ablehnen unter „Verwaltung → Gruppen → " . $group['name'] . '".';

        foreach ($recipients as $person) {
            try {
                $this->mailer->sendSystemMail($identity, $person['email'], $subject, $body);
            } catch (\Throwable) {
                // best effort
            }
        }
    }

    /** @param list<string> $names */
    private function nameList(array $names): string
    {
        return implode(', ', $names);
    }

    private function clip(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1) . '…' : $value;
    }
}
