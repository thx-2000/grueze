<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\GroupRepository;
use App\Services\GroupMailService;
use App\Support\Redirect;

/**
 * Gruppen (Stufe B/C). Verwalten braucht `groups.manage` (Standard: Team + Admin).
 * „Meine Gruppen" (`/gruppen`) sieht jede:r eingeloggte Person mit verknüpftem
 * Kontakt: eigene Gruppen ansehen, offenen Gruppen selbst bei-/austreten, der
 * eigenen Gruppe eine Nachricht schicken.
 */
final class GroupController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private GroupRepository $groups,
        private ContactRepository $contacts,
        private GroupMailService $groupMail,
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

    // ------------------------------------------------------------ Gruppen-Mail

    public function composeMail(Request $request): void
    {
        $this->requireAuth();
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null || !$this->canSendMailToGroup($group)) {
            flash('error', 'Für diese Gruppe kannst du keine Nachricht schreiben.');
            Redirect::to('/gruppen');
        }

        $userId = (int) ($this->auth->user()['id'] ?? 0);
        $members = $group['members'];
        $withEmail = array_filter($members, static fn (array $m): bool => trim((string) ($m['email'] ?? '')) !== '');

        $this->render('groups/compose', [
            'group' => $group,
            'recipientCount' => count($withEmail),
            'noEmailCount' => count($members) - count($withEmail),
            'sentToday' => $this->auth->isAdmin() ? 0 : $this->groupMail->sentTodayBy($userId),
            'softLimit' => $this->groupMail->softLimit(),
            'isAdmin' => $this->auth->isAdmin(),
        ]);
    }

    public function sendMail(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        $group = $this->groups->find((int) $request->input('id'));
        if ($group === null || !$this->canSendMailToGroup($group)) {
            flash('error', 'Für diese Gruppe kannst du keine Nachricht schreiben.');
            Redirect::to('/gruppen');
        }

        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));
        if ($subject === '' || $message === '') {
            flash('error', 'Bitte Betreff und Nachricht ausfüllen.');
            Redirect::to('/gruppen/nachricht?id=' . (int) $group['id']);
        }

        $members = $group['members'];
        $recipientCount = count(array_filter(
            $members,
            static fn (array $m): bool => trim((string) ($m['email'] ?? '')) !== ''
        ));
        if ($recipientCount === 0) {
            flash('error', 'In dieser Gruppe hat niemand eine Mailadresse hinterlegt.');
            Redirect::to('/gruppen/nachricht?id=' . (int) $group['id']);
        }
        if ($recipientCount > $this->groupMail->maxRecipients()) {
            flash('error', 'Diese Gruppe ist zu groß für den einfachen Versand. Bitte über „Nachrichten" senden.');
            Redirect::to('/gruppen');
        }

        $result = $this->groupMail->send(
            $group,
            (array) $this->auth->user(),
            $subject,
            $message,
            $this->auth->isAdmin()
        );

        $msg = 'Nachricht an ' . $result['sent'] . ' ' . ($result['sent'] === 1 ? 'Person' : 'Personen') . ' verschickt.';
        if ($result['failed'] > 0) {
            $msg .= ' Bei ' . $result['failed'] . ' gab es einen Fehler – das Admin-Team wurde informiert.';
        }
        if ($result['skipped'] > 0) {
            $msg .= ' ' . $result['skipped'] . ' ohne Mailadresse übersprungen.';
        }
        if ($result['softLimitHit']) {
            $msg .= ' Hinweis: Du hast heute schon mehrere Gruppen-Mails geschickt – das Orga-Team sieht das.';
        }
        flash($result['failed'] > 0 ? 'error' : 'success', $msg);
        Redirect::to('/gruppen');
    }

    public function toggleMailLock(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));
        if (!$this->auth->can('groups.manage') && !$this->auth->isAdmin()) {
            flash('error', 'Dafür fehlt dir die Berechtigung.');
            Redirect::to('/gruppen');
        }
        $id = (int) $request->input('id');
        $group = $this->groups->find($id);
        if ($group === null) {
            Redirect::to('/verwaltung/gruppen');
        }

        $lock = $request->input('lock') === '1';
        $this->groups->setMailLocked($id, $lock);
        flash('success', $lock ? 'Gruppen-Versand gesperrt.' : 'Gruppen-Versand wieder freigegeben.');
        Redirect::to('/verwaltung/gruppen/detail?id=' . $id);
    }

    /** Darf die aktuelle Person dieser Gruppe eine Nachricht schicken? */
    private function canSendMailToGroup(array $group): bool
    {
        $contactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $isMember = $this->groups->isMember((int) $group['id'], $contactId);
        $isManager = $this->auth->can('groups.manage');

        if (!$isMember && !$isManager) {
            return false;
        }
        if ((int) ($group['mail_locked'] ?? 0) === 1 && !$this->auth->isAdmin()) {
            return false;
        }

        return true;
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
