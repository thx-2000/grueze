<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\LogRepository;
use App\Repositories\MemorialRepository;
use App\Repositories\RosterRepository;
use App\Services\UploadService;
use App\Support\Redirect;

/**
 * „Weitere Personen" (Instanz-Label z. B. „Lehrkräfte", siehe roster_label()):
 * eine zusätzliche Personenliste außerhalb des Adressbuchs, komplett getrennt
 * von Kontakten/Gruppen. Ein Todesdatum spiegelt sich automatisch als Eintrag
 * auf der Gedenkseite (die Person bleibt hier trotzdem stehen, nur markiert).
 *
 * Ansehen ist für jede angemeldete Person offen; pflegen braucht
 * `roster.manage` (Standard: orga + admin).
 */
final class RosterController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private RosterRepository $roster,
        private MemorialRepository $memorials,
        private UploadService $uploads,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('roster/index', [
            'people' => $this->roster->all(),
            'canManage' => can('roster.manage'),
        ]);
    }

    public function createForm(): void
    {
        $this->requirePermission('roster.manage');
        $this->render('roster/form', [
            'person' => null,
            'roleSuggestions' => $this->roster->roleSuggestions(),
        ]);
    }

    public function editForm(Request $request): void
    {
        $this->requirePermission('roster.manage');
        $person = $this->roster->find((int) $request->input('id'));
        if ($person === null) {
            flash('error', 'Eintrag nicht gefunden.');
            Redirect::to('/weitere-personen');
        }
        $this->render('roster/form', [
            'person' => $person,
            'roleSuggestions' => $this->roster->roleSuggestions(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('roster.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitize($request);
        if ($data['name'] === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/weitere-personen/neu');
        }

        try {
            $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            Redirect::to('/weitere-personen/neu');
        }

        $id = $this->roster->create($data, (int) $this->userId());
        $this->syncMemorial($id);
        $this->logs->addAudit((int) $this->userId(), null, 'created', roster_label() . '-Eintrag angelegt: „' . $data['name'] . '".');
        flash('success', 'Eintrag hinzugefügt.');
        Redirect::to('/weitere-personen');
    }

    public function update(Request $request): void
    {
        $this->requirePermission('roster.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $existing = $this->roster->find($id);
        if ($existing === null) {
            Redirect::to('/weitere-personen');
        }

        $data = $this->sanitize($request);
        if ($data['name'] === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/weitere-personen/bearbeiten?id=' . $id);
        }

        if ($request->input('photo_remove')) {
            $data['photo_path'] = null;
        } else {
            try {
                $newPhoto = $this->uploads->storePhoto($request->file('photo'), $existing['photo_path']);
                if ($newPhoto !== null) {
                    $data['photo_path'] = $newPhoto;
                }
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                Redirect::to('/weitere-personen/bearbeiten?id=' . $id);
            }
        }

        $this->roster->update($id, $data);
        $this->syncMemorial($id);
        $this->logs->addAudit((int) $this->userId(), null, 'updated', roster_label() . '-Eintrag geändert: „' . $data['name'] . '".');
        flash('success', 'Eintrag gespeichert.');
        Redirect::to('/weitere-personen/bearbeiten?id=' . $id);
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('roster.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $person = $this->roster->find($id);
        if ($person !== null) {
            $this->memorials->deleteForRoster($id);
            $this->roster->delete($id);
            $this->logs->addAudit((int) $this->userId(), null, 'deleted', roster_label() . '-Eintrag entfernt: „' . $person['name'] . '".');
            flash('success', '„' . $person['name'] . '" wurde entfernt.');
        }
        Redirect::to('/weitere-personen');
    }

    // --------------------------------------------------------------------- intern

    /**
     * Nach jedem Speichern die Gedenkseite abgleichen: Todesdatum gesetzt →
     * Eintrag dort anlegen/aktualisieren, sonst einen bestehenden Spiegel
     * wieder entfernen (die Person selbst bleibt hier unberührt stehen).
     */
    private function syncMemorial(int $id): void
    {
        $person = $this->roster->find($id);
        if ($person === null) {
            return;
        }
        if ($person['died_on'] !== null) {
            $this->memorials->upsertFromRoster($person, (int) $this->userId());
        } else {
            $this->memorials->deleteForRoster($id);
        }
    }

    private function userId(): ?int
    {
        return (int) ($this->auth->user()['id'] ?? 0) ?: null;
    }

    /** @return array<string,mixed> */
    private function sanitize(Request $request): array
    {
        return [
            'name' => mb_substr(trim((string) $request->input('name')), 0, 190),
            'role_label' => mb_substr(trim((string) $request->input('role_label')), 0, 160),
            'email' => mb_substr(trim((string) $request->input('email')), 0, 190),
            'mobile' => mb_substr(trim((string) $request->input('mobile')), 0, 60),
            'born_on' => trim((string) $request->input('born_on')),
            'died_on' => trim((string) $request->input('died_on')),
            'strasse' => mb_substr(trim((string) $request->input('strasse')), 0, 190),
            'plz' => mb_substr(trim((string) $request->input('plz')), 0, 20),
            'ort' => mb_substr(trim((string) $request->input('ort')), 0, 120),
            'land' => mb_substr(trim((string) $request->input('land')), 0, 80),
            'note' => mb_substr(trim((string) $request->input('note')), 0, 500),
        ];
    }
}
