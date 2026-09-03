<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\EventRepository;
use App\Repositories\GroupRepository;
use App\Support\Redirect;

/**
 * Gruppen-Abstimmung (Stufe D/E). Jedes Gruppenmitglied darf für die eigene
 * Gruppe eine Meinungsabstimmung oder eine Terminfindung anlegen, sehen und
 * mit abstimmen. Nur für die Gruppe sichtbar; Admins mit `events.manage` sehen
 * sie zusätzlich in der Terminübersicht (mit Hinweis auf die Gruppe).
 * „Termin festlegen" und „Schließen" dürfen Ersteller:in, Gruppenleitung und
 * die globale Gruppen-Verwaltung.
 */
final class GroupPollController extends BaseController
{
    private const KINDS = ['poll', 'date_poll'];

    public function __construct(
        \App\Core\Auth $auth,
        private EventRepository $events,
        private GroupRepository $groups,
    ) {
        parent::__construct($auth);
    }

    public function list(Request $request): void
    {
        $this->requireAuth();
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null || !$this->mayView((int) $group['id'])) {
            flash('error', 'Diese Gruppe kannst du nicht sehen.');
            Redirect::to('/gruppen');
        }

        // Neue Gruppenmitglieder in laufende Abstimmungen nachtragen.
        $memberIds = $this->groups->memberContactIds((int) $group['id']);
        foreach ($this->events->pollsForGroup((int) $group['id']) as $poll) {
            if ($poll['status'] === 'open') {
                $this->events->addParticipantsFromContacts((int) $poll['id'], $memberIds);
            }
        }

