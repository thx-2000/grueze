<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\EventRepository;
use App\Repositories\LogRepository;
use App\Services\IcalService;
use App\Support\Redirect;

/**
 * Termine / Terminfindung. Anlegen und Verwalten braucht `events.manage`;
 * Abstimmen läuft über `/abstimmen?token=…` auch ohne Login.
 */
final class EventController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private EventRepository $events,
        private ContactRepository $contacts,
        private CategoryRepository $categories,
        private LogRepository $logs,
        private IcalService $ical,
    ) {
        parent::__construct($auth);
    }

    /**
     * Kalender-Download (.ics) für einen festgelegten Termin. Öffentlich – der
     * `ical_uid`-Schlüssel ist nicht erratbar; der Inhalt (Titel, Datum, Ort)
     * steht ohnehin in der Einladungs-/Ergebnismail.
     */
    public function ical(Request $request): void
    {
        $event = $this->events->findByIcalUid((string) $request->input('k', ''));
        $body = $event !== null ? $this->ical->forEvent($event, $this->host()) : null;
        if ($body === null) {
            render_error_page(404, 'Kein Termin', 'Für diesen Link gibt es keinen festgelegten Termin.');

            return;
        }

        $name = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $event['title']) ?: 'termin';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . trim($name, '-') . '.ics"');
        header('X-Robots-Tag: noindex, nofollow');
        echo $body;
    }

    private function host(): string
    {
        $base = (string) config('app.base_url', '');
        $host = parse_url($base, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'grueze.local';
    }

    // ------------------------------------------------------------- Verwaltung

    public function index(Request $request): void
    {
        $this->requirePermission('events.manage');
        $archive = (string) $request->input('archiv', '') === '1';

        $this->render('events/index', [
            'events' => $this->events->all($archive),
            'showArchive' => $archive,
        ]);
    }

    // 'fixed_date' ist hier bewusst raus – neu geht das über den Bereich
    // „Termine" (AnnouncementController). Bestehende Alt-Datensätze mit
    // kind='fixed_date' bleiben lesbar/bearbeitbar (siehe applyOptions()),
    // nur die Neuanlage bietet den Typ nicht mehr an.
    private const KINDS = ['date_poll', 'poll'];

    public function createForm(Request $request): void
    {
        $this->requirePermission('events.manage');
        $kind = (string) $request->input('typ', '');
        $this->render('events/form', [
            'event' => null,
            'kind' => in_array($kind, self::KINDS, true) ? $kind : null,
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));

        $kind = (string) $request->input('kind', 'date_poll');
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'date_poll';
        $data = $this->detailData($request) + ['kind' => $kind];
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/abstimmungen/neu?typ=' . $kind);
        }

        $id = $this->events->create($data, (int) $this->auth->user()['id']);
        $this->applyOptions($id, $kind, $request);
        flash('success', 'Termin angelegt. Jetzt die Teilnehmenden wählen.');
        Redirect::to('/abstimmungen/detail?id=' . $id);
    }

    public function detail(Request $request): void
    {
        $this->requirePermission('events.manage');
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null) {
            flash('error', 'Termin nicht gefunden.');
            Redirect::to('/abstimmungen');
        }

        $participantIds = array_map(static fn (array $p): int => (int) $p['contact_id'], $event['participants']);

        $this->render('events/detail', [
            'event' => $event,
            'participantIds' => $participantIds,
            'contacts' => $this->contacts->search(['sort' => 'nachname', 'direction' => 'asc']),
            'categories' => $this->categories->all(),
            'voteBaseUrl' => url('/abstimmen'),
        ]);
    }

    public function updateDetails(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/abstimmungen');
        }

        $existing = $this->events->find($id);
        $data = $this->detailData($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/abstimmungen/detail?id=' . $id);
        }
        $this->events->updateDetails($id, $data);
        $this->applyOptions($id, (string) ($existing['kind'] ?? 'date_poll'), $request);
        flash('success', 'Termin gespeichert.');
        Redirect::to('/abstimmungen/detail?id=' . $id);
    }

    public function updateParticipants(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/abstimmungen');
        }

        $this->events->syncParticipants($id, (array) $request->input('contact_ids', []));
        $count = count($this->events->participantContactIds($id, 'all'));
        flash('success', $count > 0 && $this->auth->can('mail.send')
            ? 'Gespeichert. Jetzt kannst du unter „Teilnehmende erreichen" alle per Mail einladen.'
            : 'Auswahl gespeichert.');
        Redirect::to('/abstimmungen/detail?id=' . $id);
    }

    public function decide(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/abstimmungen');
        }

        $optionId = (int) $request->input('option_id');
        $this->events->setDecidedOption($id, $optionId > 0 ? $optionId : null);
        flash('success', $optionId > 0 ? 'Termin festgelegt.' : 'Festlegung aufgehoben.');
        Redirect::to('/abstimmungen/detail?id=' . $id);
    }

    public function setStatus(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $status = (string) $request->input('status');
        $this->events->setStatus($id, $status);
        $message = match ($status) {
            'archived' => 'Termin archiviert.',
            'closed' => 'Abstimmung geschlossen.',
            default => 'Termin wieder geöffnet.',
        };
        flash('success', $message);
        Redirect::to($status === 'archived' ? '/abstimmungen' : '/abstimmungen/detail?id=' . $id);
    }

    /**
     * Frist verlängern / neu setzen. Reaktiviert eine geschlossene Abstimmung
     * und schaltet Erinnerung + Ergebnisversand wieder scharf.
     */
    public function extendDeadline(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/abstimmungen');
        }

        $this->events->extendDeadline($id, trim((string) $request->input('closes_at')) ?: null);
        flash('success', 'Frist aktualisiert.');
        Redirect::to('/abstimmungen/detail?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $this->events->delete((int) $request->input('id'));
        flash('success', 'Termin gelöscht.');
        Redirect::to('/abstimmungen');
    }

    /**
     * „✉ an Teilnehmer": Empfängerkreis (alle / nur Zusagen / nur Offene) an den
     * Nachrichten-Flow übergeben. Der Platzhalter {Abstimmungslink} wird beim
     * Versand je Person durch den persönlichen Token-Link ersetzt.
     */
    public function messageParticipants(Request $request): void
    {
        $this->requirePermission('mail.send');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $event = $this->events->find($id);
        if ($event === null) {
            Redirect::to('/abstimmungen');
        }

        $filter = (string) $request->input('filter', 'all');
        $filter = in_array($filter, ['all', 'confirmed', 'pending'], true) ? $filter : 'all';
        $contactIds = $this->events->participantContactIds($id, $filter);

        if ($contactIds === []) {
            flash('error', 'In diesem Kreis ist niemand.');
            Redirect::to('/abstimmungen/detail?id=' . $id);
        }

        $isPoll = ($event['kind'] ?? '') === 'poll';
        $noun = $isPoll ? 'Abstimmung' : 'Terminabstimmung';
        $closesAt = trim((string) ($event['closes_at'] ?? ''));

        if ($event['status'] === 'decided') {
            $subject = ($isPoll ? 'Ergebnis' : 'Termin steht') . ': ' . $event['title'];
            $message = "{Anrede} {Vorname},\n\n"
                . 'die ' . $noun . ' „' . $event['title'] . "\" ist entschieden.\n"
                . 'Hier ist das Ergebnis: {Abstimmungslink}';
            if (!$isPoll && trim((string) ($event['ical_uid'] ?? '')) !== '') {
                $message .= "\n\nIn den Kalender: " . url('/abstimmungen/termin.ics') . '?k=' . $event['ical_uid'];
            }
            $message .= "\n\nDanke euch!";
        } else {
            $subject = 'Bitte abstimmen: ' . $event['title'];
            $lines = [
                '{Anrede} {Vorname},',
                '',
                'für „' . $event['title'] . '" brauchen wir noch deine Rückmeldung.',
                'Über deinen persönlichen Link kannst du direkt abstimmen:',
                '{Abstimmungslink}',
            ];
            if ($closesAt !== '') {
                $lines[] = '';
                $lines[] = 'Die ' . $noun . ' läuft noch bis ' . format_deadline($closesAt) . '.';
            }
            $lines[] = '';
            $lines[] = 'Vielen Dank!';
            $message = implode("\n", $lines);
        }

        $_SESSION['mail_draft'] = [
            'contact_ids' => $contactIds,
            'event_id' => $id,
            'subject' => $subject,
            'message' => $message,
            'salutation_mode' => 'auto',
        ];
        Redirect::to('/rundmail');
    }

    // --------------------------------------------------------- Abstimmen (Token)

    public function vote(Request $request): void
    {
        $token = trim((string) $request->input('token', ''));
        $participant = $token !== '' ? $this->events->participantByToken($token) : null;

        if ($participant === null) {
            render_error_page(404, 'Abstimmung nicht gefunden', 'Der Link ist ungültig oder der Termin wurde entfernt.');

            return;
        }

        $this->render('events/vote', [
            'p' => $participant,
            'ownName' => $this->currentPersonName(),
        ]);
    }

    public function submitVote(Request $request): void
    {
        Csrf::validate($request->input('_csrf'));
        $token = trim((string) $request->input('token', ''));
        $participant = $token !== '' ? $this->events->participantByToken($token) : null;

        if ($participant === null) {
            render_error_page(404, 'Abstimmung nicht gefunden', 'Der Link ist ungültig oder der Termin wurde entfernt.');

            return;
        }

        if (in_array($participant['status'], ['closed', 'archived'], true)) {
            flash('error', 'Diese Abstimmung ist abgeschlossen.');
            Redirect::to('/abstimmen?token=' . rawurlencode($token));
        }

        $answers = [];
        foreach ((array) $request->input('answer', []) as $optionId => $value) {
            if (in_array($value, ['yes', 'maybe', 'no'], true)) {
                $answers[(int) $optionId] = $value;
            }
        }

        $via = $this->auth->check() ? 'login' : 'token';
        $this->events->saveResponses((int) $participant['participant_id'], $answers, $via);
        $this->events->saveParticipantNote((int) $participant['participant_id'], (string) $request->input('note', ''));
        $this->events->logTokenHit((int) $participant['participant_id'], $this->sourceHash());

        flash('success', 'Danke, deine Rückmeldung ist gespeichert.');
        Redirect::to('/abstimmen?token=' . rawurlencode($token));
    }

    // ------------------------------------------------------------------ intern

    private function detailData(Request $request): array
    {
        return [
            'title' => trim((string) $request->input('title')),
            'description' => trim((string) $request->input('description')),
            'location' => trim((string) $request->input('location')),
            'time_note' => trim((string) $request->input('time_note')),
            'cost_note' => trim((string) $request->input('cost_note')),
            'bring_note' => trim((string) $request->input('bring_note')),
            'closes_at' => trim((string) $request->input('closes_at')),
            'result_recipients' => trim((string) $request->input('result_recipients')),
            'remind_days_before' => trim((string) $request->input('remind_days_before')),
        ];
    }

    /**
     * Antwortoptionen je nach Typ speichern:
     * - date_poll: mehrere Datum/Uhrzeit-Zeilen
     * - fixed_date: genau ein Datum, sofort als Ergebnis festgelegt
     * - poll: Freitext-Optionen
     */
    private function applyOptions(int $id, string $kind, Request $request): void
    {
        if ($kind === 'poll') {
            $labels = array_map('strval', (array) $request->input('option_label', []));
            $this->events->syncTextOptions($id, $labels);

            return;
        }

        $rows = $this->optionRows($request);
        if ($kind === 'fixed_date') {
            $rows = array_slice($rows, 0, 1);
        }
        $this->events->syncDateOptions($id, $rows);

        if ($kind === 'fixed_date') {
            $event = $this->events->find($id);
            $firstOption = $event['options'][0] ?? null;
            $this->events->setDecidedOption($id, $firstOption !== null ? (int) $firstOption['id'] : null);
        }
    }

    /**
     * Datumsoptionen aus dem Formular: option_date[] + option_time[] in Reihe.
     *
     * @return list<array{date: string, time: string}>
     */
    private function optionRows(Request $request): array
    {
        $dates = (array) $request->input('option_date', []);
        $times = (array) $request->input('option_time', []);
        $rows = [];
        foreach ($dates as $index => $date) {
            $date = trim((string) $date);
            if ($date === '') {
                continue;
            }
            $rows[] = ['date' => $date, 'time' => trim((string) ($times[$index] ?? ''))];
        }

        return $rows;
    }

    private function sourceHash(): string
    {
        return source_hash('termine-quelle');
    }

    private function currentPersonName(): string
    {
        $user = $this->auth->user();

        return trim((string) ($user['name'] ?? ''));
    }
}
