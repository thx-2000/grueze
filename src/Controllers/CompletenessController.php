<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\ContactRepository;
use App\Support\Redirect;

/**
 * Vollständigkeit (löst die alte Namensliste ab): Überblick über Datenlücken,
 * pro Person direkte Aktionen, dazu die Namen als Kopiervorlage. „Liste teilen"
 * übergibt die Namen als Nachrichtentext an den Rundmail-Flow.
 */
final class CompletenessController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private CategoryRepository $categories,
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request): void
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
    public function share(Request $request): void
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
}
