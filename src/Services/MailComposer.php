<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\SettingRepository;

/**
 * „Wie ist die Mail adressiert und unterschrieben?" – Absender-Identität,
 * Antwortweg, Betreff-Präfix, Anrede-Modus und Mail-Fuß. Kapselt auch den
 * „eingeschränkte Kontaktaufnahme"-Modus (`mail.contact_single` ohne
 * `mail.send`/`contacts.manage`), in dem nur einzelne Personen mit dem
 * eigenen Postfach als Antwortadresse geschrieben werden dürfen.
 */
final class MailComposer
{
    public function __construct(
        private Auth $auth,
        private SettingRepository $settings,
    ) {
    }

    /**
     * Gemeinsame Ansichtsdaten für den Schreiben-Teil (Absender, Antwortweg,
     * Betreff-Präfixe, Mail-Fuß). memberContactMode ist hier immer falsch –
     * die Einzelkontakt-Aufnahme nutzt weiterhin `mail/compose`.
     */
    public function messageComposeData(): array
    {
        return [
            'identities' => $this->settings->mailIdentities(),
            'replyToOptions' => $this->replyToOptions(false),
            'mailFooter' => $this->mailFooter(false),
            'subjectPrefixOptions' => $this->settings->subjectPrefixOptions(),
            'defaultSubjectPrefix' => $this->defaultSubjectPrefix(false),
            'defaultSalutationMode' => 'auto',
            'defaultSenderKey' => $this->settings->defaultMailSenderKey(),
            'defaultReplyToKey' => $this->settings->defaultMailReplyToKey(),
        ];
    }

    public function identityByKey(string $key): ?array
    {
        foreach ($this->settings->mailIdentities() as $identity) {
            if (($identity['key'] ?? '') === $key) {
                return $identity;
            }
        }

        return $this->settings->mailIdentity();
    }

    public function replyToByKey(string $key, bool $memberContactMode = false, ?array $user = null): ?array
    {
        if ($memberContactMode) {
            return $this->memberReplyTo($user ?? $this->auth->user());
        }

        foreach ($this->replyToOptions() as $option) {
            if (($option['key'] ?? '') === $key) {
                return $option;
            }
        }

        return $this->replyToOptions()[0] ?? null;
    }

    public function replyToOptions(bool $memberContactMode = false): array
    {
        if ($memberContactMode) {
            $option = $this->memberReplyTo($this->auth->user());

            return $option ? [$option] : [];
        }

        $options = $this->settings->mailReplyToOptions();
        if ($options === []) {
            $options = $this->settings->mailIdentities();
        }

        // Zusätzlich: „Antworten kommen zu mir" – das eigene Login-Postfach.
        $self = $this->selfReplyTo($this->auth->user());
        if ($self !== null) {
            $options[] = $self;
        }

        return $options;
    }

    /** Reply-To auf das eigene Postfach der absendenden Person. */
    public function selfReplyTo(?array $user): ?array
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        return [
            'key' => 'self',
            'name' => 'Ich selbst (' . trim((string) ($user['name'] ?? 'mein Postfach')) . ')',
            'email' => $email,
        ];
    }

    public function memberReplyTo(?array $user): ?array
    {
        if (!$user || empty($user['email'])) {
            return null;
        }

        return [
            'key' => 'member_reply',
            'name' => (string) ($user['name'] ?? 'Interne Kontaktaufnahme'),
            'email' => (string) $user['email'],
        ];
    }

    public function composeMailBody(string $message, bool $memberContactMode = false): string
    {
        $message = trim($message);
        $footer = trim($this->mailFooter($memberContactMode));

        return $footer === '' ? $message : $message . "\n\n" . $footer;
    }

    public function composeSubject(string $subject, string $selectedPrefix, bool $memberContactMode = false): string
    {
        $subject = trim($subject);
        if ($memberContactMode) {
            $prefix = $this->defaultSubjectPrefix(true);
        } else {
            $options = $this->settings->subjectPrefixOptions();
            $prefix = in_array($selectedPrefix, $options, true)
                ? $selectedPrefix
                : $this->settings->defaultSubjectPrefix();
        }

        $normalizedPrefix = trim(apply_branding_placeholders($prefix));

        return $normalizedPrefix === '' ? $subject : $normalizedPrefix . ' ' . $subject;
    }

    public function normalizeSalutationMode(string $salutationMode): string
    {
        return in_array($salutationMode, ['auto', 'hallo', 'liebe', 'lieber'], true) ? $salutationMode : 'auto';
    }

    public function mailFooter(bool $memberContactMode = false): string
    {
        $footer = $memberContactMode
            ? (string) config('defaults.member_contact_footer', $this->settings->memberContactFooter())
            : $this->settings->mailFooter();

        return apply_branding_placeholders($footer);
    }

    public function defaultSubjectPrefix(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_subject_prefix', $this->settings->memberContactSubjectPrefix());
        }

        return $this->settings->defaultSubjectPrefix();
    }

    /**
     * „Eingeschränkte" Kontaktaufnahme: darf einzelne Personen anschreiben,
     * aber keine Sammel-Mailings und keine Kontakte verwalten. Über
     * Berechtigungen gesteuert statt an einen festen Rollennamen gebunden.
     * Bezieht sich immer auf den aktuell angemeldeten Nutzer.
     */
    public function isMemberContactMode(): bool
    {
        return $this->auth->can('mail.contact_single')
            && !$this->auth->can('mail.send')
            && !$this->auth->can('contacts.manage');
    }
}
