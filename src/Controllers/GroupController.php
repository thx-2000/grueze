<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\GroupRepository;
use App\Support\Redirect;

/**
 * Gruppen (Stufe B). Verwalten braucht `groups.manage` (Standard: Team + Admin).
 * „Meine Gruppen" (`/gruppen`) sieht jede:r eingeloggte Person mit verknüpftem
 * Kontakt: eigene Gruppen ansehen, offenen Gruppen selbst bei-/austreten.
 */
final class GroupController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private GroupRepository $groups,
        private ContactRepository $contacts,
    ) {
        parent::__construct($auth);
    }

    // --------------------------------------------------------- Meine Gruppen

    public function mine(): void
    {
        $this->requireAuth();
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);

        $this->render('groups/index', [
            'contactId' => $contactId,
            'myGroups' => $this->groups->forContact($contactId),
            'openGroups' => $contactId > 0 ? $this->groups->openGroupsToJoin($contactId) : [],
            'canManage' => $this->auth->can('groups.manage'),
        ]);
    }

    public function join(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $groupId = (int) $request->input('id');
        $group = $this->groups->find($groupId);

        if ($contactId <= 0 || $group === null || (int) $group['is_open'] !== 1) {
            flash('error', 'Dieser Gruppe kannst du nicht selbst beitreten.');
            Redirect::to('/gruppen');
        }

        $this->groups->addMember($groupId, $contactId);
        flash('success', 'Du bist jetzt in der Gruppe „' . $group['name'] . '".');
        Redirect::to('/gruppen');
    }

    public function leave(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $groupId = (int) $request->input('id');
        $group = $this->groups->find($groupId);

        if ($contactId <= 0 || $group === null) {
            Redirect::to('/gruppen');
        }
        if ((int) $group['is_open'] !== 1) {
            flash('error', 'Diese Gruppe verwaltet das Orga-Team. Bitte dort melden.');
            Redirect::to('/gruppen');
        }

        $this->groups->removeMember($groupId, $contactId);
        flash('success', 'Du bist aus der Gruppe „' . $group['name'] . '" ausgetreten.');
        Redirect::to('/gruppen');
    }

    // ------------------------------------------------------------- Verwaltung

    public function manage(): void
    {
        $this->requirePermission('groups.manage');
        $this->render('groups/manage', ['groups' => $this->groups->all()]);
    }

    public function detail(Request $request): void
    {
        $this->requirePermission('groups.manage');
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null) {
            flash('error', 'Gruppe nicht gefunden.');
            Redirect::to('/verwaltung/gruppen');
        }

        $memberIds = array_map(static fn (array $m): int => (int) $m['contact_id'], $group['members']);

        $this->render('groups/detail', [
            'group' => $group,
            'memberIds' => $memberIds,
            'contacts' => $this->contacts->search(['sort' => 'nachname', 'direction' => 'asc']),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('groups.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->groupData($request);
        if ($data['name'] === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/verwaltung/gruppen');
        }
        if ($this->groups->nameExists($data['name'])) {
            flash('error', 'Eine Gruppe mit diesem Namen gibt es schon.');
            Redirect::to('/verwaltung/gruppen');
        }

        $id = $this->groups->create($data, (int) ($this->auth->user()['id'] ?? 0) ?: null);
        flash('success', 'Gruppe angelegt. Jetzt Mitglieder wählen.');
        Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
    }

    public function updateGroup(Request $request): void
    {
        $this->requirePermission('groups.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->groups->find($id) === null) {
            Redirect::to('/verwaltung/gruppen');
        }

        $data = $this->groupData($request);
        if ($data['name'] === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
        }
        if ($this->groups->nameExists($data['name'], $id)) {
            flash('error', 'Eine Gruppe mit diesem Namen gibt es schon.');
            Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
        }

        $this->groups->update($id, $data);
        flash('success', 'Gruppe gespeichert.');
        Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
    }

    public function updateMembers(Request $request): void
    {
        $this->requirePermission('groups.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        if ($this->groups->find($id) === null) {
            Redirect::to('/verwaltung/gruppen');
        }

        $this->groups->syncMembers($id, (array) $request->input('contact_ids', []));
        flash('success', 'Mitgliederkreis aktualisiert.');
        Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
    }

    public function deleteGroup(Request $request): void
    {
        $this->requirePermission('groups.manage');
        Csrf::validate($request->input('_csrf'));
        $this->groups->delete((int) $request->input('id'));
        flash('success', 'Gruppe gelöscht.');
        Redirect::to('/verwaltung/gruppen');
    }

    private function groupData(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name')),
            'description' => trim((string) $request->input('description')),
            'is_open' => $request->input('is_open') === '1',
        ];
    }
}
