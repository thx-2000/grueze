<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\SettingRepository;
use App\Services\MailService;
use App\Services\UploadService;
use App\Support\Redirect;

final class MailController extends BaseController
{
    private const MEMBER_CONTACT_FOOTER_FALLBACK = "Diese Nachricht wurde von einem Stufenmitglied über die interne Kontaktfunktion versendet und stammt nicht vom Orga-Team.\nDu erhältst sie, weil deine Kontaktdaten in der Adress-Zentrale hinterlegt sind.\nAntworten auf diese Nachricht gehen direkt an die absendende Person.\nFalls unsere Nachrichten fälschlich als Spam erkannt werden, nimm bitte kontakt@example.org und mailer@example.org in dein Adressbuch auf.\nWenn du keine weiteren Kontaktanfragen über dieses System erhalten möchtest, schreibe bitte an kontakt@example.org. Wir prüfen das dann mit dir.";
    private const MEMBER_CONTACT_SUBJECT_PREFIX = '[Kontakt]';

    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private LogRepository $logs,
        private SettingRepository $settings,
        private MailService $mailer,
        private UploadService $uploads
    ) {
        parent::__construct($auth);
    }

    public function compose(Request $request): void
    {
        $this->requireMailAccess();
        $ids = array_map('intval', (array) ($request->input('selected_contacts', []) ?: $request->input('contact_ids', [])));
        $contacts = $this->contacts->findManyByIds($ids);
        $memberContactMode = $this->isMemberContactMode();

        if ($contacts === []) {
            flash('error', 'Bitte zuerst Kontakte auswählen.');
            Redirect::to('/');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'Stufenmitglieder können immer nur eine einzelne Person kontaktieren.');
            Redirect::to('/');
        }

        $_SESSION['mail_draft_contact_ids'] = $ids;

        $this->render('mail/compose', [
            'contacts' => $contacts,
            'identities' => config('mail.identities', []),
            'replyToOptions' => $this->replyToOptions($memberContactMode),
            'mailFooter' => $this->mailFooter($memberContactMode),
            'subjectPrefixOptions' => $this->settings->subjectPrefixOptions(),
            'defaultSubjectPrefix' => $this->defaultSubjectPrefix($memberContactMode),
            'defaultSalutationMode' => 'auto',
            'memberContactMode' => $memberContactMode,
        ]);
    }

    public function test(Request $request): void
    {
        $this->requireMailAccess();
        Csrf::validate($request->input('_csrf'));
        $contactIds = array_map('intval', (array) ($request->input('contact_ids', []) ?: ($_SESSION['mail_draft_contact_ids'] ?? [])));
        $contacts = $this->contacts->findManyByIds($contactIds);
        $user = $this->auth->user();
        $memberContactMode = $this->isMemberContactMode($user);
        $identity = $this->identityByKey($memberContactMode
            ? (string) config('mail.default_sender_key', config('mail.identities.0.key', ''))
            : (string) $request->input('sender_key'));
        $replyTo = $this->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);

        if (!$identity || !$replyTo || !$user) {
            flash('error', 'Absender oder Nutzer konnte nicht geladen werden.');
            Redirect::to('/');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'Stufenmitglieder können immer nur eine einzelne Person kontaktieren.');
            Redirect::to('/');
        }

        $sample = $contacts[0] ?? ['vorname' => 'Max', 'nachname' => 'Mustermann'];
        $salutationMode = $this->normalizeSalutationMode((string) $request->input('salutation_mode', 'auto'));
        $subject = $this->composeSubject((string) $request->input('subject'), (string) $request->input('subject_prefix'), $memberContactMode);
        $message = $this->mailer->renderMessageTemplate(
            $sample,
            $this->composeMailBody((string) $request->input('message'), $memberContactMode),
            $salutationMode
        );
        $this->mailer->sendSystemMail(
            $identity,
            $user['email'],
            '[Testmail] ' . $subject,
            $message,
            $replyTo['email']
        );
        $this->logs->addMailLog([
            'user_id' => $user['id'],
            'contact_id' => null,
            'empfaenger_email' => $user['email'],
            'betreff' => '[Testmail] ' . $subject,
            'status' => 'gesendet',
            'fehlermeldung' => null,
        ]);
        flash('success', 'Testmail wurde an dein Konto gesendet.');
        $_SESSION['mail_draft'] = [
            'contact_ids' => $contactIds,
            'subject' => (string) $request->input('subject'),
            'message' => (string) $request->input('message'),
            'sender_key' => (string) $request->input('sender_key'),
            'reply_to_key' => $replyTo['key'] ?? (string) $request->input('reply_to_key'),
            'subject_prefix' => $memberContactMode ? $this->defaultSubjectPrefix(true) : (string) $request->input('subject_prefix'),
            'salutation_mode' => $salutationMode,
            'member_contact_mode' => $memberContactMode,
        ];
        Redirect::to('/mail/compose?contact_ids[]=' . implode('&contact_ids[]=', array_map('urlencode', array_map('strval', $contactIds))));
    }

    public function start(Request $request): void
    {
        $this->requireMailAccess();
        Csrf::validate($request->input('_csrf'));

        $attachments = $this->uploads->storeAttachments($request->file('attachments'));
        $rawMessage = trim((string) $request->input('message'));
        $memberContactMode = $this->isMemberContactMode();
        $user = $this->auth->user();
        $subjectPrefix = $memberContactMode ? $this->defaultSubjectPrefix(true) : (string) $request->input('subject_prefix');
        $salutationMode = $this->normalizeSalutationMode((string) $request->input('salutation_mode', 'auto'));
        $contactIds = array_map('intval', (array) $request->input('contact_ids', []));
        if ($memberContactMode && count($contactIds) !== 1) {
            flash('error', 'Stufenmitglieder können immer nur eine einzelne Person kontaktieren.');
            Redirect::to('/');
        }
        $senderKey = $memberContactMode
            ? (string) config('mail.default_sender_key', config('mail.identities.0.key', ''))
            : (string) $request->input('sender_key');
        $replyTo = $this->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);
        $_SESSION['mail_job'] = [
            'contacts' => $contactIds,
            'subject' => $this->composeSubject((string) $request->input('subject'), $subjectPrefix, $memberContactMode),
            'message' => $this->composeMailBody($rawMessage, $memberContactMode),
            'sender_key' => $senderKey,
            'reply_to_key' => $replyTo['key'] ?? (string) $request->input('reply_to_key'),
            'salutation_mode' => $salutationMode,
            'member_contact_mode' => $memberContactMode,
            'attachments' => $attachments,
            'offset' => 0,
            'results' => [],
        ];
        $_SESSION['mail_draft'] = [
            'contact_ids' => $_SESSION['mail_job']['contacts'],
            'subject' => $_SESSION['mail_job']['subject'],
            'message' => $rawMessage,
            'sender_key' => $senderKey,
            'reply_to_key' => $_SESSION['mail_job']['reply_to_key'],
            'subject_prefix' => $subjectPrefix,
            'salutation_mode' => $salutationMode,
            'member_contact_mode' => $memberContactMode,
        ];
        Redirect::to('/mail/status');
    }

    public function status(): void
    {
        $this->requireMailAccess();
        $job = $_SESSION['mail_job'] ?? null;

        if (!$job) {
            flash('error', 'Es ist kein aktiver Versandauftrag vorhanden.');
            Redirect::to('/');
        }

        $contacts = $this->contacts->findManyByIds($job['contacts']);

        $this->render('mail/status', [
            'job' => $job,
            'contacts' => $contacts,
            'canViewLog' => $this->auth->can('mail.view_log'),
            'memberContactMode' => (bool) ($job['member_contact_mode'] ?? false),
        ]);
    }

    public function batch(): void
    {
        $this->requireMailAccess();
        \App\Core\Csrf::validate($_POST['_csrf'] ?? null);
        header('Content-Type: application/json; charset=UTF-8');

        $job = $_SESSION['mail_job'] ?? null;
        if (!$job) {
            echo json_encode(['ok' => false, 'message' => 'Kein Versandauftrag aktiv.']);
            return;
        }

        $contacts = $this->contacts->findManyByIds($job['contacts']);
        $slice = array_slice($contacts, (int) $job['offset'], (int) config('mail.batch_size', 3));
        $identity = $this->identityByKey($job['sender_key']);
        $user = $this->auth->user();
        $replyTo = $this->replyToByKey((string) $job['reply_to_key'], (bool) ($job['member_contact_mode'] ?? false), $user);
        $userId = (int) $user['id'];

        foreach ($slice as $contact) {
            $result = $this->mailer->sendMergedMail(
                $identity,
                $replyTo,
                $contact,
                $job['subject'],
                $job['message'],
                (string) ($job['salutation_mode'] ?? 'auto'),
                $job['attachments'],
                $userId
            );
            $job['results'][] = [
                'name' => $contact['vorname'] . ' ' . $contact['nachname'],
                'ok' => $result['ok'],
                'error' => $result['error'] ?? null,
            ];

            sleep((int) config('mail.send_delay_seconds', 1));
        }

        $job['offset'] += count($slice);
        $_SESSION['mail_job'] = $job;
        $done = $job['offset'] >= count($contacts);

        if ($done) {
            $this->uploads->cleanupAttachments($job['attachments']);
            unset($_SESSION['mail_job']);
        }

        echo json_encode([
            'ok' => true,
            'done' => $done,
            'processed' => $job['offset'],
            'total' => count($contacts),
            'results' => $job['results'],
        ]);
    }

    private function identityByKey(string $key): ?array
    {
        foreach (config('mail.identities', []) as $identity) {
            if (($identity['key'] ?? '') === $key) {
                return $identity;
            }
        }

        return config('mail.identities.0');
    }

    private function replyToByKey(string $key, bool $memberContactMode = false, ?array $user = null): ?array
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

    private function replyToOptions(bool $memberContactMode = false): array
    {
        if ($memberContactMode) {
            $option = $this->memberReplyTo($this->auth->user());

            return $option ? [$option] : [];
        }

        $options = config('mail.reply_to_options', []);

        if ($options !== []) {
            return $options;
        }

        return config('mail.identities', []);
    }

    private function composeMailBody(string $message, bool $memberContactMode = false): string
    {
        $message = trim($message);
        $footer = trim($this->mailFooter($memberContactMode));

        return $footer === '' ? $message : $message . "\n\n" . $footer;
    }

    private function composeSubject(string $subject, string $selectedPrefix, bool $memberContactMode = false): string
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

        $normalizedPrefix = trim($prefix);

        return $normalizedPrefix === '' ? $subject : $normalizedPrefix . ' ' . $subject;
    }

    private function normalizeSalutationMode(string $salutationMode): string
    {
        return in_array($salutationMode, ['auto', 'hallo', 'liebe', 'lieber'], true) ? $salutationMode : 'auto';
    }

    private function requireMailAccess(): void
    {
        if ($this->auth->can('mail.send') || $this->auth->can('mail.contact_single')) {
            return;
        }

        flash('error', 'Dafür fehlen die nötigen Rechte.');
        Redirect::to('/');
    }

    private function isMemberContactMode(?array $user = null): bool
    {
        $currentUser = $user ?? $this->auth->user();

        return (string) ($currentUser['role_name'] ?? '') === 'stufenmitglied';
    }

    private function memberReplyTo(?array $user): ?array
    {
        if (!$user || empty($user['email'])) {
            return null;
        }

        return [
            'key' => 'member_reply',
            'name' => (string) ($user['name'] ?? 'Stufenmitglied'),
            'email' => (string) $user['email'],
        ];
    }

    private function mailFooter(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_footer', self::MEMBER_CONTACT_FOOTER_FALLBACK);
        }

        return $this->settings->mailFooter();
    }

    private function defaultSubjectPrefix(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_subject_prefix', self::MEMBER_CONTACT_SUBJECT_PREFIX);
        }

        return $this->settings->defaultSubjectPrefix();
    }
}
