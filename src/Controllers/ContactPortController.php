<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Services\ContactImportService;
use App\Services\CsvExportService;
use App\Services\VCardService;
use App\Support\Redirect;

/**
 * Kontakte rein und raus: XLSX-Import (RAMA-Arbeitsmappe) sowie CSV- und
 * vCard-Export – einzeln, als aktuelle Auswahl oder als gefilterte Liste.
 */
final class ContactPortController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private ContactImportService $imports,
        private CsvExportService $csv,
        private VCardService $vcards,
    ) {
        parent::__construct($auth);
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
}
