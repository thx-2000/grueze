<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\EventRepository;
use App\Repositories\LogRepository;
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
    ) {
        parent::__construct($auth);
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

    public function createForm(): void
    {
        $this->requirePermission('events.manage');
        $this->render('events/form', ['event' => null]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->detailData($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/termine/neu');
        }

        $id = $this->events->create($data, (int) $this->auth->user()['id']);
        $this->events->syncDateOptions($id, $this->optionRows($request));
        flash('success', 'Termin angelegt. Jetzt Teilnehmerkreis wählen.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function detail(Request $request): void
    {
        $this->requirePermission('events.manage');
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null) {
            flash('error', 'Termin nicht gefunden.');
            Redirect::to('/termine');
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
            Redirect::to('/termine');
        }

        $data = $this->detailData($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/termine/detail?id=' . $id);
        }
        $this->events->updateDetails($id, $data);
        $this->events->syncDateOptions($id, $this->optionRows($request));
        flash('success', 'Termin gespeichert.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function updateParticipants(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/termine');
        }

        $this->events->syncParticipants($id, (array) $request->input('contact_ids', []));
        flash('success', 'Teilnehmerkreis aktualisiert.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function decide(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->events->find($id) === null) {
            Redirect::to('/termine');
        }

        $optionId = (int) $request->input('option_id');
        $this->events->setDecidedOption($id, $optionId > 0 ? $optionId : null);
        flash('success', $optionId > 0 ? 'Termin festgelegt.' : 'Festlegung aufgehoben.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function setStatus(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $status = (string) $request->input('status');
        $this->events->setStatus($id, $status);
        flash('success', $status === 'archived' ? 'Termin archiviert.' : 'Termin wieder geöffnet.');
        Redirect::to($status === 'archived' ? '/termine' : '/termine/detail?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('events.manage');
        Csrf::validate($request->input('_csrf'));
        $this->events->delete((int) $request->input('id'));
        flash('success', 'Termin gelöscht.');
        Redirect::to('/termine');
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

        if ($participant['status'] === 'archived') {
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
        ];
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
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return hash('sha256', $ip . '|grueze-termine-quelle');
    }

    private function currentPersonName(): string
    {
        $user = $this->auth->user();

        return trim((string) ($user['name'] ?? ''));
    }
}
