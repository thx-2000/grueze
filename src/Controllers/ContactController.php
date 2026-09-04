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
use App\Services\LinkedAccountService;
use App\Services\UploadService;
use App\Services\Validator;
use App\Support\ContactDiff;
use App\Support\ContactFieldRedactor;
use App\Support\ContactInput;
use App\Support\Redirect;

/**
 * Kern-CRUD des Adressbuchs: Liste, Anlegen, Bearbeiten, Speichern, Selbst-
 * Service („Mein Eintrag") sowie die beiden Sammelaktionen (Kategorie/Tags in
 * einem Rutsch, Gruppe aus Auswahl). Archiv/Papierkorb/Dubletten liegen in
 * ContactArchiveController, Import/Export in ContactPortController, die
 * Vollständigkeit in CompletenessController.
 */
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
        private GroupRepository $groups,
        private DataCheckRepository $dataChecks,
        private LinkedAccountService $linkedAccounts,
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
        ContactFieldRedactor::apply($contacts, $ownContactId);

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
            $errors = array_merge($errors, $this->linkedAccounts->validateUniqueness($data, null));
        }

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/contacts/create');
        }

        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'));
        $contactId = $this->contacts->create($data, (int) $this->auth->user()['id']);
        $accountMessage = $this->linkedAccounts->sync($contactId, $data);
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
            $errors = array_merge($errors, $this->linkedAccounts->validateUniqueness($data, $id));
        }
        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/contacts/edit?id=' . $id);
        }
        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'), $existing['photo_path']);
        $this->contacts->update($id, $data, (int) $this->auth->user()['id']);
        $accountMessage = $this->linkedAccounts->sync($id, $data);
        $changes = ContactDiff::describe($existing, $data, $this->categories->all(), $this->tags->all());
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
        $changes = ContactDiff::describe($existing, $data, $this->categories->all(), $this->tags->all());
        $summary = $changes === []
            ? 'Eigener Eintrag gespeichert, keine Feldänderung.'
            : 'Selbst gepflegt: ' . implode(', ', array_keys($changes)) . '.';
        $this->logs->addAudit((int) $user['id'], $contactId, 'updated', $summary, $changes);
        flash('success', 'Deine Angaben wurden gespeichert – danke fürs Aktuell-Halten.');
        Redirect::to('/account');
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

    /** Volles Kontaktformular (Verwaltung) → Datenarray für ContactRepository. */
    private function sanitizePayload(Request $request): array
    {
        $emails = ContactInput::emails($request);

        // Login-Adresse: leer = die erste Kontakt-Mail übernehmen.
        $loginEmail = ContactInput::cleanEmail((string) $request->input('login_email'));
        if ($loginEmail === '' && isset($emails[0]['email'])) {
            $loginEmail = $emails[0]['email'];
        }

        return ContactInput::baseFields($request) + [
            'category_id' => (string) $request->input('category_id'),
            'notizen' => trim((string) $request->input('notizen')),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', [])))),
            'emails' => $emails,
            'phones' => ContactInput::phones($request),
            'login_enabled' => can('users.manage') && $request->input('login_enabled') !== null,
            'login_email' => $loginEmail,
            'role_id' => (int) $request->input('role_id'),
        ];
    }

    /**
     * Beschnittene Variante von sanitizePayload() für den Selbst-Service: nur die
     * Felder, die eine Person am eigenen Eintrag ändern darf. Kategorie, Tags,
     * Notizen und Bild kommen aus dem Bestand, damit contacts.update() sie nicht
     * leert. Identisch mit dem Daten-Check-Link → siehe ContactInput.
     */
    private function sanitizeOwnProfilePayload(Request $request, array $existing): array
    {
        return ContactInput::selfServiceFields($request, $existing);
    }
}
