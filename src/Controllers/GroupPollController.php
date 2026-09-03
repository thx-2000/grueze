<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\EventRepository;
use App\Repositories\GroupRepository;
use App\Support\Redirect;

/**
 * Gruppen-Abstimmung (Stufe D). Jedes Gruppenmitglied darf für die eigene
 * Gruppe eine Abstimmung anlegen, sehen und mit abstimmen. Die Abstimmung ist
 * nur für die Gruppe sichtbar; Admins mit `events.manage` sehen sie zusätzlich
 * in der Terminübersicht (mit Hinweis, dass sie zur Gruppe gehört).
 */
final class GroupPollController extends BaseController
{
    // Stufe D: nur Meinungsabstimmungen (kind 'poll'). Terminfindung für Gruppen
    // kann später über dieselbe events-Tabelle folgen.

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

        $this->render('groups/poll-form', ['group' => $group]);
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

        $title = trim((string) $request->input('title'));
        $labels = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            (array) $request->input('option_label', [])
        ), static fn (string $v): bool => $v !== ''));

        if ($title === '') {
            flash('error', 'Bitte eine Frage angeben.');
            Redirect::to('/gruppen/abstimmung/neu?id=' . (int) $group['id']);
        }
        if (count($labels) < 2) {
            flash('error', 'Bitte mindestens zwei Antwortmöglichkeiten angeben.');
            Redirect::to('/gruppen/abstimmung/neu?id=' . (int) $group['id']);
        }

        $eventId = $this->events->create([
            'title' => $title,
            'kind' => 'poll',
            'group_id' => (int) $group['id'],
            'description' => trim((string) $request->input('description')),
            'location' => '',
            'time_note' => '',
            'cost_note' => '',
            'bring_note' => '',
            'closes_at' => trim((string) $request->input('closes_at')),
            'result_recipients' => trim((string) $request->input('result_recipients')),
        ], (int) $this->auth->user()['id']);

        $this->events->syncTextOptions($eventId, $labels);
        $this->events->addParticipantsFromContacts($eventId, $this->groups->memberContactIds((int) $group['id']));

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

        $this->render('groups/poll', [
            'event' => $event,
            'myParticipantId' => $mine['participant_id'] ?? null,
            'myAnswers' => $myAnswers,
            'canManage' => $this->mayCreate((int) $event['group_id']) || (int) $event['created_by'] === (int) $this->auth->user()['id'],
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

        flash('success', 'Danke, deine Rückmeldung ist gespeichert.');
        Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
    }

    public function close(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $event = $this->events->find((int) $request->input('id'));
        if ($event === null || (int) ($event['group_id'] ?? 0) === 0) {
            Redirect::to('/gruppen');
        }
        $isCreator = (int) $event['created_by'] === (int) $this->auth->user()['id'];
        if (!$isCreator && !$this->mayCreate((int) $event['group_id'])) {
            flash('error', 'Dafür fehlt dir die Berechtigung.');
            Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
        }

        $this->events->setStatus((int) $event['id'], 'closed');
        flash('success', 'Abstimmung geschlossen.');
        Redirect::to('/gruppen/abstimmung?id=' . (int) $event['id']);
    }

    // ------------------------------------------------------------------ intern

    private function mayView(int $groupId): bool
    {
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        return $this->groups->isMember($groupId, $contactId)
            || $this->auth->can('groups.manage')
            || $this->auth->can('events.manage');
    }

    private function mayCreate(int $groupId): bool
    {
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        return $this->groups->isMember($groupId, $contactId) || $this->auth->can('groups.manage');
    }
}
