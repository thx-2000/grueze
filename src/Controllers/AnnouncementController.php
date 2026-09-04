<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\AnnouncementRepository;
use App\Repositories\ContactRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\EventRepository;
use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Repositories\TagRepository;
use App\Support\Redirect;

/**
 * Termine: reine Ankündigungsseite vom Orga-Team (Titel, Zeitraum, Freitext-
 * Info, Links). Anders als bei Abstimmungen (`events`/EventController) keine
 * Zu-/Absage, kein Teilnehmerkreis – nur Information.
 *
 * Verwalten (anlegen/bearbeiten/löschen) braucht `announcements.manage`.
 * Ansehen ist für jede angemeldete Person offen – standardmäßig alle,
 * optional pro Ankündigung auf bestimmte Personen/Gruppen/Tags eingeschränkt
 * (`announcement_audience`). Verwaltung sieht dabei immer alles, mit Hinweis
 * auf die eigentliche Einschränkung.
 */
final class AnnouncementController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private AnnouncementRepository $announcements,
        private ContactRepository $contacts,
        private GroupRepository $groups,
        private TagRepository $tags,
        private DocumentRepository $documents,
        private EventRepository $events,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    // ------------------------------------------------------------- Übersicht

    public function index(Request $request): void
    {
        $this->requireAuth();
        $past = (string) $request->input('archiv', '') === '1';
        $canManage = $this->canManage();

        $visible = $canManage
            ? $this->announcements->all($past)
            : array_values(array_filter(
                $this->announcements->all($past),
                fn (array $a): bool => $this->canView($a)
            ));

        $this->render('announcements/index', [
            'announcements' => $visible,
            'showPast' => $past,
            'canManage' => $canManage,
        ]);
    }

    public function show(Request $request): void
    {
        $this->requireAuth();
        $announcement = $this->announcements->find((int) $request->input('id'));
        if ($announcement === null || !$this->canView($announcement)) {
            flash('error', 'Ankündigung nicht gefunden.');
            Redirect::to('/termine');
        }

        $canManage = $this->canManage();
        $this->render('announcements/show', [
            'announcement' => $announcement,
            'canManage' => $canManage,
            'audienceLabels' => ($canManage && $announcement['audience_mode'] === 'restricted')
                ? $this->announcements->audienceLabels((int) $announcement['id'])
                : [],
            'audienceRows' => $canManage ? $this->announcements->audienceFor((int) $announcement['id']) : [],
            'pickerData' => $canManage ? $this->pickerData() : null,
        ]);
    }

    // ------------------------------------------------------------ Verwaltung

    public function createForm(): void
    {
        $this->requireManage();
        $this->render('announcements/form', [
            'announcement' => null,
            'pickerData' => $this->pickerData(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitize($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/termine/neu');
        }

        $id = $this->announcements->create($data, $this->userId());
        $this->announcements->replaceAudience($id, $this->audienceRows($request));
        $this->announcements->replaceLinks($id, $this->linkRows($request));
        $this->logs->addAudit((int) $this->userId(), null, 'created', 'Ankündigung angelegt: „' . $data['title'] . '".');
        flash('success', 'Ankündigung veröffentlicht.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function update(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        if ($this->announcements->find($id) === null) {
            Redirect::to('/termine');
        }

        $data = $this->sanitize($request);
        if ($data['title'] === '') {
            flash('error', 'Bitte einen Titel angeben.');
            Redirect::to('/termine/detail?id=' . $id);
        }

        $this->announcements->update($id, $data);
        $this->announcements->replaceAudience($id, $this->audienceRows($request));
        $this->announcements->replaceLinks($id, $this->linkRows($request));
        flash('success', 'Ankündigung gespeichert.');
        Redirect::to('/termine/detail?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->requireManage();
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $announcement = $this->announcements->find($id);
        if ($announcement !== null) {
            $this->announcements->delete($id);
            $this->logs->addAudit((int) $this->userId(), null, 'deleted', 'Ankündigung gelöscht: „' . $announcement['title'] . '".');
            flash('success', '„' . $announcement['title'] . '" wurde gelöscht.');
        }
        Redirect::to('/termine');
    }

    // --------------------------------------------------------------- intern

    private function userId(): ?int
    {
        return (int) ($this->auth->user()['id'] ?? 0) ?: null;
    }

    private function contactId(): int
    {
        return (int) ($this->auth->user()['contact_id'] ?? 0);
    }

    private function canManage(): bool
    {
        return $this->auth->can('announcements.manage');
    }

    private function requireManage(): void
    {
        $this->requireAuth();
        if (!$this->canManage()) {
            throw new \RuntimeException('Zum Verwalten von Terminen fehlt die Berechtigung.');
        }
    }

    /** @param array<string,mixed> $announcement */
    private function canView(array $announcement): bool
    {
        if ($this->canManage()) {
            return true;
        }
        $cid = $this->contactId();
        $groupIds = $cid > 0
            ? array_map(static fn (array $g): int => (int) $g['id'], $this->groups->forContact($cid))
            : [];
        $tagIds = $cid > 0 ? $this->tags->tagIdsForContact($cid) : [];

        return $this->announcements->isVisibleTo($announcement, $cid, $groupIds, $tagIds);
    }

    /** @return array<string,mixed> */
    private function sanitize(Request $request): array
    {
        $startsAt = trim((string) $request->input('starts_at'));
        $endsAt = trim((string) $request->input('ends_at'));

        return [
            'title' => mb_substr(trim((string) $request->input('title')), 0, 190),
            'info' => mb_substr(trim((string) $request->input('info')), 0, 8000),
            'location' => mb_substr(trim((string) $request->input('location')), 0, 190),
            'starts_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsAt) ? $startsAt : '',
            'ends_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt) ? $endsAt : '',
            // Kein Radio nötig: leere Auswahl = für alle sichtbar.
            'audience_mode' => $this->audienceRows($request) === [] ? 'all' : 'restricted',
        ];
    }

    /** @return list<array{kind:string,ref_id:int}> */
    private function audienceRows(Request $request): array
    {
        $rows = [];
        foreach (array_map('intval', (array) $request->input('audience_contacts', [])) as $id) {
            if ($id > 0) {
                $rows[] = ['kind' => 'contact', 'ref_id' => $id];
            }
        }
        foreach (array_map('intval', (array) $request->input('audience_groups', [])) as $id) {
            if ($id > 0) {
                $rows[] = ['kind' => 'group', 'ref_id' => $id];
            }
        }
        foreach (array_map('intval', (array) $request->input('audience_tags', [])) as $id) {
            if ($id > 0) {
                $rows[] = ['kind' => 'tag', 'ref_id' => $id];
            }
        }

        return $rows;
    }

    /** @return list<array{label:string,kind:string,url:string}> */
    private function linkRows(Request $request): array
    {
        $kinds = (array) $request->input('link_kind', []);
        $labels = (array) $request->input('link_label', []);
        $urls = (array) $request->input('link_url', []);
        $documentIds = (array) $request->input('link_document_id', []);
        $eventIds = (array) $request->input('link_event_id', []);

        $rows = [];
        foreach ($kinds as $i => $kind) {
            $kind = in_array($kind, ['extern', 'dokument', 'abstimmung'], true) ? $kind : 'extern';
            $label = mb_substr(trim((string) ($labels[$i] ?? '')), 0, 190);

            if ($kind === 'extern') {
                $url = trim((string) ($urls[$i] ?? ''));
                if ($url === '') {
                    continue;
                }
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                $rows[] = ['label' => $label !== '' ? $label : $url, 'kind' => 'extern', 'url' => mb_substr($url, 0, 500)];

                continue;
            }

            if ($kind === 'dokument') {
                $docId = (int) ($documentIds[$i] ?? 0);
                $doc = $docId > 0 ? $this->documents->find($docId) : null;
                if ($doc === null) {
                    continue;
                }
                $rows[] = [
                    'label' => $label !== '' ? $label : (string) $doc['title'],
                    'kind' => 'dokument',
                    'url' => url('/dokumente/datei?id=' . $docId),
                ];

                continue;
            }

            // Abstimmung: Gruppen-Abstimmung verlinkt auf die (für die
            // Gruppe erreichbare) Gruppen-Seite, sonst auf die Verwaltung.
            $eventId = (int) ($eventIds[$i] ?? 0);
            $event = $eventId > 0 ? $this->events->find($eventId) : null;
            if ($event === null) {
                continue;
            }
            $target = (int) ($event['group_id'] ?? 0) > 0
                ? '/gruppen/abstimmung?id=' . $eventId
                : '/abstimmungen/detail?id=' . $eventId;
            $rows[] = [
                'label' => $label !== '' ? $label : (string) $event['title'],
                'kind' => 'abstimmung',
                'url' => url($target),
            ];
        }

        return $rows;
    }

    /**
     * Auswahllisten für die Formulare: Personen/Gruppen/Tags (Sichtbarkeit)
     * sowie Dokumente/Abstimmungen (Links).
     *
     * @return array<string,mixed>
     */
    private function pickerData(): array
    {
        $pollEvents = array_values(array_filter(
            $this->events->all(false),
            static fn (array $e): bool => in_array($e['kind'], ['poll', 'date_poll'], true)
        ));

        return [
            'contacts' => $this->contacts->search(['sort' => 'nachname', 'direction' => 'asc']),
            'groups' => $this->groups->all(),
            'tags' => $this->tags->all(),
            'documents' => $this->documents->allWithFolder(),
            'pollEvents' => $pollEvents,
        ];
    }
}
