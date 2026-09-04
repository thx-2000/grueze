<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\DataCheckRepository;
use App\Repositories\GroupRepository;
use App\Repositories\LogRepository;
use App\Repositories\TagRepository;
use App\Repositories\UserRepository;
use App\Services\CsvExportService;
use App\Services\ContactImportService;
use App\Services\UploadService;
use App\Services\VCardService;
use App\Services\Validator;
use App\Support\Redirect;

final class ContactController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private CategoryRepository $categories,
        private TagRepository $tags,
        private UserRepository $users,
        private LogRepository $logs,
        private UploadService $uploads,
        private CsvExportService $csv,
        private ContactImportService $imports,
        private GroupRepository $groups,
        private DataCheckRepository $dataChecks,
        private VCardService $vcards
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request): void
    {
        $this->requireAuth();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_map('intval', (array) $request->input('tag_ids', [])),
            'group_ids' => array_map('intval', (array) $request->input('group_ids', [])),
            'without_email' => (string) $request->input('without_email', '') === '1' ? '1' : '',
            'without_phone' => (string) $request->input('without_phone', '') === '1' ? '1' : '',
            'sort' => (string) $request->input('sort', 'vorname'),
            'direction' => (string) $request->input('direction', 'asc'),
        ];

        $ownContactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        $ownContact = $ownContactId > 0 ? $this->contacts->find($ownContactId) : null;

        $contacts = $this->contacts->search($filters);
        $this->redactHiddenFields($contacts, $ownContactId);

        $this->render('contacts/index', [
            'contacts' => $contacts,
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'groups' => $this->groups->all(),
            'filters' => $filters,
            'phoneLabels' => config('defaults.phone_labels', []),
            'ownContact' => $ownContact,
            'retiredCount' => can('contacts.delete')
                ? array_sum($this->contacts->retiredCounts())
                : 0,
            'duplicateCount' => can('contacts.manage')
                ? $this->contacts->duplicateClusterCount()
                : 0,
        ]);
    }

    /**
     * Entfernt personenbezogene Feldwerte aus der Kontaktliste, die die aktuelle
     * Rolle nicht sehen darf – bevor die Daten überhaupt an die View gehen.
     * Zeilen bleiben erhalten (Status „Mail/Tel. fehlt" braucht die Anzahl),
     * nur die Werte werden geleert. Der eigene verknüpfte Kontakt bleibt
     * unberührt (die Seite zeigt ihn separat, Notizen ausgenommen).
     *
     * @param array<int,array<string,mixed>> $contacts
     */
    private function redactHiddenFields(array &$contacts, int $ownContactId): void
    {
        $show = [
            'address'  => can_view_contact_field('address'),
            'birthday' => can_view_contact_field('birthday'),
            'emails'   => can_view_contact_field('emails'),
            'phones'   => can_view_contact_field('phones'),
            'login'    => can_view_contact_field('login'),
            'notes'    => can_view_contact_field('notes'),
        ];
        if (!in_array(false, $show, true)) {
            return;
        }

        foreach ($contacts as &$contact) {
            if ((int) ($contact['id'] ?? 0) === $ownContactId && $ownContactId > 0) {
                continue;
            }
            if (!$show['emails']) {
                foreach (($contact['emails'] ?? []) as $i => $_) {
                    $contact['emails'][$i] = ['email' => '', 'label' => ''];
                }
            }
            if (!$show['phones']) {
                foreach (($contact['phones'] ?? []) as $i => $_) {
                    $contact['phones'][$i] = ['phone' => '', 'label' => ''];
                }
            }
            if (!$show['address']) {
                $contact['strasse'] = $contact['plz'] = $contact['ort'] = $contact['land'] = '';
            }
            if (!$show['birthday']) {
                $contact['geburtstag'] = null;
            }
            if (!$show['notes']) {
                $contact['notizen'] = '';
            }
            if (!$show['login']) {
                $contact['linked_user'] = null;
            }
        }
        unset($contact);
    }

    /**
     * Vollständigkeit (löst die Namensliste ab): Überblick über Datenlücken,
     * pro Person direkte Aktionen, dazu die Namen als Kopiervorlage.
     */
    public function completeness(Request $request): void
    {
        $this->requirePermission('contacts.manage');

        $categoryId = (string) $request->input('category_id', '');
        $which = (string) $request->input('which', 'all');
        $which = in_array($which, ['all', 'email', 'phone'], true) ? $which : 'all';
        $numbered = (string) $request->input('numbered', '1') === '1';

        $all = $this->contacts->search([
            'category_id' => $categoryId,
            'sort' => 'nachname',
            'direction' => 'asc',
        ]);

        $stats = ['total' => count($all), 'without_email' => 0, 'without_phone' => 0];
        $gaps = [];
        foreach ($all as $contact) {
            $missingEmail = ($contact['emails'] ?? []) === [];
            $missingPhone = ($contact['phones'] ?? []) === [];
            if ($missingEmail) {
                $stats['without_email']++;
            }
            if ($missingPhone) {
                $stats['without_phone']++;
            }
            if (!$missingEmail && !$missingPhone) {
                continue;
            }
            if ($which === 'email' && !$missingEmail) {
                continue;
            }
            if ($which === 'phone' && !$missingPhone) {
                continue;
            }
            $gaps[] = [
                'id' => (int) $contact['id'],
                'name' => trim($contact['vorname'] . ' ' . $contact['nachname']),
                'geburtsname' => ($contact['geburtsname'] ?? '') !== '' && $contact['geburtsname'] !== $contact['nachname'] ? (string) $contact['geburtsname'] : '',
                'category_name' => (string) ($contact['category_name'] ?? ''),
                'missing_email' => $missingEmail,
                'missing_phone' => $missingPhone,
                'email' => (string) ($contact['emails'][0]['email'] ?? ''),
            ];
        }

        $lines = [];
        foreach (array_values($all) as $index => $contact) {
            $name = trim($contact['vorname'] . ' ' . $contact['nachname']);
            $lines[] = $numbered ? ($index + 1) . '. ' . $name : $name;
        }

        $this->render('contacts/completeness', [
            'categories' => $this->categories->all(),
            'categoryId' => $categoryId,
            'which' => $which,
            'numbered' => $numbered,
            'stats' => $stats,
            'gaps' => $gaps,
            'nameListText' => implode("\n", $lines),
            'canShare' => can('mail.send'),
        ]);
    }

    /** „Liste teilen": Namen als Nachrichtentext an den Nachrichten-Flow übergeben. */
    public function shareCompleteness(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $list = trim((string) $request->input('name_list'));
        $intro = trim((string) $request->input('intro'));
        if ($list === '') {
            flash('error', 'Die Namensliste ist leer.');
            Redirect::to('/vollstaendigkeit');
        }

        $_SESSION['mail_draft'] = [
            'subject' => 'Bitte die Namensliste auf Vollständigkeit prüfen',
            'message' => ($intro !== '' ? $intro . "\n\n" : '') . $list,
            'salutation_mode' => 'hallo',
        ];
        Redirect::to('/rundmail');
    }

    public function create(): void
    {
        $this->requirePermission('contacts.manage');
        $this->render('contacts/detail', [
            'contact' => null,
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'roles' => $this->users->roles(),
            'phoneLabels' => config('defaults.phone_labels', []),
            'history' => [],
        ]);
    }

    public function importForm(): void
    {
        $this->requirePermission('contacts.manage');
        $this->render('contacts/import');
    }

    public function importXlsx(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $file = $request->file('import_file');
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Bitte eine XLSX-Datei auswählen.');
            Redirect::to('/contacts/import');
        }

        $filename = (string) ($file['name'] ?? '');
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            flash('error', 'Bitte eine Datei im XLSX-Format hochladen.');
            Redirect::to('/contacts/import');
        }

        if ((int) ($file['size'] ?? 0) > (int) config('security.import_max_size', 5242880)) {
            flash('error', 'Die Datei ist zu groß (max. 5 MB).');
            Redirect::to('/contacts/import');
        }

        try {
            $summary = $this->imports->importRamaWorkbook((string) $file['tmp_name'], (int) $this->auth->user()['id']);
            flash(
                'success',
                sprintf(
                    'Import abgeschlossen: %d neu, %d aktualisiert, %d übersprungen, %d ohne Mailadresse.',
                    $summary['created'],
                    $summary['updated'],
                    $summary['skipped'],
                    $summary['without_email']
                )
            );
        } catch (\Throwable $exception) {
            flash('error', 'Import fehlgeschlagen: ' . $exception->getMessage());
            Redirect::to('/contacts/import');
        }

        Redirect::to('/kontakte');
    }

    public function store(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitizePayload($request);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
        ]);
        if (can('users.manage') && $data['login_enabled']) {
            $errors = array_merge($errors, Validator::validate([
                'login_email' => $data['login_email'],
                'role_id' => (string) $data['role_id'],
            ], [
                'login_email' => ['required', 'email'],
                'role_id' => ['required'],
            ]));
            $errors = array_merge($errors, $this->validateLinkedAccountUniqueness($data, null));
        }

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/contacts/create');
        }

        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'));
        $contactId = $this->contacts->create($data, (int) $this->auth->user()['id']);
        $accountMessage = $this->syncLinkedAccount($contactId, $data);
        $this->logs->addAudit((int) $this->auth->user()['id'], $contactId, 'created', 'Kontakt wurde angelegt.');
        flash('success', trim('Der Kontakt wurde angelegt. ' . $accountMessage));
        Redirect::to('/kontakte');
    }

    public function edit(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        $contact = $this->contacts->find((int) $request->input('id'));
        if (!$contact) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $freshLink = null;
        if (($_SESSION['data_check_link']['contact_id'] ?? 0) === (int) $contact['id']) {
            $freshLink = (string) $_SESSION['data_check_link']['url'];
            unset($_SESSION['data_check_link']);
        }

        $this->render('contacts/detail', [
            'contact' => $contact,
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'roles' => $this->users->roles(),
            'phoneLabels' => config('defaults.phone_labels', []),
            'history' => can('audit.view')
                ? $this->logs->contactAuditTrail((int) $contact['id'])
                : [],
            'dataCheckActive' => $this->dataChecks->activeForContact((int) $contact['id']),
            'dataCheckFreshLink' => $freshLink,
        ]);
    }

    public function update(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $existing = $this->contacts->find($id);
        if (!$existing) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/kontakte');
        }

        $data = $this->sanitizePayload($request);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
        ]);
        if (can('users.manage') && $data['login_enabled']) {
            $errors = array_merge($errors, Validator::validate([
                'login_email' => $data['login_email'],
                'role_id' => (string) $data['role_id'],
            ], [
                'login_email' => ['required', 'email'],
                'role_id' => ['required'],
            ]));
            $errors = array_merge($errors, $this->validateLinkedAccountUniqueness($data, $id));
        }
        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/contacts/edit?id=' . $id);
        }
        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'), $existing['photo_path']);
        $this->contacts->update($id, $data, (int) $this->auth->user()['id']);
        $accountMessage = $this->syncLinkedAccount($id, $data);
        $changes = $this->contactChanges($existing, $data);
        $summary = $changes === []
            ? 'Kontakt gespeichert, keine Feldänderung.'
            : 'Geändert: ' . implode(', ', array_keys($changes)) . '.';
        $this->logs->addAudit((int) $this->auth->user()['id'], $id, 'updated', $summary, $changes);
        flash('success', trim('Der Kontakt wurde gespeichert. ' . $accountMessage));
        Redirect::to('/contacts/edit?id=' . $id);
    }

    /**
     * Selbst-Service („Mein Eintrag"): Eine angemeldete Person pflegt Stammdaten,
     * Adresse und Kontaktwege im eigenen verknüpften Kontakt. Kategorie, Tags,
     * Notizen und Login bleiben unberührt – die ändert nur die Verwaltung.
     */
    public function updateOwnProfile(Request $request): void
    {
        $this->requireAuth();
        Csrf::validate($request->input('_csrf'));

        $user = $this->auth->user();
        $contactId = (int) ($user['contact_id'] ?? 0);
        $existing = $contactId > 0 ? $this->contacts->find($contactId) : null;
        if (!$existing) {
            flash('error', 'Für dich ist noch kein Eintrag im Adressbuch verknüpft.');
            Redirect::to('/account');
        }

        // „Eigenen Eintrag bearbeiten" folgt derselben Regel wie das Ansehen der
        // eigenen Daten (Rollen-Sichtbarkeit oder der Selbst-Service-Schalter).
        if (!can('contacts.manage') && !can_view_contact_field('address', $existing)) {
            flash('error', 'Deine Daten pflegt zurzeit das Orga-Team.');
            Redirect::to('/account');
        }

        $data = $this->sanitizeOwnProfilePayload($request, $existing);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
        ]);
        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/account');
        }

        $this->contacts->update($contactId, $data, (int) $user['id']);
        $changes = $this->contactChanges($existing, $data);
        $summary = $changes === []
            ? 'Eigener Eintrag gespeichert, keine Feldänderung.'
            : 'Selbst gepflegt: ' . implode(', ', array_keys($changes)) . '.';
        $this->logs->addAudit((int) $user['id'], $contactId, 'updated', $summary, $changes);
        flash('success', 'Deine Angaben wurden gespeichert – danke fürs Aktuell-Halten.');
        Redirect::to('/account');
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
        $this->redactHiddenFields2($clusters);

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

    /**
     * Wie redactHiddenFields, aber über die Cluster-Struktur des Dubletten-Finders.
     *
     * @param array<int,array{reason:string,contacts:array<int,array<string,mixed>>}> $clusters
     */
    private function redactHiddenFields2(array &$clusters): void
    {
        $ownContactId = (int) ($this->auth->user()['contact_id'] ?? 0);
        foreach ($clusters as &$cluster) {
            $this->redactHiddenFields($cluster['contacts'], $ownContactId);
        }
        unset($cluster);
    }

    public function export(Request $request): never
    {
        $this->requirePermission('contacts.export');
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_map('intval', (array) $request->input('tag_ids', [])),
            'sort' => (string) $request->input('sort', ''),
            'direction' => (string) $request->input('direction', 'asc'),
        ];
        $this->csv->stream($this->contacts->search($filters));
    }

    /**
     * vCard-Export (.vcf): einzelner Kontakt (`?id=`), aktuelle Auswahl
     * (`selected_contacts[]` per POST) oder die gefilterte Liste (GET).
     */
    public function vcard(Request $request): never
    {
        $this->requirePermission('contacts.export');

        $singleId = (int) $request->input('id');
        $selected = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('selected_contacts', [])),
            static fn (int $id): bool => $id > 0
        )));

        if ($singleId > 0) {
            $contact = $this->contacts->find($singleId);
            if (!$contact || !empty($contact['archived_at']) || !empty($contact['deleted_at'])) {
                flash('error', 'Kontakt nicht gefunden.');
                Redirect::to('/kontakte');
            }
            $name = trim($contact['vorname'] . ' ' . $contact['nachname']) ?: 'kontakt';
            $this->vcards->stream([$contact], $name . '.vcf');
        }

        if ($selected !== []) {
            $contacts = $this->contacts->findManyByIds($selected);
            $this->vcards->stream($contacts, 'kontakte-auswahl.vcf');
        }

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_map('intval', (array) $request->input('tag_ids', [])),
            'group_ids' => array_map('intval', (array) $request->input('group_ids', [])),
            'sort' => (string) $request->input('sort', ''),
            'direction' => (string) $request->input('direction', 'asc'),
        ];
        $this->vcards->stream($this->contacts->search($filters), 'kontakte.vcf');
    }

    public function bulkUpdate(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $contactIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('selected_contacts', [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($contactIds === []) {
            flash('error', 'Bitte zuerst Kontakte auswählen.');
            Redirect::to('/kontakte');
        }

        $categoryInput = trim((string) $request->input('bulk_category_id', ''));
        $changeCategory = $categoryInput !== '';
        $categoryId = $categoryInput === '__none__' ? null : ($changeCategory ? (int) $categoryInput : null);
        $categoryOnlyIfEmpty = $request->input('bulk_category_only_if_empty') !== null;
        $tagIdsToAdd = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('bulk_tag_ids_add', [])),
            static fn (int $id): bool => $id > 0
        )));
        $tagIdsToRemove = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('bulk_tag_ids_remove', [])),
            static fn (int $id): bool => $id > 0
        )));

        if (!$changeCategory && $tagIdsToAdd === [] && $tagIdsToRemove === []) {
            flash('error', 'Bitte mindestens eine Kategorie oder einen Tag für die Sammeländerung wählen.');
            Redirect::to('/kontakte');
        }

        $updatedContacts = $this->contacts->applyBulkUpdate(
            $contactIds,
            $changeCategory,
            $categoryId,
            $categoryOnlyIfEmpty,
            $tagIdsToAdd,
            $tagIdsToRemove,
            (int) $this->auth->user()['id']
        );

        $details = [];
        if ($changeCategory) {
            $details[] = $categoryId === null
                ? 'Kategorie entfernt'
                : ($categoryOnlyIfEmpty ? 'Kategorie nur bei leeren Kontakten gesetzt' : 'Kategorie gesetzt');
        }
        if ($tagIdsToAdd !== []) {
            $details[] = count($tagIdsToAdd) . ' Tag(s) ergänzt';
        }
        if ($tagIdsToRemove !== []) {
            $details[] = count($tagIdsToRemove) . ' Tag(s) entfernt';
        }

        $message = sprintf(
            'Sammeländerung gespeichert: %d Kontakte aktualisiert%s.',
            $updatedContacts,
            $details !== [] ? ' (' . implode(', ', $details) . ')' : ''
        );
        $this->logs->addAudit((int) $this->auth->user()['id'], null, 'updated', $message);
        flash('success', $message);
        Redirect::to('/kontakte');
    }

    /**
     * Aus der aktuellen Auswahl eine neue Gruppe machen. Braucht zusätzlich
     * `groups.manage`.
     */
    public function groupFromSelection(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        if (!$this->auth->can('groups.manage')) {
            flash('error', 'Zum Anlegen von Gruppen fehlt dir die Berechtigung.');
            Redirect::to('/kontakte');
        }
        Csrf::validate($request->input('_csrf'));

        $contactIds = array_values(array_unique(array_filter(
            array_map('intval', (array) $request->input('selected_contacts', [])),
            static fn (int $id): bool => $id > 0
        )));
        $name = trim((string) $request->input('group_name'));

        if ($contactIds === []) {
            flash('error', 'Bitte zuerst Kontakte auswählen.');
            Redirect::to('/kontakte');
        }
        if ($name === '') {
            flash('error', 'Bitte einen Namen für die Gruppe angeben.');
            Redirect::to('/kontakte');
        }
        if ($this->groups->nameExists($name)) {
            flash('error', 'Eine Gruppe mit diesem Namen gibt es schon.');
            Redirect::to('/kontakte');
        }

        $groupId = $this->groups->create(
            ['name' => $name, 'description' => '', 'is_open' => false],
            (int) ($this->auth->user()['id'] ?? 0) ?: null
        );
        $this->groups->syncMembers($groupId, $contactIds);

        flash('success', sprintf(
            'Gruppe „%s" mit %d %s angelegt.',
            $name,
            count($contactIds),
            count($contactIds) === 1 ? 'Mitglied' : 'Mitgliedern'
        ));
        Redirect::to('/verwaltung/gruppen/detail?id=' . $groupId);
    }

    /**
     * Entfernt fuehrende "mailto:"-Praefixe (Alt-Importdaten), Steuerzeichen
     * (u. a. CR/LF – sonst E-Mail-Header-Injection beim Versand) und trimmt.
     */
    private function cleanEmail(string $value): string
    {
        $value = (string) preg_replace('/^\s*mailto:\s*/i', '', $value);
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $value);

        return trim($value);
    }

    private function sanitizePayload(Request $request): array
    {
        $emails = [];
        foreach (($request->input('emails', []) ?: []) as $entry) {
            $email = $this->cleanEmail((string) ($entry['email'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            if ($email !== '') {
                $emails[] = ['email' => $email, 'label' => $label];
            }
        }

        $phones = [];
        foreach (($request->input('phones', []) ?: []) as $entry) {
            $phone = trim((string) preg_replace(['/^\s*tel:\s*/i', '/[\x00-\x1F\x7F]+/'], '', (string) ($entry['phone'] ?? '')));
            $label = trim((string) ($entry['label'] ?? 'Sonstige'));
            if ($phone !== '') {
                $phones[] = ['phone' => $phone, 'label' => $label];
            }
        }

        $loginEnabled = can('users.manage') && $request->input('login_enabled') !== null;
        $loginEmail = $this->cleanEmail((string) $request->input('login_email'));
        if ($loginEmail === '' && isset($emails[0]['email'])) {
            $loginEmail = $emails[0]['email'];
        }

        return [
            'vorname' => trim((string) $request->input('vorname')),
            'nachname' => trim((string) $request->input('nachname')),
            'geburtsname' => trim((string) $request->input('geburtsname')),
            'geschlecht' => $this->normalizeGeschlecht((string) $request->input('geschlecht')),
            'category_id' => (string) $request->input('category_id'),
            'geburtstag' => (string) $request->input('geburtstag'),
            'strasse' => trim((string) $request->input('strasse')),
            'plz' => trim((string) $request->input('plz')),
            'ort' => trim((string) $request->input('ort')),
            'land' => trim((string) $request->input('land', (string) config('defaults.country', 'Deutschland'))),
            'notizen' => trim((string) $request->input('notizen')),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', [])))),
            'emails' => $emails,
            'phones' => $phones,
            'login_enabled' => $loginEnabled,
            'login_email' => $loginEmail,
            'role_id' => (int) $request->input('role_id'),
        ];
    }

    /**
     * Beschnittene Variante von sanitizePayload() für den Selbst-Service: nur die
     * Felder, die eine Person am eigenen Eintrag ändern darf. Kategorie, Tags,
     * Notizen und Bild werden aus dem Bestand übernommen, damit contacts.update()
     * sie nicht leert.
     */
    private function sanitizeOwnProfilePayload(Request $request, array $existing): array
    {
        $emails = [];
        foreach (($request->input('emails', []) ?: []) as $entry) {
            $email = $this->cleanEmail((string) ($entry['email'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            if ($email !== '') {
                $emails[] = ['email' => $email, 'label' => $label];
            }
        }

        $phones = [];
        foreach (($request->input('phones', []) ?: []) as $entry) {
            $phone = trim((string) preg_replace(['/^\s*tel:\s*/i', '/[\x00-\x1F\x7F]+/'], '', (string) ($entry['phone'] ?? '')));
            $label = trim((string) ($entry['label'] ?? 'Sonstige'));
            if ($phone !== '') {
                $phones[] = ['phone' => $phone, 'label' => $label];
            }
        }

        return [
            'vorname' => trim((string) $request->input('vorname')),
            'nachname' => trim((string) $request->input('nachname')),
            'geburtsname' => trim((string) $request->input('geburtsname')),
            'geschlecht' => $this->normalizeGeschlecht((string) $request->input('geschlecht')),
            'geburtstag' => (string) $request->input('geburtstag'),
            'strasse' => trim((string) $request->input('strasse')),
            'plz' => trim((string) $request->input('plz')),
            'ort' => trim((string) $request->input('ort')),
            'land' => trim((string) $request->input('land', (string) config('defaults.country', 'Deutschland'))),
            'category_id' => (string) ($existing['category_id'] ?? ''),
            'notizen' => (string) ($existing['notizen'] ?? ''),
            'photo_path' => (string) ($existing['photo_path'] ?? ''),
            'tag_ids' => array_map(static fn (array $tag): int => (int) $tag['id'], $existing['tags'] ?? []),
            'emails' => $emails,
            'phones' => $phones,
        ];
    }

    /**
     * Anrede-Code des Kontakts. `m`/`w` steuern die Anrede „Lieber"/„Liebe",
     * leer bedeutet die neutrale Anrede „Hallo". Historische Spalte
     * `contacts.geschlecht` – im UI heißt das Feld nur noch „Anrede".
     */
    private function normalizeGeschlecht(string $geschlecht): string
    {
        $normalized = strtolower(trim($geschlecht));

        return in_array($normalized, ['m', 'w'], true) ? $normalized : '';
    }

    /**
     * Feldweiser Vergleich alt → neu für den Änderungsverlauf. Nur tatsächlich
     * geänderte Felder, Werte als menschenlesbarer Text.
     *
     * @param array<string, mixed> $before Kontakt aus find() (inkl. emails/phones/tags/linked_user)
     * @param array<string, mixed> $after  bereinigte Formulardaten aus sanitizePayload()
     * @return array<string, array{from: string, to: string}>
     */
    private function contactChanges(array $before, array $after): array
    {
        $geschlecht = static fn (string $v): string => match ($v) {
            'm' => '„Lieber …"', 'w' => '„Liebe …"', default => 'neutral („Hallo …")',
        };
        $categoryName = static function (string $id, array $categories): string {
            foreach ($categories as $category) {
                if ((string) $category['id'] === $id && $id !== '') {
                    return (string) $category['name'];
                }
            }

            return '—';
        };
        $categories = $this->categories->all();
        $tagNames = function (array $ids): string {
            $ids = array_map('intval', $ids);
            $names = [];
            foreach ($this->tags->all() as $tag) {
                if (in_array((int) $tag['id'], $ids, true)) {
                    $names[] = (string) $tag['name'];
                }
            }
            sort($names);

            return $names === [] ? '—' : implode(', ', $names);
        };
        $emailText = static function (array $rows): string {
            $parts = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $parts[] = ($label !== '' ? $label . ': ' : '') . (string) ($row['email'] ?? '');
            }
            sort($parts);

            return $parts === [] ? '—' : implode(', ', $parts);
        };
        $phoneText = static function (array $rows): string {
            $parts = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $parts[] = ($label !== '' ? $label . ': ' : '') . (string) ($row['phone'] ?? '');
            }
            sort($parts);

            return $parts === [] ? '—' : implode(', ', $parts);
        };

        $pairs = [
            'Vorname' => [(string) ($before['vorname'] ?? ''), (string) $after['vorname']],
            'Nachname' => [(string) ($before['nachname'] ?? ''), (string) $after['nachname']],
            'Geburtsname' => [(string) ($before['geburtsname'] ?? ''), (string) $after['geburtsname']],
            'Anrede' => [$geschlecht((string) ($before['geschlecht'] ?? '')), $geschlecht((string) $after['geschlecht'])],
            'Geburtstag' => [(string) ($before['geburtstag'] ?? ''), (string) $after['geburtstag']],
            'Kategorie' => [
                (string) ($before['category_name'] ?? '') ?: '—',
                $categoryName((string) $after['category_id'], $categories),
            ],
            'Straße' => [(string) ($before['strasse'] ?? ''), (string) $after['strasse']],
            'PLZ' => [(string) ($before['plz'] ?? ''), (string) $after['plz']],
            'Ort' => [(string) ($before['ort'] ?? ''), (string) $after['ort']],
            'Land' => [(string) ($before['land'] ?? ''), (string) $after['land']],
            'Notizen' => [(string) ($before['notizen'] ?? ''), (string) $after['notizen']],
            'Tags' => [
                $tagNames(array_map(static fn (array $t): int => (int) $t['id'], $before['tags'] ?? [])),
                $tagNames((array) $after['tag_ids']),
            ],
            'E-Mail' => [$emailText($before['emails'] ?? []), $emailText($after['emails'])],
            'Telefon' => [$phoneText($before['phones'] ?? []), $phoneText($after['phones'])],
        ];

        $changes = [];
        foreach ($pairs as $label => [$from, $to]) {
            $from = trim($from);
            $to = trim($to);
            if ($from !== $to) {
                $changes[$label] = [
                    'from' => $from === '' ? '—' : $from,
                    'to' => $to === '' ? '—' : $to,
                ];
            }
        }

        return $changes;
    }

    private function syncLinkedAccount(int $contactId, array $data): string
    {
        if (!can('users.manage')) {
            return '';
        }

        $fullName = trim($data['vorname'] . ' ' . $data['nachname']);
        $linkedUser = $this->users->findByContactId($contactId);

        if (!$data['login_enabled']) {
            if ($linkedUser) {
                $this->users->updateLinkedAccount((int) $linkedUser['id'], [
                    'name' => $fullName,
                    'email' => $data['login_email'] ?: $linkedUser['email'],
                    'role_id' => $data['role_id'] ?: (int) $linkedUser['role_id'],
                    'is_active' => 0,
                    'contact_id' => $contactId,
                ]);

                return 'Der verknüpfte Login wurde deaktiviert.';
            }

            return '';
        }

        if ($linkedUser) {
            $this->users->updateLinkedAccount((int) $linkedUser['id'], [
                'name' => $fullName,
                'email' => $data['login_email'],
                'role_id' => $data['role_id'],
                'is_active' => 1,
                'contact_id' => $contactId,
            ]);

            return 'Login und Rolle wurden aktualisiert.';
        }

        $existingUser = $this->users->findByEmail($data['login_email']);
        if ($existingUser && empty($existingUser['contact_id'])) {
            $this->users->updateLinkedAccount((int) $existingUser['id'], [
                'name' => $fullName,
                'email' => $data['login_email'],
                'role_id' => $data['role_id'],
                'is_active' => 1,
                'contact_id' => $contactId,
            ]);

            return 'Bestehender Zugang wurde mit diesem Kontakt verknüpft.';
        }

        $password = $this->generatePassword();
        $this->users->create([
            'name' => $fullName,
            'email' => $data['login_email'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role_id' => $data['role_id'],
            'is_active' => 1,
            'contact_id' => $contactId,
        ]);

        return 'Login angelegt. Erstpasswort: ' . $password;
    }

    private function generatePassword(): string
    {
        return substr(strtr(base64_encode(random_bytes(12)), '+/', 'AZ'), 0, 16);
    }

    private function validateLinkedAccountUniqueness(array $data, ?int $contactId): array
    {
        if (($data['login_email'] ?? '') === '') {
            return [];
        }

        $existingUser = $this->users->findByEmail($data['login_email']);
        if (!$existingUser) {
            return [];
        }

        if ((int) ($existingUser['contact_id'] ?? 0) === (int) ($contactId ?? 0) || empty($existingUser['contact_id'])) {
            return [];
        }

        return [
            'login_email' => 'Diese Login-E-Mail wird bereits von einem anderen Zugang verwendet.',
        ];
    }
}
