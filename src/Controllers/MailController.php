<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\SentMailRepository;
use App\Repositories\SettingRepository;
use App\Services\MailComposer;
use App\Services\MailRecipientResolver;
use App\Services\MailService;
use App\Services\UploadService;
use App\Support\JsonResponse;
use App\Support\Redirect;

/**
 * Serien-E-Mail: Empfängerkreis wählen, Text schreiben, Testmail, Versand in
 * Häppchen. „Wer bekommt die Mail" liegt im MailRecipientResolver, „wie ist
 * sie adressiert/unterschrieben" im MailComposer, die gespeicherten
 * Empfängerlisten im RecipientListController.
 */
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
        private \App\Repositories\EventRepository $events,
        private MailRecipientResolver $recipients,
        private MailComposer $composer,
        private SentMailRepository $sentMails,
    ) {
        parent::__construct($auth);
    }

    public function rundmail(Request $request): void
    {
        $this->requirePermission('mail.send');

        $categories = $this->categories->all();
        $tags = $this->tags->all();
        $filters = $this->recipients->filters($request);
        $fromFilter = $request->input('from') === 'filter' && $this->recipients->hasActiveFilter($filters);

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

        // Entwurf einmalig übernehmen (z. B. aus der Vollständigkeit oder von
        // „an Teilnehmer" eines Termins – dann mit fester Empfängerliste).
        $draft = (array) ($_SESSION['mail_draft'] ?? []);
        unset($_SESSION['mail_draft']);

        $presetContacts = !empty($draft['contact_ids'])
            ? $this->contacts->findManyByIds(array_map('intval', (array) $draft['contact_ids']))
            : [];

        $this->render('mail/nachricht', array_merge($this->composer->messageComposeData(), [
            'presetContacts' => $presetContacts,
            'draft' => $draft,
            'eventId' => isset($draft['event_id']) ? (int) $draft['event_id'] : null,
            'categories' => $categories,
            'tags' => $tags,
            'categoryCounts' => $categoryCounts,
            'tagCounts' => $tagCounts,
            'totalWithEmail' => count($this->contacts->recipientIds([])),
            'fromFilter' => $fromFilter,
            'filterCount' => count($filterIds),
            'filterSummary' => $fromFilter ? $this->recipients->filterSummary($filters, $categories, $tags) : '',
            'recipientLists' => $this->recipients->reachableLists(),
        ]));
    }

    /** Live-Empfängerzahl für die Nachrichten-Seite (JSON). */
    public function recipientCount(Request $request): never
    {
        $this->requirePermission('mail.send');

        JsonResponse::send(['count' => count($this->recipients->resolve($request))]);
    }

    /**
     * Ersetzt {Abstimmungslink} in Betreff und Text durch den persönlichen
     * Token-Link des Kontakts – oder lässt beides unverändert, wenn der Job
     * nicht zu einem Termin gehört.
     *
     * @return array{0: string, 1: string} [Betreff, Text]
     */
    private function applyVoteLink(array $job, int $contactId): array
    {
        $subject = (string) $job['subject'];
        $message = (string) $job['message'];
        if (empty($job['event_tokens'])) {
            return [$subject, $message];
        }

        $token = (string) ($job['event_tokens'][$contactId] ?? '');
        $link = $token !== '' ? url('/abstimmen?token=' . $token) : url('/abstimmungen');

        return [
            str_replace('{Abstimmungslink}', $link, $subject),
            str_replace('{Abstimmungslink}', $link, $message),
        ];
    }

    public function compose(Request $request): void
    {
        $this->requireMailAccess();
        $ids = array_map('intval', (array) ($request->input('selected_contacts', []) ?: $request->input('contact_ids', [])));
        $contacts = $this->contacts->findManyByIds($ids);
        $memberContactMode = $this->composer->isMemberContactMode();

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

        // Einzelkontakt-Aufnahme (Mitglieder) behält die schlanke Sonderansicht.
        if ($memberContactMode) {
            $this->render('mail/compose', [
                'contacts' => $contacts,
                'draft' => $draft,
                'identities' => $this->settings->mailIdentities(),
                'replyToOptions' => $this->composer->replyToOptions(true),
                'mailFooter' => $this->composer->mailFooter(true),
                'subjectPrefixOptions' => $this->settings->subjectPrefixOptions(),
                'defaultSubjectPrefix' => $this->composer->defaultSubjectPrefix(true),
                'defaultSalutationMode' => 'auto',
                'memberContactMode' => true,
                'defaultSenderKey' => $this->settings->defaultMailSenderKey(),
                'defaultReplyToKey' => $this->settings->defaultMailReplyToKey(),
            ]);

            return;
        }

        // Staff mit fester Auswahl landen auf der gemeinsamen Nachrichten-Seite,
        // Empfängerkreis auf „diese Auswahl" vorbelegt.
        [$categories, $tags] = [$this->categories->all(), $this->tags->all()];
        $categoryCounts = [];
        foreach ($categories as $category) {
            $categoryCounts[(int) $category['id']] = count($this->contacts->recipientIds(['category_id' => (string) $category['id']]));
        }
        $tagCounts = [];
        foreach ($tags as $tag) {
            $tagCounts[(int) $tag['id']] = count($this->contacts->recipientIds(['tag_ids' => [(int) $tag['id']]]));
        }

        $this->render('mail/nachricht', array_merge($this->composer->messageComposeData(), [
            'presetContacts' => $contacts,
            'draft' => $draft,
            'eventId' => isset($draft['event_id']) ? (int) $draft['event_id'] : null,
            'categories' => $categories,
            'tags' => $tags,
            'categoryCounts' => $categoryCounts,
            'tagCounts' => $tagCounts,
            'totalWithEmail' => count($this->contacts->recipientIds([])),
            'fromFilter' => false,
            'filterCount' => 0,
            'filterSummary' => '',
            'recipientLists' => $this->recipients->reachableLists(),
        ]));
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
        $contactIds = $request->input('recipient_mode') !== null
            ? $this->recipients->resolve($request)
            : array_map('intval', (array) ($request->input('contact_ids', []) ?: ($_SESSION['mail_draft_contact_ids'] ?? [])));
        $this->guardMassSend($request, $contactIds);
        $contacts = $this->contacts->findManyByIds($contactIds);
        $user = $this->auth->user();
        $memberContactMode = $this->composer->isMemberContactMode();
        $identity = $this->composer->identityByKey($memberContactMode
            ? $this->settings->defaultMailSenderKey()
            : (string) $request->input('sender_key'));
        $replyTo = $this->composer->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);

        if (!$identity || !$replyTo || !$user) {
            flash('error', 'Absender oder Konto konnte nicht geladen werden.');
            Redirect::to('/kontakte');
        }

        if ($memberContactMode && count($contacts) !== 1) {
            flash('error', 'In diesem Modus kann immer nur eine einzelne Person kontaktiert werden.');
            Redirect::to('/kontakte');
        }

        $sample = $contacts[0] ?? ['vorname' => 'Max', 'nachname' => 'Mustermann'];
        $salutationMode = $this->composer->normalizeSalutationMode((string) $request->input('salutation_mode', 'auto'));
        $subject = $this->composer->composeSubject((string) $request->input('subject'), (string) $request->input('subject_prefix'), $memberContactMode);
        $rawTestMessage = $this->composer->composeMailBody((string) $request->input('message'), $memberContactMode);

        // {Abstimmungslink} in der Testmail mit dem Link der Beispielperson zeigen.
        $eventId = (int) $request->input('event_id') ?: null;
        if ($eventId !== null) {
            $tokens = $this->events->tokensForEvent($eventId);
            $link = ($token = $tokens[(int) ($sample['id'] ?? 0)] ?? '') !== ''
                ? url('/abstimmen?token=' . $token)
                : url('/abstimmungen');
            $subject = str_replace('{Abstimmungslink}', $link, $subject);
            $rawTestMessage = str_replace('{Abstimmungslink}', $link, $rawTestMessage);
        }

        $message = $this->mailer->renderMessageTemplate($sample, $rawTestMessage, $salutationMode);
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
        $recipientMode = (string) $request->input('recipient_mode', '');
        $_SESSION['mail_draft'] = [
            'contact_ids' => $contactIds,
            'subject' => (string) $request->input('subject'),
            'message' => (string) $request->input('message'),
            'sender_key' => (string) $request->input('sender_key'),
            'reply_to_key' => $replyTo['key'] ?? (string) $request->input('reply_to_key'),
            'subject_prefix' => $memberContactMode ? $this->composer->defaultSubjectPrefix(true) : (string) $request->input('subject_prefix'),
            'salutation_mode' => $salutationMode,
            'member_contact_mode' => $memberContactMode,
            'event_id' => $eventId,
            'recipient_mode' => $recipientMode,
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', [])))),
            'list_id' => (string) $request->input('list_id', ''),
        ];

        if ($recipientMode !== '' && $recipientMode !== 'selection') {
            Redirect::to('/rundmail');
        }
        Redirect::to('/mail/compose?contact_ids[]=' . implode('&contact_ids[]=', array_map('urlencode', array_map('strval', $contactIds))));
    }

    public function start(Request $request): void
    {
        $this->requireMailAccess();
        Csrf::validate($request->input('_csrf'));

        $attachments = $this->uploads->storeAttachments($request->file('attachments'));
        $rawMessage = trim((string) $request->input('message'));
        $memberContactMode = $this->composer->isMemberContactMode();
        $user = $this->auth->user();
        $subjectPrefix = $memberContactMode ? $this->composer->defaultSubjectPrefix(true) : (string) $request->input('subject_prefix');
        $salutationMode = $this->composer->normalizeSalutationMode((string) $request->input('salutation_mode', 'auto'));
        $contactIds = $request->input('recipient_mode') !== null
            ? $this->recipients->resolve($request)
            : array_map('intval', (array) $request->input('contact_ids', []));
        $this->guardMassSend($request, $contactIds);
        if ($memberContactMode && count($contactIds) !== 1) {
            flash('error', 'In diesem Modus kann immer nur eine einzelne Person kontaktiert werden.');
            Redirect::to('/kontakte');
        }
        if (!$memberContactMode && $contactIds === []) {
            flash('error', 'In diesem Empfängerkreis hat niemand eine Mailadresse hinterlegt.');
            Redirect::to('/rundmail');
        }
        $senderKey = $memberContactMode
            ? $this->settings->defaultMailSenderKey()
            : (string) $request->input('sender_key');
        $replyTo = $this->composer->replyToByKey((string) $request->input('reply_to_key'), $memberContactMode, $user);
        $eventId = (int) $request->input('event_id') ?: null;
        $_SESSION['mail_job'] = [
            'contacts' => $contactIds,
            'subject' => $this->composer->composeSubject((string) $request->input('subject'), $subjectPrefix, $memberContactMode),
            'message' => $this->composer->composeMailBody($rawMessage, $memberContactMode),
            // Rohfassung für den „Gesendete Nachrichten"-Verlauf (ohne Präfix/Fuß).
            'raw_subject' => trim((string) $request->input('subject')),
            'raw_message' => $rawMessage,
            'subject_prefix' => $subjectPrefix,
            'sender_key' => $senderKey,
            'reply_to_key' => $replyTo['key'] ?? (string) $request->input('reply_to_key'),
            'salutation_mode' => $salutationMode,
            'member_contact_mode' => $memberContactMode,
            'event_id' => $eventId,
            'event_tokens' => $eventId !== null ? $this->events->tokensForEvent($eventId) : [],
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

    /**
     * Startet den Serienversand der Weihnachtsgrüße aus der vorbereiteten
     * (und in der Vorschau gemischten) Zuordnung in der Session.
     */
    public function sendGreetings(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $batch = $_SESSION['greeting_batch'] ?? null;
        if (!is_array($batch) || empty($batch['assignments'])) {
            flash('error', 'Keine vorbereiteten Grüße gefunden. Bitte erneut vorbereiten.');
            Redirect::to('/gruesse/weihnachten');
        }

        $user = $this->auth->user();
        $identity = $this->composer->identityByKey((string) $batch['sender_key']) ?? $this->settings->mailIdentity();
        $replyTo = $this->composer->replyToByKey((string) $batch['reply_to_key'], false, $user);

        $perContact = [];
        foreach ((array) $batch['assignments'] as $contactId => $text) {
            $perContact[(int) $contactId] = $this->composer->composeMailBody((string) $text, false);
        }

        $_SESSION['mail_job'] = [
            'contacts' => array_map('intval', array_keys($perContact)),
            'subject' => $this->composer->composeSubject((string) $batch['subject'], $this->settings->subjectPrefixOptions()[0] ?? '', false),
            'message' => (string) (reset($perContact) ?: ''),
            'raw_subject' => trim((string) $batch['subject']),
            'raw_message' => (string) (reset($batch['assignments']) ?: ''),
            'subject_prefix' => (string) ($this->settings->subjectPrefixOptions()[0] ?? ''),
            'kind' => 'gruesse',
            'per_contact_message' => $perContact,
            'sender_key' => $identity['key'],
            'reply_to_key' => $replyTo['key'] ?? (string) $batch['reply_to_key'],
            'salutation_mode' => 'auto',
            'member_contact_mode' => false,
            'event_id' => null,
            'event_tokens' => [],
            'attachments' => [],
            'offset' => 0,
            'results' => [],
        ];
        unset($_SESSION['greeting_batch']);
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
        Csrf::validate($_POST['_csrf'] ?? null);
        header('Content-Type: application/json; charset=UTF-8');

        $job = $_SESSION['mail_job'] ?? null;
        if (!$job) {
            echo json_encode(['ok' => false, 'message' => 'Kein Versandauftrag aktiv.']);
            return;
        }

        $contacts = $this->contacts->findManyByIds($job['contacts']);
        $slice = array_slice($contacts, (int) $job['offset'], (int) config('mail.batch_size', 3));
        $identity = $this->composer->identityByKey($job['sender_key']);
        $user = $this->auth->user();
        $replyTo = $this->composer->replyToByKey((string) $job['reply_to_key'], (bool) ($job['member_contact_mode'] ?? false), $user);
        $userId = (int) $user['id'];

        foreach ($slice as $contact) {
            $perJob = $job;
            if (isset($job['per_contact_message'][(int) $contact['id']])) {
                $perJob['message'] = (string) $job['per_contact_message'][(int) $contact['id']];
            }
            [$subject, $message] = $this->applyVoteLink($perJob, (int) $contact['id']);
            $result = $this->mailer->sendMergedMail(
                $identity,
                $replyTo,
                $contact,
                $subject,
                $message,
                (string) ($job['salutation_mode'] ?? 'auto'),
                $job['attachments'],
                $userId
            );
            $job['results'][] = [
                'contact_id' => (int) $contact['id'],
                'email' => (string) ($contact['emails'][0]['email'] ?? ''),
                'name' => trim($contact['vorname'] . ' ' . $contact['nachname']),
                'ok' => $result['ok'],
                'status' => $result['ok'] ? 'gesendet' : 'fehlgeschlagen',
                'error' => $result['error'] ?? null,
            ];

            sleep((int) config('mail.send_delay_seconds', 1));
        }

        $job['offset'] += count($slice);
        $_SESSION['mail_job'] = $job;
        $done = $job['offset'] >= count($contacts);

        if ($done) {
            $this->uploads->cleanupAttachments($job['attachments']);
            $this->recordSentMail($job, $user);
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

    /**
     * Abgeschlossenen Versand in den „Gesendete Nachrichten"-Verlauf schreiben.
     * Fehler hier dürfen den Abschluss des Versands nie stören.
     *
     * @param array<string,mixed> $job
     * @param array<string,mixed>|null $user
     */
    private function recordSentMail(array $job, ?array $user): void
    {
        try {
            $kind = (string) ($job['kind'] ?? match (true) {
                (bool) ($job['member_contact_mode'] ?? false) => 'einzeln',
                !empty($job['event_id']) => 'termin',
                default => 'rundmail',
            });

            $recipients = array_map(static fn (array $r): array => [
                'contact_id' => (int) ($r['contact_id'] ?? 0),
                'email' => (string) ($r['email'] ?? ''),
                'name' => (string) ($r['name'] ?? ''),
                'status' => (string) ($r['status'] ?? (($r['ok'] ?? false) ? 'gesendet' : 'fehlgeschlagen')),
                'error' => $r['error'] ?? null,
            ], (array) ($job['results'] ?? []));

            $this->sentMails->record([
                'user_id' => (int) ($user['id'] ?? 0),
                'sender_name' => (string) ($user['name'] ?? ''),
                'kind' => $kind,
                'subject' => (string) ($job['raw_subject'] ?? $job['subject'] ?? ''),
                'subject_prefix' => (string) ($job['subject_prefix'] ?? ''),
                'body' => (string) ($job['raw_message'] ?? $job['message'] ?? ''),
                'salutation_mode' => (string) ($job['salutation_mode'] ?? 'auto'),
                'sender_key' => (string) ($job['sender_key'] ?? ''),
                'reply_to_key' => (string) ($job['reply_to_key'] ?? ''),
                'recipients' => $recipients,
            ]);
        } catch (\Throwable) {
            // Verlauf ist Beiwerk – nie den Versandabschluss gefährden.
        }
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
     * Alles außer „genau eine ausgewählte Person" ist ein Sammelversand und
     * braucht `mail.send` – unabhängig davon, ob jemand über `contacts.manage`
     * gerade nicht im „nur Einzelkontakt"-Modus steckt.
     *
     * @param list<int> $contactIds
     */
    private function guardMassSend(Request $request, array $contactIds): void
    {
        if ($this->auth->can('mail.send')) {
            return;
        }

        $mode = (string) $request->input('recipient_mode', '');
        $singlePick = ($mode === '' || $mode === 'selection') && count($contactIds) <= 1;
        if ($singlePick) {
            return;
        }

        flash('error', 'Für einen Sammelversand fehlt die Berechtigung „Nachrichten senden".');
        Redirect::to('/kontakte');
    }
}
