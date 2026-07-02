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
        $this->requirePermission('mail.send');
        $ids = array_map('intval', (array) ($request->input('selected_contacts', []) ?: $request->input('contact_ids', [])));
        $contacts = $this->contacts->findManyByIds($ids);

        if ($contacts === []) {
            flash('error', 'Bitte zuerst Kontakte auswählen.');
            Redirect::to('/');
        }

        $_SESSION['mail_draft_contact_ids'] = $ids;

        $this->render('mail/compose', [
            'contacts' => $contacts,
            'identities' => config('mail.identities', []),
            'replyToOptions' => $this->replyToOptions(),
            'mailFooter' => $this->settings->mailFooter(),
            'subjectPrefixOptions' => $this->settings->subjectPrefixOptions(),
            'defaultSubjectPrefix' => $this->settings->defaultSubjectPrefix(),
        ]);
    }

    public function test(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));
        $contactIds = array_map('intval', (array) ($request->input('contact_ids', []) ?: ($_SESSION['mail_draft_contact_ids'] ?? [])));
        $contacts = $this->contacts->findManyByIds($contactIds);
        $identity = $this->identityByKey((string) $request->input('sender_key'));
        $replyTo = $this->replyToByKey((string) $request->input('reply_to_key'));
        $user = $this->auth->user();

        if (!$identity || !$replyTo || !$user) {
            flash('error', 'Absender oder Nutzer konnte nicht geladen werden.');
            Redirect::to('/');
        }

        $sample = $contacts[0] ?? ['vorname' => 'Max', 'nachname' => 'Mustermann'];
        $subject = $this->composeSubject((string) $request->input('subject'), (string) $request->input('subject_prefix'));
        $message = str_replace(
            ['{Vorname}', '{Nachname}'],
            [$sample['vorname'], $sample['nachname']],
            $this->composeMailBody((string) $request->input('message'))
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
            'reply_to_key' => (string) $request->input('reply_to_key'),
            'subject_prefix' => (string) $request->input('subject_prefix'),
        ];
        Redirect::to('/mail/compose?contact_ids[]=' . implode('&contact_ids[]=', array_map('urlencode', array_map('strval', $contactIds))));
    }

    public function start(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $attachments = $this->uploads->storeAttachments($request->file('attachments'));
        $rawMessage = trim((string) $request->input('message'));
        $subjectPrefix = (string) $request->input('subject_prefix');
        $_SESSION['mail_job'] = [
            'contacts' => array_map('intval', (array) $request->input('contact_ids', [])),
            'subject' => $this->composeSubject((string) $request->input('subject'), $subjectPrefix),
            'message' => $this->composeMailBody($rawMessage),
            'sender_key' => (string) $request->input('sender_key'),
            'reply_to_key' => (string) $request->input('reply_to_key'),
            'attachments' => $attachments,
            'offset' => 0,
            'results' => [],
        ];
        $_SESSION['mail_draft'] = [
            'contact_ids' => $_SESSION['mail_job']['contacts'],
            'subject' => $_SESSION['mail_job']['subject'],
            'message' => $rawMessage,
            'sender_key' => $_SESSION['mail_job']['sender_key'],
            'reply_to_key' => $_SESSION['mail_job']['reply_to_key'],
            'subject_prefix' => $subjectPrefix,
        ];
        Redirect::to('/mail/status');
    }

    public function status(): void
    {
        $this->requirePermission('mail.send');
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
        ]);
    }

    public function batch(): void
    {
        $this->requirePermission('mail.send');
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
        $replyTo = $this->replyToByKey($job['reply_to_key']);
        $userId = (int) $this->auth->user()['id'];

        foreach ($slice as $contact) {
            $result = $this->mailer->sendMergedMail($identity, $replyTo, $contact, $job['subject'], $job['message'], $job['attachments'], $userId);
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

    private function replyToByKey(string $key): ?array
    {
        foreach ($this->replyToOptions() as $option) {
            if (($option['key'] ?? '') === $key) {
                return $option;
            }
        }

        return $this->replyToOptions()[0] ?? null;
    }

    private function replyToOptions(): array
    {
        $options = config('mail.reply_to_options', []);

        if ($options !== []) {
            return $options;
        }

        return config('mail.identities', []);
    }

    private function composeMailBody(string $message): string
    {
        $message = trim($message);
        $footer = trim($this->settings->mailFooter());

        return $footer === '' ? $message : $message . "\n\n" . $footer;
    }

    private function composeSubject(string $subject, string $selectedPrefix): string
    {
        $subject = trim($subject);
        $options = $this->settings->subjectPrefixOptions();
        $prefix = in_array($selectedPrefix, $options, true)
            ? $selectedPrefix
            : $this->settings->defaultSubjectPrefix();

        $normalizedPrefix = trim($prefix);

        return $normalizedPrefix === '' ? $subject : $normalizedPrefix . ' ' . $subject;
    }
}
