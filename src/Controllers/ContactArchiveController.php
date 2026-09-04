<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\UserRepository;
use App\Support\ContactFieldRedactor;
use App\Support\Redirect;

/**
 * Kontakte aus dem aktiven Bestand nehmen und zurückholen: Archiv (dauerhaft,
 * jederzeit zurückholbar) bzw. Papierkorb (nach 30 Tagen endgültig weg), plus
 * der Dubletten-Finder und das Zusammenführen doppelt angelegter Kontakte.
 */
final class ContactArchiveController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private UserRepository $users,
        private LogRepository $logs,
    ) {
        parent::__construct($auth);
    }

    /**
     * Kontakt aus dem aktiven Bestand nehmen – wahlweise ins Archiv (bleibt
     * dauerhaft, jederzeit zurückholbar) oder in den Papierkorb (nach 30 Tagen
     * endgültig weg). Ein verknüpfter Login wird deaktiviert.
     */
    public function retire(Request $request): void
    {
        $this->requirePermission('contacts.delete');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $mode = $request->input('mode') === 'archive' ? 'archive' : 'trash';
        $contact = $this->contacts->find($id);
        if (!$contact) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $this->users->deactivateByContactId($id);
        $name = trim($contact['vorname'] . ' ' . $contact['nachname']);
        $userId = (int) $this->auth->user()['id'];

        if ($mode === 'archive') {
            $this->contacts->archive($id, $userId);
            $this->logs->addAudit($userId, $id, 'updated', 'Kontakt ins Archiv gelegt: ' . $name . '.');
            flash('success', $name . ' liegt jetzt im Archiv. Du kannst den Kontakt jederzeit zurückholen.');
        } else {
            $this->contacts->trash($id, $userId);
            $this->logs->addAudit($userId, $id, 'deleted', 'Kontakt in den Papierkorb gelegt: ' . $name . '.');
            flash('success', $name . ' liegt jetzt im Papierkorb und wird in ' . ContactRepository::TRASH_DAYS . ' Tagen endgültig gelöscht.');
        }

        Redirect::to('/kontakte');
    }

    /** Archiv & Papierkorb – Übersicht mit Zurückholen / endgültig löschen. */
    public function retiredList(): void
    {
        $this->requirePermission('contacts.delete');
        $lists = $this->contacts->retired();

        $this->render('contacts/retired', [
            'archived' => $lists['archived'],
            'trashed' => $lists['trashed'],
            'trashDays' => ContactRepository::TRASH_DAYS,
        ]);
    }

    public function restore(Request $request): void
    {
        $this->requirePermission('contacts.delete');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $contact = $this->contacts->find($id);
        if (!$contact) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte/archiv');
        }

        $this->contacts->restore($id);
        $name = trim($contact['vorname'] . ' ' . $contact['nachname']);
        $this->logs->addAudit((int) $this->auth->user()['id'], $id, 'updated', 'Kontakt wiederhergestellt: ' . $name . '.');
        flash('success', $name . ' ist wieder im aktiven Adressbuch. Ein früher verknüpfter Login bleibt deaktiviert – bei Bedarf unter „Zugänge" wieder aktivieren.');
        Redirect::to('/kontakte/archiv');
    }

    public function purge(Request $request): void
    {
        $this->requirePermission('contacts.delete');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $contact = $this->contacts->find($id);
        $name = $contact ? trim($contact['vorname'] . ' ' . $contact['nachname']) : 'Kontakt';
        $this->users->deactivateByContactId($id);
        $this->contacts->purge($id);
        $this->logs->addAudit((int) $this->auth->user()['id'], null, 'deleted', 'Kontakt endgültig gelöscht: ' . $name . '.');
        flash('success', $name . ' wurde endgültig gelöscht.');
        Redirect::to('/kontakte/archiv');
    }

    /** Dubletten-Finder: Kontakte, die vermutlich doppelt angelegt wurden. */
    public function duplicates(): void
    {
        $this->requirePermission('contacts.manage');
        $clusters = $this->contacts->duplicateClusters();
        ContactFieldRedactor::applyToClusters($clusters, (int) ($this->auth->user()['contact_id'] ?? 0));

        $this->render('contacts/duplicates', [
            'clusters' => $clusters,
            'canMerge' => can('contacts.delete'),
        ]);
    }

    /** Zwei oder mehr Kontakte zu einem zusammenführen. */
    public function merge(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));
        if (!can('contacts.delete')) {
            flash('error', 'Zum Zusammenführen fehlt die Berechtigung zum Löschen von Kontakten.');
            Redirect::to('/kontakte/dubletten');
        }

        $primaryId = (int) $request->input('primary_id');
        $secondaryIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('secondary_ids', [])),
            static fn (int $id): bool => $id > 0 && $id !== $primaryId
        )));

        $primary = $this->contacts->find($primaryId);
        if (!$primary || $secondaryIds === []) {
            flash('error', 'Bitte einen Haupt-Kontakt und mindestens einen weiteren wählen.');
            Redirect::to('/kontakte/dubletten');
        }

        $userId = (int) $this->auth->user()['id'];
        $mergedNames = [];
        $filled = [];
        $notes = [];
        foreach ($secondaryIds as $sid) {
            $sec = $this->contacts->find($sid);
            if (!$sec) {
                continue;
            }
            $result = $this->contacts->merge($primaryId, $sid, $userId);
            $mergedNames[] = trim($sec['vorname'] . ' ' . $sec['nachname']);
            $filled = array_merge($filled, $result['filled']);
            if ($result['note'] !== '') {
                $notes[] = $result['note'];
            }
        }

        $filled = array_values(array_unique($filled));
        $summary = 'Zusammengeführt mit: ' . implode(', ', $mergedNames) . '.'
            . ($filled !== [] ? ' Ergänzt: ' . implode(', ', $filled) . '.' : '');
        $this->logs->addAudit($userId, $primaryId, 'updated', $summary);

        flash('success', trim('Kontakte zusammengeführt. ' . implode(' ', $notes)));
        Redirect::to('/contacts/edit?id=' . $primaryId);
    }
}
