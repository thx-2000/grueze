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
        private UploadService $uploads,
        private \App\Repositories\CategoryRepository $categories,
        private \App\Repositories\TagRepository $tags
    ) {
        parent::__construct($auth);
    }

    /** Filter aus dem Request lesen (identisch zur Kontaktliste). */
    private function recipientFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', [])))),
            'without_email' => (string) $request->input('without_email', '') === '1' ? '1' : '',
            'without_phone' => (string) $request->input('without_phone', '') === '1' ? '1' : '',
        ];
    }

    private function hasActiveFilter(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['category_id'] !== ''
            || $filters['tag_ids'] !== []
            || $filters['without_email'] === '1'
            || $filters['without_phone'] === '1';
    }

    public function rundmail(Request $request): void
    {
        $this->requirePermission('mail.send');

        $categories = $this->categories->all();
        $tags = $this->tags->all();
        $filters = $this->recipientFilters($request);
        $fromFilter = $request->input('from') === 'filter' && $this->hasActiveFilter($filters);

        $categoryCounts = [];
        foreach ($categories as $category) {
            $categoryCounts[(int) $category['id']] = count($this->contacts->recipientIds(['category_id' => (string) $category['id']]));
        }
        $tagCounts = [];
        foreach ($tags as $tag) {
            $tagCounts[(int) $tag['id']] = count($this->contacts->recipientIds(['tag_ids' => [(int) $tag['id']]]));
        }

        $filterIds = $fromFilter ? $this->contacts->recipientIds($filters) : [];
        $_SESSION['rundmail_filter_ids'] = $filterIds;

        $this->render('mail/rundmail', [
            'categories' => $categories,
            'tags' => $tags,
            'categoryCounts' => $categoryCounts,
            'tagCounts' => $tagCounts,
            'totalWithEmail' => count($this->contacts->recipientIds([])),
            'fromFilter' => $fromFilter,
            'filterCount' => count($filterIds),
            'filterSummary' => $fromFilter ? $this->filterSummary($filters, $categories, $tags) : '',
        ]);
    }

    public function rundmailStart(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $mode = (string) $request->input('mode', '');

        if ($mode === 'filter') {
            $ids = array_map('intval', (array) ($_SESSION['rundmail_filter_ids'] ?? []));
        } else {
            $filters = match ($mode) {
                'all' => [],
                'category' => ['category_id' => (string) $request->input('category_id', '')],
                'tags' => ['tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', []))))],
                default => null,
            };

            if ($filters === null
                || ($mode === 'category' && $filters['category_id'] === '')
                || ($mode === 'tags' && $filters['tag_ids'] === [])
            ) {
                flash('error', 'Bitte einen Empfängerkreis auswählen.');
                Redirect::to('/rundmail');
            }

            $ids = $this->contacts->recipientIds($filters);
        }

        if ($ids === []) {
            flash('error', 'In dieser Auswahl hat niemand eine Mailadresse hinterlegt.');
            Redirect::to('/rundmail');
        }

        $_GET['contact_ids'] = $ids;
        $this->compose(new Request());
    }

    private function filterSummary(array $filters, array $categories, array $tags): string
    {
        $parts = [];
        if ($filters['q'] !== '') {
            $parts[] = 'Suche „' . $filters['q'] . '"';
        }
        if ($filters['category_id'] !== '') {
            foreach ($categories as $category) {
                if ((string) $category['id'] === $filters['category_id']) {
                    $parts[] = 'Kategorie ' . $category['name'];
                }
            }
        }
        if ($filters['tag_ids'] !== []) {
            $names = [];
            foreach ($tags as $tag) {
                if (in_array((int) $tag['id'], $filters['tag_ids'], true)) {
                    $names[] = $tag['name'];
                }
            }
            if ($names !== []) {
                $parts[] = 'Tags: ' . implode(', ', $names);
            }
        }
        if ($filters['without_email'] === '1') {
            $parts[] = 'ohne Mailadresse';
        }
        if ($filters['without_phone'] === '1') {
            $parts[] = 'ohne Handynummer';
        }

        return $parts === [] ? 'alle Kontakte' : implode(' · ', $parts);
    }

    public function compose(Request $request): void
    {
        $this->requireMailAccess();
        $ids = array_map('intval', (array) ($request->input('selected_contacts', []) ?: $request->input('contact_ids', [])));
        $contacts = $this->contacts->findManyByIds($ids);
        $memberContactMode = $this->isMemberContactMode();

        if ($contacts === []) {
            flash('error', 'Bitte zuerst Kontakte auswählen.');
            Redirect::to('/kontakte');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'Stufenmitglieder können immer nur eine einzelne Person kontaktieren.');
            Redirect::to('/kontakte');
        }

        $_SESSION['mail_draft_contact_ids'] = $ids;

        $this->render('mail/compose', [
            'contacts' => $contacts,
            'identities' => $this->settings->mailIdentities(),
            'replyToOptions' => $this->replyToOptions($memberContactMode),
            'mailFooter' => $this->mailFooter($memberContactMode),
            'subjectPrefixOptions' => $this->settings->subjectPrefixOptions(),
            'defaultSubjectPrefix' => $this->defaultSubjectPrefix($memberContactMode),
            'defaultSalutationMode' => 'auto',
            'memberContactMode' => $memberContactMode,
            'defaultSenderKey' => $this->settings->defaultMailSenderKey(),
            'defaultReplyToKey' => $this->settings->defaultMailReplyToKey(),
        ]);
    }

    public function composeAll(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $ids = $this->contacts->mailingContactIds();
        if ($ids === []) {
            flash('error', 'Es sind aktuell keine Kontakte mit Mailadresse vorhanden.');
            Redirect::to('/kontakte');
        }

        $_GET['contact_ids'] = $ids;
        $this->compose(new Request());
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
            ? $this->settings->defaultMailSenderKey()
            : (string) $request->input('sender_key'));
        $replyTo = $this->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);

        if (!$identity || !$replyTo || !$user) {
            flash('error', 'Absender oder Nutzer konnte nicht geladen werden.');
            Redirect::to('/kontakte');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'Stufenmitglieder können immer nur eine einzelne Person kontaktieren.');
            Redirect::to('/kontakte');
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
            Redirect::to('/kontakte');
        }
        $senderKey = $memberContactMode
            ? $this->settings->defaultMailSenderKey()
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
            Redirect::to('/kontakte');
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
        foreach ($this->settings->mailIdentities() as $identity) {
            if (($identity['key'] ?? '') === $key) {
                return $identity;
            }
        }

        return $this->settings->mailIdentity();
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

        $options = $this->settings->mailReplyToOptions();

        if ($options !== []) {
            return $options;
        }

        return $this->settings->mailIdentities();
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
        Redirect::to('/kontakte');
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
            'name' => (string) ($user['name'] ?? branding_value('branding_short_name', 'Stufenmitglied') . '-Stufenmitglied'),
            'email' => (string) $user['email'],
        ];
    }

    private function mailFooter(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_footer', $this->settings->memberContactFooter());
        }

        return $this->settings->mailFooter();
    }

    private function defaultSubjectPrefix(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_subject_prefix', $this->settings->memberContactSubjectPrefix());
        }

        return $this->settings->defaultSubjectPrefix();
    }
}