        $this->render('groups/polls', [
            'group' => $group,
            'polls' => $this->events->pollsForGroup((int) $group['id']),
            'canCreate' => $this->mayCreate((int) $group['id']),
        ]);
    }

    public function createForm(Request $request): void
    {
        $this->requireAuth();
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null || !$this->mayCreate((int) $group['id'])) {
            flash('error', 'Für diese Gruppe kannst du keine Abstimmung anlegen.');
            Redirect::to('/gruppen');
        }

        $kind = (string) $request->input('typ', '');
        $this->render('groups/poll-form', [
            'group' => $group,
            'kind' => in_array($kind, self::KINDS, true) ? $kind : null,
            'today' => (new \DateTimeImmutable('now'))->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null || !$this->mayCreate((int) $group['id'])) {
            flash('error', 'Für diese Gruppe kannst du keine Abstimmung anlegen.');
            Redirect::to('/gruppen');
        }

        $kind = (string) $request->input('kind', 'poll');
        $kind = in_array($kind, self::KINDS, true) ? $kind : 'poll';
        $groupId = (int) $group['id'];
        $back = '/gruppen/abstimmung/neu?id=' . $groupId . '&typ=' . $kind;

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            flash('error', 'Bitte eine Frage bzw. einen Titel angeben.');
            Redirect::to($back);
        }

        if ($kind === 'poll') {
            $labels = array_values(array_filter(array_map(
                static fn ($v): string => trim((string) $v),
                (array) $request->input('option_label', [])
            ), static fn (string $v): bool => $v !== ''));
            if (count($labels) < 2) {
                flash('error', 'Bitte mindestens zwei Antwortmöglichkeiten angeben.');
                Redirect::to($back);
            }
        } else {
            $rows = $this->dateRows($request);
            if ($rows === []) {
                flash('error', 'Bitte mindestens einen Datumsvorschlag angeben.');
                Redirect::to($back);
            }
        }

        $eventId = $this->events->create([
            'title' => $title,
            'kind' => $kind,
            'group_id' => $groupId,
            'description' => trim((string) $request->input('description')),
            'location' => trim((string) $request->input('location')),
            'time_note' => '',
            'cost_note' => '',
            'bring_note' => '',
            'closes_at' => trim((string) $request->input('closes_at')),
            'result_recipients' => trim((string) $request->input('result_recipients')),
        ], (int) $this->auth->user()['id']);

        if ($kind === 'poll') {
            $this->events->syncTextOptions($eventId, $labels);
        } else {
            $this->events->syncDateOptions($eventId, $rows);
        }
        $this->events->addParticipantsFromContacts($eventId, $this->groups->memberContactIds($groupId));

        flash('success', 'Abstimmung angelegt.');
        Redirect::to('/gruppen/abstimmung?id=' . $eventId);
    }

    public function show(Request $request): void
    {
        $this->requireAuth();
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null || (int) ($event['group_id'] ?? 0) === 0) {
            flash('error', 'Abstimmung nicht gefunden.');
            Redirect::to('/gruppen');
        }
        if (!$this->mayView((int) $event['group_id'])) {
            flash('error', 'Diese Abstimmung ist nur für die Gruppe sichtbar.');
            Redirect::to('/gruppen');
        }

        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $mine = $this->events->participantForContact((int) $event['id'], $contactId);
        $myAnswers = [];
        if ($mine !== null) {
            foreach ($event['participants'] as $participant) {
                if ((int) $participant['id'] === (int) $mine['participant_id']) {
                    $myAnswers = $participant['answers'];
                }
            }
        }

        $canManage = $this->mayManagePoll($event);

        // Fertigen Ankündigungstext für „Nachricht an die Gruppe" bereitlegen.
        if ($canManage && $event['status'] === 'open') {
            $closesAt = trim((string) ($event['closes_at'] ?? ''));
            $lines = [
                'Hallo zusammen,',
                '',
                'in unserer Gruppe läuft eine Abstimmung: „' . $event['title'] . '".',
                'Bitte stimmt ab unter „Gruppen" → „Abstimmungen" (Login nötig).',
            ];
            if ($closesAt !== '') {
                $lines[] = '';
                $lines[] = 'Die Abstimmung endet am ' . format_deadline($closesAt) . '.';
            }
            $lines[] = '';
            $lines[] = 'Danke!';
            $_SESSION['group_mail_prefill'] = [
                'group_id' => (int) $event['group_id'],
                'subject' => 'Bitte abstimmen: ' . $event['title'],
                'message' => implode("\n", $lines),
            ];
        }

        $this->render('groups/poll', [
            'event' => $event,
            'myParticipantId' => $mine['participant_id'] ?? null,
            'myAnswers' => $myAnswers,
            'canManage' => $canManage,
        ]);
    }

    public function vote(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null || (int) ($event['group_id'] ?? 0) === 0 || !$this->mayView((int) $event['group_id'])) {
            Redirect::to('/gruppen');
        }
        if (in_array($event['status'], ['closed', 'decided', 'archived'], true)) {
            flash('error', 'Diese Abstimmung ist abgeschlossen.');
            Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
        }

        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $mine = $this->events->participantForContact((int) $event['id'], $contactId);
        if ($mine === null) {
            flash('error', 'Du gehörst nicht zum Teilnehmerkreis dieser Abstimmung.');
            Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
        }

        $answers = [];
        foreach ((array) $request->input('answer', []) as $optionId => $value) {
            if (in_array($value, ['yes', 'maybe', 'no'], true)) {
                $answers[(int) $optionId] = $value;
            }
        }
        $this->events->saveResponses((int) $mine['participant_id'], $answers, 'login');
        $this->events->saveParticipantNote((int) $mine['participant_id'], (string) $request->input('note', ''));

        flash('success', 'Danke, deine Rückmeldung ist gespeichert.');
        Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
    }

    public function close(Request $request): void
    {
        $event = $this->guardManagePoll($request);
        $this->events->setStatus((int) $event['id'], 'closed');
        flash('success', 'Abstimmung geschlossen.');
        Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
    }

    /** Terminfindung: einen Datumsvorschlag als Ergebnis festlegen (oder aufheben). */
    public function decide(Request $request): void
    {
        $event = $this->guardManagePoll($request);
        if ($event['kind'] !== 'date_poll') {
            Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
        }

        $optionId = (int) $request->input('option_id');
        $this->events->setDecidedOption((int) $event['id'], $optionId > 0 ? $optionId : null);
        flash('success', $optionId > 0 ? 'Termin festgelegt.' : 'Festlegung aufgehoben.');
        Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
    }

    // ------------------------------------------------------------------ intern

    private function guardManagePoll(Request $request): array
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null || (int) ($event['group_id'] ?? 0) === 0) {
            Redirect::to('/gruppen');
        }
        if (!$this->mayManagePoll($event)) {
            flash('error', 'Dafür fehlt dir die Berechtigung.');
            Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
        }

        return $event;
    }

    private function mayView(int $groupId): bool
    {
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        return $this->groups->isMember($groupId, $contactId)
            || $this->auth->can('groups.manage')
            || $this->auth->can('events.manage');
    }

    /** Abstimmung anlegen / verwalten: Mitglied, Gruppenleitung oder globale Verwaltung. */
    private function mayCreate(int $groupId): bool
    {
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        return $this->groups->isMember($groupId, $contactId)
            || $this->groups->isLead($groupId, $contactId)
            || $this->auth->can('groups.manage');
    }

    private function mayManagePoll(array $event): bool
    {
        $groupId = (int) ($event['group_id'] ?? 0);
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        return (int) $event['created_by'] === (int) ($this->auth->user()['id'] ?? 0)
            || $this->groups->isLead($groupId, $contactId)
            || $this->auth->can('groups.manage');
    }

    /** @return list<array{date: string, time: string}> */
    private function dateRows(Request $request): array
    {
        $dates = (array) $request->input('option_date', []);
        $times = (array) $request->input('option_time', []);
        $rows = [];
        foreach ($dates as $i => $date) {
            $date = trim((string) $date);
            if ($date !== '') {
                $rows[] = ['date' => $date, 'time' => trim((string) ($times[$i] ?? ''))];
            }
        }

        return $rows;
    }
}
