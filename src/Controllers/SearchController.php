<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\UserRepository;
use App\Support\ContactFieldRedactor;

final class SearchController extends BaseController
{
    public function __construct(
        \App\Core\Auth $auth,
        private ContactRepository $contacts,
        private UserRepository $users
    ) {
        parent::__construct($auth);
    }

    public function index(Request $request): void
    {
        $this->requireAuth();

        $query = trim((string) $request->input('q', ''));

        $visible = [
            'address' => can_view_contact_field('address'),
            'emails' => can_view_contact_field('emails'),
            'phones' => can_view_contact_field('phones'),
            'notes' => can_view_contact_field('notes'),
        ];

        $contactResults = [];
        if ($query !== '') {
            $ownContactId = (int) ($this->auth->user()['contact_id'] ?? 0);
            $contactResults = $this->contacts->globalSearch($query, $visible, 80);
            ContactFieldRedactor::apply($contactResults, $ownContactId);

            $needle = mb_strtolower($query);
            foreach ($contactResults as &$contact) {
                $contact['_matched'] = $this->matchedFields($contact, $needle, $visible);
            }
            unset($contact);
        }

        $userResults = $query !== '' && $this->auth->can('users.manage')
            ? $this->users->search($query, 30)
            : [];

        // Kategorien, die in den Treffern vorkommen – für die Eingrenzung.
        $categories = [];
        foreach ($contactResults as $c) {
            $name = trim((string) ($c['category_name'] ?? ''));
            if ($name !== '') {
                $categories[$name] = true;
            }
        }
        ksort($categories);

        $this->render('search/index', [
            'query' => $query,
            'contactResults' => $contactResults,
            'userResults' => $userResults,
            'resultCategories' => array_keys($categories),
            'signalHint' => $query === ''
                ? 'Globale Suche'
                : sprintf('%d Kontakte, %d Zugänge', count($contactResults), count($userResults)),
        ]);
    }

    /**
     * Ermittelt, in welchen (sichtbaren) Feldern der Suchbegriff steckt –
     * für die Anzeige „gefunden in …" und die Eingrenzung nach Fundstelle.
     *
     * @param array<string,mixed> $c bereits rollen-redigierter Kontakt
     * @param array<string,bool> $visible
     * @return list<array{key:string,label:string,snippet:string}>
     */
    private function matchedFields(array $c, string $needle, array $visible): array
    {
        $hit = static fn (string $v): bool => $v !== '' && str_contains(mb_strtolower($v), $needle);
        $out = [];
        $push = static function (string $key, string $label, string $snippet) use (&$out): void {
            $out[] = ['key' => $key, 'label' => $label, 'snippet' => trim($snippet)];
        };

        $name = trim(($c['vorname'] ?? '') . ' ' . ($c['nachname'] ?? '') . ' ' . ($c['geburtsname'] ?? ''));
        if ($hit($name)) {
            $push('name', 'Name', trim(($c['vorname'] ?? '') . ' ' . ($c['nachname'] ?? '')));
        }
        if ($hit((string) ($c['beruf'] ?? ''))) {
            $push('beruf', 'Beruf/Tätigkeit', (string) $c['beruf']);
        }
        if ($hit((string) ($c['webseite'] ?? ''))) {
            $push('webseite', 'Webseite', (string) $c['webseite']);
        }
        if ($hit((string) ($c['category_name'] ?? ''))) {
            $push('kategorie', 'Kategorie', (string) $c['category_name']);
        }
        foreach (($c['tags'] ?? []) as $tag) {
            if ($hit((string) ($tag['name'] ?? ''))) {
                $push('tag', 'Tag', (string) $tag['name']);
                break;
            }
        }
        foreach (($c['groups'] ?? []) as $group) {
            if ($hit((string) ($group['name'] ?? ''))) {
                $push('gruppe', 'Gruppe', (string) $group['name']);
                break;
            }
        }
        if ($visible['address']) {
            $addr = trim(($c['strasse'] ?? '') . ' ' . ($c['plz'] ?? '') . ' ' . ($c['ort'] ?? '') . ' ' . ($c['land'] ?? ''));
            if ($hit($addr)) {
                $push('adresse', 'Adresse', $addr);
            }
        }
        if ($visible['emails']) {
            foreach (($c['emails'] ?? []) as $mail) {
                if ($hit((string) ($mail['email'] ?? ''))) {
                    $push('email', 'E-Mail', (string) $mail['email']);
                    break;
                }
            }
        }
        if ($visible['phones']) {
            foreach (($c['phones'] ?? []) as $phone) {
                if ($hit((string) ($phone['phone'] ?? ''))) {
                    $push('telefon', 'Telefon', (string) $phone['phone']);
                    break;
                }
            }
        }
        if ($visible['notes'] && $hit((string) ($c['notizen'] ?? ''))) {
            $push('notiz', 'Notiz', mb_substr((string) $c['notizen'], 0, 120));
        }

        return $out;
    }
}
