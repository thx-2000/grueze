<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Repositories\TagRepository;
use App\Repositories\UserRepository;
use App\Services\CsvExportService;
use App\Services\ContactImportService;
use App\Services\UploadService;
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
        private ContactImportService $imports
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
            'sort' => (string) $request->input('sort', ''),
            'direction' => (string) $request->input('direction', 'asc'),
        ];

        $this->render('contacts/index', [
            'contacts' => $this->contacts->search($filters),
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'filters' => $filters,
            'phoneLabels' => config('defaults.phone_labels', []),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('contacts.manage');
        $this->render('contacts/form', [
            'contact' => null,
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'roles' => $this->users->roles(),
            'phoneLabels' => config('defaults.phone_labels', []),
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

        Redirect::to('/');
    }

    public function store(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        Csrf::validate($request->input('_csrf'));

        $data = $this->sanitizePayload($request);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
            'strasse' => ['required'],
            'plz' => ['required'],
            'ort' => ['required'],
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
        Redirect::to('/');
    }

    public function edit(Request $request): void
    {
        $this->requirePermission('contacts.manage');
        $contact = $this->contacts->find((int) $request->input('id'));
        if (!$contact) {
            flash('error', 'Kontakt nicht gefunden.');
            Redirect::to('/');
        }

        $this->render('contacts/form', [
            'contact' => $contact,
            'categories' => $this->categories->all(),
            'tags' => $this->tags->all(),
            'roles' => $this->users->roles(),
            'phoneLabels' => config('defaults.phone_labels', []),
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
            Redirect::to('/');
        }

        $data = $this->sanitizePayload($request);
        $errors = Validator::validate($data, [
            'vorname' => ['required'],
            'nachname' => ['required'],
            'strasse' => ['required'],
            'plz' => ['required'],
            'ort' => ['required'],
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
        $this->logs->addAudit((int) $this->auth->user()['id'], $id, 'updated', 'Kontaktdaten wurden aktualisiert.');
        flash('success', trim('Der Kontakt wurde gespeichert. ' . $accountMessage));
        Redirect::to('/');
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('contacts.delete');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $contact = $this->contacts->find($id);
        $this->users->deactivateByContactId($id);
        $this->contacts->delete($id);
        $details = $contact
            ? 'Kontakt wurde gelöscht: ' . $contact['vorname'] . ' ' . $contact['nachname'] . '.'
            : 'Kontakt wurde gelöscht.';
        $this->logs->addAudit((int) $this->auth->user()['id'], null, 'deleted', $details);
        flash('success', 'Der Kontakt wurde gelöscht.');
        Redirect::to('/');
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

    private function sanitizePayload(Request $request): array
    {
        $emails = [];
        foreach (($request->input('emails', []) ?: []) as $entry) {
            $email = trim((string) ($entry['email'] ?? ''));
            $label = trim((string) ($entry['label'] ?? ''));
            if ($email !== '') {
                $emails[] = ['email' => $email, 'label' => $label];
            }
        }

        $phones = [];
        foreach (($request->input('phones', []) ?: []) as $entry) {
            $phone = trim((string) ($entry['phone'] ?? ''));
            $label = trim((string) ($entry['label'] ?? 'Sonstige'));
            if ($phone !== '') {
                $phones[] = ['phone' => $phone, 'label' => $label];
            }
        }

        $loginEnabled = can('users.manage') && $request->input('login_enabled') !== null;
        $loginEmail = trim((string) $request->input('login_email'));
        if ($loginEmail === '' && isset($emails[0]['email'])) {
            $loginEmail = $emails[0]['email'];
        }

        return [
            'vorname' => trim((string) $request->input('vorname')),
            'nachname' => trim((string) $request->input('nachname')),
            'geburtsname' => trim((string) $request->input('geburtsname')),
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

            return 'Bestehendes Benutzerkonto wurde mit diesem Kontakt verknüpft.';
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
            'login_email' => 'Diese Login-E-Mail wird bereits von einem anderen Benutzerkonto verwendet.',
        ];
    }
}
