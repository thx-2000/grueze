<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\ContactRepository;
use App\Repositories\RecipientListRepository;

/**
 * „Wer bekommt diese Nachricht?" – übersetzt den gewählten Empfängerkreis
 * (alle / gefilterte Liste / gespeicherte Liste / Kategorie / Tags / manuelle
 * Auswahl) in konkrete Kontakt-IDs und liefert die Anzeigetexte dazu.
 */
final class MailRecipientResolver
{
    public function __construct(
        private ContactRepository $contacts,
        private RecipientListRepository $recipientLists,
    ) {
    }

    /** Filter aus dem Request lesen (identisch zur Kontaktliste). */
    public function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'category_id' => (string) $request->input('category_id', ''),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', [])))),
            'without_email' => (string) $request->input('without_email', '') === '1' ? '1' : '',
            'without_phone' => (string) $request->input('without_phone', '') === '1' ? '1' : '',
        ];
    }

    public function hasActiveFilter(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['category_id'] !== ''
            || $filters['tag_ids'] !== []
            || $filters['without_email'] === '1'
            || $filters['without_phone'] === '1';
    }

    /**
     * Empfänger-IDs aus dem gewählten Empfängerkreis („recipient_mode").
     * Nur für die neue Nachrichten-Seite; die Einzelkontakt-Aufnahme läuft
     * weiter über `contact_ids[]` ohne Modus.
     *
     * @return list<int>
     */
    public function resolve(Request $request): array
    {
        $mode = (string) $request->input('recipient_mode', 'all');
        $withEmail = $this->contacts->recipientIds([]);

        return match ($mode) {
            'selection' => array_values(array_unique(array_filter(
                array_map('intval', (array) $request->input('contact_ids', [])),
                static fn (int $n): bool => $n > 0
            ))),
            'filter' => array_values(array_intersect(
                $withEmail,
                array_map('intval', (array) ($_SESSION['rundmail_filter_ids'] ?? []))
            )),
            'list' => (function () use ($request, $withEmail): array {
                $list = $this->recipientLists->find((int) $request->input('list_id'));

                return $list === null ? [] : array_values(array_intersect($withEmail, $list['contact_ids']));
            })(),
            'category' => (string) $request->input('category_id', '') !== ''
                ? $this->contacts->recipientIds(['category_id' => (string) $request->input('category_id')])
                : [],
            'tags' => ($tagIds = array_values(array_filter(array_map('intval', (array) $request->input('tag_ids', []))))) !== []
                ? $this->contacts->recipientIds(['tag_ids' => $tagIds])
                : [],
            default => $withEmail,
        };
    }

    /** Gespeicherte Empfängerlisten inkl. „wie viele davon noch erreichbar". */
    public function reachableLists(): array
    {
        $withEmail = $this->contacts->recipientIds([]);

        return array_map(static function (array $list) use ($withEmail): array {
            return [
                'id' => $list['id'],
                'name' => $list['name'],
                'total' => count($list['contact_ids']),
                'reachable' => count(array_intersect($withEmail, $list['contact_ids'])),
            ];
        }, $this->recipientLists->all());
    }

    /** Menschenlesbare Zusammenfassung des aktiven Filters. */
    public function filterSummary(array $filters, array $categories, array $tags): string
    {
        $parts = [];
        if ($filters['q'] !== '') {
            $parts[] = 'Suche „' . $filters['q'] . '"';
        }
        if ($filters['category_id'] !== '') {
            foreach ($categories as $category) {
                if ((string) $category['id'] === $filters['category_id']) {
                    $parts[] = 'Kategorie ' . $category['name'];
                }
            }
        }
        if ($filters['tag_ids'] !== []) {
            $names = [];
            foreach ($tags as $tag) {
                if (in_array((int) $tag['id'], $filters['tag_ids'], true)) {
                    $names[] = $tag['name'];
                }
            }
            if ($names !== []) {
                $parts[] = 'Tags: ' . implode(', ', $names);
            }
        }
        if ($filters['without_email'] === '1') {
            $parts[] = 'ohne Mailadresse';
        }
        if ($filters['without_phone'] === '1') {
            $parts[] = 'ohne Handynummer';
        }

        return $parts === [] ? 'alle Kontakte' : implode(' · ', $parts);
    }
}
