<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\MemorialRepository;
use App\Repositories\UserRepository;
use App\Services\UploadService;
use App\Support\Redirect;

/**
 * „In Memoriam" – eine ruhige Gedenkseite. Zwei Wege, einen Eintrag anzulegen:
 *  - aus einem Kontakt (Kontakt-Detailseite → „Als verstorben eintragen"):
 *    setzt contacts.deceased_at und legt die memorials-Zeile an;
 *  - als freier Eintrag (hier, für Personen, die nie im Adressbuch standen).
 *
 * Ansehen ist für jede angemeldete Person offen; pflegen braucht
 * `memorials.manage` (Standard: orga + admin).
 */
final class MemorialController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private MemorialRepository $memorials,
        private ContactRepository $contacts,
        private UserRepository $users,
        private UploadService $uploads,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('memorial/index', [
            'groups' => $this->memorials->grouped(),
            'canManage' => can('memorials.manage'),
        ]);
    }

    public function createForm(): void
    {
        $this->requirePermission('memorials.manage');
        $this->render('memorial/form', [
            'entry' => null,
            'roleSuggestions' => $this->memorials->roleSuggestions(),
        ]);
    }

    public function editForm(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        $entry = $this->memorials->find((int) $request->input('id'));
        if ($entry === null) {
            flash('error', 'Eintrag nicht gefunden.');
            Redirect::to('/memoriam');
        }
        $this->render('memorial/form', [
            'entry' => $entry,
            'roleSuggestions' => $this->memorials->roleSuggestions(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitize($request);
        if ($data['display_name'] === '') {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/memoriam/neu');
        }

        try {
            $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            Redirect::to('/memoriam/neu');
        }

        $this->memorials->createFree($data, (int) $this->userId());
        $this->logs->addAudit((int) $this->userId(), null, 'created', 'Gedenk-Eintrag angelegt: „' . $data['display_name'] . '".');
        flash('success', 'Eintrag hinzugefügt.');
        Redirect::to('/memoriam');
    }

    public function update(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $entry = $this->memorials->find($id);
        if ($entry === null) {
            Redirect::to('/memoriam');
        }

        $data = $this->sanitize($request);
        if ($data['display_name'] === '' && $entry['contact_id'] === null) {
            flash('error', 'Bitte einen Namen angeben.');
            Redirect::to('/memoriam/bearbeiten?id=' . $id);
        }

        // Bei verknüpften Einträgen kommen Name/Foto aus dem Kontakt – nicht überschreiben.
        if ($entry['contact_id'] !== null) {
            unset($data['display_name'], $data['photo_path']);
        } else {
            try {
                $newPhoto = $this->uploads->storePhoto($request->file('photo'), $entry['photo_path']);
                if ($newPhoto !== null) {
                    $data['photo_path'] = $newPhoto;
                }
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                Redirect::to('/memoriam/bearbeiten?id=' . $id);
            }
        }

        $this->memorials->update($id, $data);
        flash('success', 'Eintrag gespeichert.');
        Redirect::to('/memoriam');
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $entry = $this->memorials->find($id);
        if ($entry !== null) {
            // Verknüpfter Eintrag: Kontakt zugleich wieder in den aktiven Bestand.
            if ($entry['contact_id'] !== null) {
                $this->contacts->unmarkDeceased((int) $entry['contact_id']);
            }
            $this->memorials->delete($id);
            $this->logs->addAudit((int) $this->userId(), $entry['contact_id'], 'deleted', 'Gedenk-Eintrag entfernt: „' . $entry['name'] . '".');
            flash('success', '„' . $entry['name'] . '" wurde von der Gedenkseite entfernt.');
        }
        Redirect::to('/memoriam');
    }

    // ------------------------------------------------- von der Kontakt-Detailseite

    /** Kontakt als verstorben eintragen. */
    public function markDeceased(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $contact = $this->contacts->find($id);
        if (!$contact) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $diedOn = trim((string) $request->input('died_on'));
        $diedOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $diedOn) ? $diedOn : null;
        $note = (string) $request->input('note');
        $userId = (int) $this->userId();
        $name = trim($contact['vorname'] . ' ' . $contact['nachname']);

        $this->contacts->markDeceased($id, $diedOn, $userId);
        $this->memorials->upsertFromContact($contact, $diedOn, $note, $userId);
        $this->users->deactivateByContactId($id);

        $this->logs->addAudit($userId, $id, 'updated', 'Als verstorben eingetragen: ' . $name . '.');
        flash('success', $name . ' ist jetzt auf der Gedenkseite und aus dem aktiven Adressbuch genommen.');
        Redirect::to('/memoriam');
    }

    /** „Doch nicht verstorben": Kontakt zurück in den aktiven Bestand. */
    public function revive(Request $request): void
    {
        $this->requirePermission('memorials.manage');
        Csrf::validate($request->input('_csrf'));

        $id = (int) $request->input('id');
        $contact = $this->contacts->find($id);
        $name = $contact ? trim($contact['vorname'] . ' ' . $contact['nachname']) : 'Kontakt';

        $this->contacts->unmarkDeceased($id);
        $this->memorials->deleteForContact($id);
        $this->logs->addAudit((int) $this->userId(), $id, 'updated', 'Verstorben-Eintrag zurückgenommen: ' . $name . '.');
        flash('success', $name . ' ist wieder im aktiven Adressbuch. Ein verknüpfter Login bleibt deaktiviert.');
        Redirect::to('/contacts/edit?id=' . $id);
    }

    // --------------------------------------------------------------------- intern

    private function userId(): ?int
    {
        return (int) ($this->auth->user()['id'] ?? 0) ?: null;
    }

    /** @return array<string,mixed> */
    private function sanitize(Request $request): array
    {
        return [
            'display_name' => mb_substr(trim((string) $request->input('display_name')), 0, 190),
            'role_label' => mb_substr(trim((string) $request->input('role_label')), 0, 120),
            'born_year' => (int) $request->input('born_year') ?: null,
            'died_year' => (int) $request->input('died_year') ?: null,
            'died_on' => trim((string) $request->input('died_on')),
            'note' => mb_substr(trim((string) $request->input('note')), 0, 500),
        ];
    }
}
