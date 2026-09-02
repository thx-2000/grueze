<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\RecipientListRepository;
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
        private \App\Repositories\TagRepository $tags,
        private RecipientListRepository $recipientLists
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Namensliste

    public function namensliste(Request $request): void
    {
        $this->requirePermission('contacts.manage');

        $categoryId = (string) $request->input('category_id', '');
        $sort = (string) $request->input('sort', 'nachname') === 'vorname' ? 'vorname' : 'nachname';
        $numbered = (string) $request->input('numbered', '1') === '1';
        $withoutEmail = (string) $request->input('without_email', '') === '1';
        $withoutPhone = (string) $request->input('without_phone', '') === '1';

        $contacts = $this->contacts->search([
            'category_id' => $categoryId,
            'without_email' => $withoutEmail ? '1' : '',
            'without_phone' => $withoutPhone ? '1' : '',
            'sort' => $sort,
            'direction' => 'asc',
        ]);

        $subjectTitle = 'Namensliste – bitte auf Vollständigkeit prüfen';

        $this->render('mail/namensliste', [
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'categoryId' => $categoryId,
            'sort' => $sort,
            'numbered' => $numbered,
            'withoutEmail' => $withoutEmail,
            'withoutPhone' => $withoutPhone,
            'count' => count($contacts),
            'nameList' => $this->formatNameList($contacts, $numbered),
            'canSend' => $this->auth->can('mail.send'),
            'totalWithEmail' => count($this->contacts->recipientIds([])),
            'subjectTitle' => $subjectTitle,
            'defaultSubject' => trim($this->settings->defaultSubjectPrefix() . ' ' . $subjectTitle),
        ]);
    }

    /** Übergibt die Namensliste als Nachrichtentext an den Rundmail-Flow. */
    public function namenslisteToRundmail(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $list = trim((string) $request->input('name_list'));
        $intro = trim((string) $request->input('intro'));
        $subject = trim((string) $request->input('subject_title')) ?: 'Namensliste';

        if ($list === '') {
            flash('error', 'Die Namensliste ist leer.');
            Redirect::to('/namensliste');
        }

        $_SESSION['mail_draft'] = [
            'subject' => $subject,
            'message' => ($intro !== '' ? $intro . "\n\n" : '') . $list,
            'salutation_mode' => 'hallo',
        ];

        Redirect::to('/rundmail');
    }

    public function namenslisteSend(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $list = trim((string) $request->input('name_list'));
        $intro = trim((string) $request->input('intro'));
        $subject = trim((string) $request->input('subject')) ?: 'Namensliste';
        $sendToSelf = (string) $request->input('send_to_self', '') === '1';

        $recipients = array_values(array_unique(array_filter(array_map(
            static fn (string $addr): string => trim($addr),
            preg_split('/[\s,;]+/', (string) $request->input('recipients', '')) ?: []
        ), static fn (string $addr): bool => $addr !== '')));

        $user = $this->auth->user();
        if ($sendToSelf && $user && !in_array($user['email'], $recipients, true)) {
            $recipients[] = $user['email'];
        }

        $invalid = array_filter($recipients, static fn (string $a): bool => !filter_var($a, FILTER_VALIDATE_EMAIL));
        if ($recipients === [] || $invalid !== []) {
            flash('error', $recipients === []
                ? 'Bitte mindestens eine Empfängeradresse angeben.'
                : 'Ungültige Adresse: ' . implode(', ', $invalid));
            Redirect::to('/namensliste');
        }
        if ($list === '') {
            flash('error', 'Die Namensliste ist leer.');
            Redirect::to('/namensliste');
        }

        $identity = $this->settings->mailIdentity();
        $body = ($intro !== '' ? $intro . "\n\n" : '') . $list;

        $sent = 0;
        $failed = [];
        foreach ($recipients as $to) {
            try {
                $this->mailer->sendSystemMail($identity, $to, $subject, $body, $identity['email']);
                $this->logs->addMailLog([
                    'user_id' => (int) $user['id'],
                    'contact_id' => null,
                    'empfaenger_email' => $to,
                    'betreff' => $subject,
                    'status' => 'gesendet',
                    'fehlermeldung' => null,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $failed[] = $to;
                $this->logs->addMailLog([
                    'user_id' => (int) $user['id'],
                    'contact_id' => null,
                    'empfaenger_email' => $to,
                    'betreff' => $subject,
                    'status' => 'fehlgeschlagen',
                    'fehlermeldung' => $e->getMessage(),
                ]);
            }
        }

        flash(
            $failed === [] ? 'success' : 'error',
            sprintf('Namensliste verschickt: %d gesendet%s.', $sent, $failed === [] ? '' : ', fehlgeschlagen: ' . implode(', ', $failed))
        );
        Redirect::to('/namensliste');
    }

    /** @param array<int,array> $contacts */
    private function formatNameList(array $contacts, bool $numbered): string
    {
        $lines = [];
        foreach ($contacts as $i => $contact) {
            $name = trim((string) ($contact['vorname'] ?? '') . ' ' . (string) ($contact['nachname'] ?? ''));
            $lines[] = $numbered ? sprintf('%d. %s', $i + 1, $name) : $name;
        }

        return implode("\n", $lines);
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

        $withEmail = $this->contacts->recipientIds([]);
        $recipientLists = array_map(function (array $list) use ($withEmail): array {
            $active = array_values(array_intersect($withEmail, $list['contact_ids']));

            return [
                'id' => $list['id'],
                'name' => $list['name'],
                'total' => count($list['contact_ids']),
                'reachable' => count($active),
            ];
        }, $this->recipientLists->all());

        $this->render('mail/rundmail', [
            'categories' => $categories,
            'tags' => $tags,
            'categoryCounts' => $categoryCounts,
            'tagCounts' => $tagCounts,
            'totalWithEmail' => count($withEmail),
            'fromFilter' => $fromFilter,
            'filterCount' => count($filterIds),
            'filterSummary' => $fromFilter ? $this->filterSummary($filters, $categories, $tags) : '',
            'recipientLists' => $recipientLists,
        ]);
    }

    public function rundmailStart(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $mode = (string) $request->input('mode', '');

        if ($mode === 'filter') {
            $ids = array_map('intval', (array) ($_SESSION['rundmail_filter_ids'] ?? []));
        } elseif ($mode === 'list') {
            $list = $this->recipientLists->find((int) $request->input('list_id'));
            if ($list === null) {
                flash('error', 'Gespeicherte Liste nicht gefunden.');
                Redirect::to('/rundmail');
            }
            $ids = array_values(array_intersect($this->contacts->recipientIds([]), $list['contact_ids']));
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

    // ----------------------------------------------- Gespeicherte Empfängerlisten

    /** Wird per fetch() vom Schreiben-Dialog aufgerufen; antwortet JSON. */
    public function saveRecipientList(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $name = trim((string) $request->input('name'));
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('contact_ids', [])),
            static fn (int $n): bool => $n > 0
        )));

        if ($name === '') {
            $this->json(['ok' => false, 'error' => 'Bitte einen Namen für die Liste angeben.'], 422);
        }
        if ($ids === []) {
            $this->json(['ok' => false, 'error' => 'Keine Empfänger zum Speichern.'], 422);
        }
        if ($this->recipientLists->nameExists($name)) {
            $this->json(['ok' => false, 'error' => 'Eine Liste mit diesem Namen gibt es schon.'], 409);
        }

        $user = $this->auth->user();
        $id = $this->recipientLists->create($name, $ids, isset($user['id']) ? (int) $user['id'] : null);

        $this->json(['ok' => true, 'name' => $name, 'count' => count($ids), 'id' => $id]);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public function renameRecipientList(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        if ($name === '' || $this->recipientLists->find($id) === null) {
            flash('error', 'Liste oder Name fehlt.');
            Redirect::to('/rundmail');
        }
        if ($this->recipientLists->nameExists($name, $id)) {
            flash('error', 'Eine Liste mit diesem Namen gibt es schon.');
            Redirect::to('/rundmail');
        }

        $this->recipientLists->rename($id, $name);
        flash('success', 'Liste umbenannt.');
        Redirect::to('/rundmail');
    }

    public function deleteRecipientList(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $this->recipientLists->delete((int) $request->input('id'));
        flash('success', 'Liste gelöscht.');
        Redirect::to('/rundmail');
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
            flash('error', 'In diesem Modus kann immer nur eine einzelne Person kontaktiert werden.');
            Redirect::to('/kontakte');
        }

        $_SESSION['mail_draft_contact_ids'] = $ids;

        // Entwurf einmalig übernehmen (z. B. aus der Namensliste) und danach
        // aus der Session entfernen, damit er nicht bei der nächsten Mail wieder auftaucht.
        $draft = (array) ($_SESSION['mail_draft'] ?? []);
        unset($_SESSION['mail_draft']);

        $this->render('mail/compose', [
            'contacts' => $contacts,
            'draft' => $draft,
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
        $memberContactMode = $this->isMemberContactMode();
        $identity = $this->identityByKey($memberContactMode
            ? $this->settings->defaultMailSenderKey()
            : (string) $request->input('sender_key'));
        $replyTo = $this->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);

        if (!$identity || !$replyTo || !$user) {
            flash('error', 'Absender oder Nutzer konnte nicht geladen werden.');
            Redirect::to('/kontakte');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'In diesem Modus kann immer nur eine einzelne Person kontaktiert werden.');
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
            flash('error', 'In diesem Modus kann immer nur eine einzelne Person kontaktiert werden.');
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

        $normalizedPrefix = trim(apply_branding_placeholders($prefix));

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

    /**
     * „Eingeschränkte" Kontaktaufnahme: darf einzelne Personen anschreiben,
     * aber keine Sammel-Mailings und keine Kontakte verwalten. Über
     * Berechtigungen gesteuert statt an einen festen Rollennamen gebunden.
     * Bezieht sich immer auf den aktuell angemeldeten Nutzer.
     */
    private function isMemberContactMode(): bool
    {
        return $this->auth->can('mail.contact_single')
            && !$this->auth->can('mail.send')
            && !$this->auth->can('contacts.manage');
    }

    private function memberReplyTo(?array $user): ?array
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

    private function mailFooter(bool $memberContactMode = false): string
    {
        $footer = $memberContactMode
            ? (string) config('defaults.member_contact_footer', $this->settings->memberContactFooter())
            : $this->settings->mailFooter();

        return apply_branding_placeholders($footer);
    }

    private function defaultSubjectPrefix(bool $memberContactMode = false): string
    {
        if ($memberContactMode) {
            return (string) config('defaults.member_contact_subject_prefix', $this->settings->memberContactSubjectPrefix());
        }

        return $this->settings->defaultSubjectPrefix();
    }
}
