<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use App\Services\CsvExportService;
use App\Services\UploadService;
use App\Services\Validator;
use App\Support\Redirect;

final class ContactController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private CategoryRepository $categories,
        private LogRepository $logs,
        private UploadService $uploads,
        private CsvExportService $csv
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request): void
    {
        $this->requireAuth();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'sort' => (string) $request->input('sort', ''),
            'direction' => (string) $request->input('direction', 'asc'),
        ];

        $this->render('contacts/index', [
            'contacts' => $this->contacts->search($filters),
            'categories' => $this->categories->all(),
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
            'phoneLabels' => config('defaults.phone_labels', []),
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
            'strasse' => ['required'],
            'plz' => ['required'],
            'ort' => ['required'],
        ]);

        if ($errors !== []) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $request->all();
            Redirect::to('/contacts/create');
        }

        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'));
        $contactId = $this->contacts->create($data, (int) $this->auth->user()['id']);
        $this->logs->addAudit((int) $this->auth->user()['id'], $contactId, 'created', 'Kontakt wurde angelegt.');
        flash('success', 'Der Kontakt wurde angelegt.');
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
        $data['photo_path'] = $this->uploads->storePhoto($request->file('photo'), $existing['photo_path']);
        $this->contacts->update($id, $data, (int) $this->auth->user()['id']);
        $this->logs->addAudit((int) $this->auth->user()['id'], $id, 'updated', 'Kontaktdaten wurden aktualisiert.');
        flash('success', 'Der Kontakt wurde gespeichert.');
        Redirect::to('/');
    }

    public function delete(Request $request): void
    {
        $this->requirePermission('contacts.delete');
        Csrf::validate($request->input('_csrf'));
        $id = (int) $request->input('id');
        $this->contacts->delete($id);
        $this->logs->addAudit((int) $this->auth->user()['id'], $id, 'deleted', 'Kontakt wurde gelöscht.');
        flash('success', 'Der Kontakt wurde gelöscht.');
        Redirect::to('/');
    }

    public function export(Request $request): never
    {
        $this->requirePermission('contacts.export');
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
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
            'emails' => $emails,
            'phones' => $phones,
        ];
    }
}

