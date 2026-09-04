<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Feldweiser Vergleich alt → neu für den Änderungsverlauf eines Kontakts.
 * Liefert nur tatsächlich geänderte Felder, Werte als menschenlesbaren Text.
 */
final class ContactDiff
{
    /**
     * @param array<string,mixed> $before Kontakt aus find() (inkl. emails/phones/tags/linked_user)
     * @param array<string,mixed> $after  bereinigte Formulardaten (baseFields + emails/phones/tag_ids …)
     * @param array<int,array<string,mixed>> $categories alle Kategorien (für die Namensauflösung)
     * @param array<int,array<string,mixed>> $tags       alle Tags (für die Namensauflösung)
     * @return array<string,array{from:string,to:string}>
     */
    public static function describe(array $before, array $after, array $categories, array $tags): array
    {
        $salutation = static fn (string $v): string => match ($v) {
            'm' => '„Lieber …"', 'w' => '„Liebe …"', default => 'neutral („Hallo …")',
        };
        $categoryName = static function (string $id) use ($categories): string {
            foreach ($categories as $category) {
                if ((string) $category['id'] === $id && $id !== '') {
                    return (string) $category['name'];
                }
            }

            return '—';
        };
        $tagNames = static function (array $ids) use ($tags): string {
            $ids = array_map('intval', $ids);
            $names = [];
            foreach ($tags as $tag) {
                if (in_array((int) $tag['id'], $ids, true)) {
                    $names[] = (string) $tag['name'];
                }
            }
            sort($names);

            return $names === [] ? '—' : implode(', ', $names);
        };
        $rowText = static function (array $rows, string $key): string {
            $parts = [];
            foreach ($rows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $parts[] = ($label !== '' ? $label . ': ' : '') . (string) ($row[$key] ?? '');
            }
            sort($parts);

            return $parts === [] ? '—' : implode(', ', $parts);
        };

        $pairs = [
            'Vorname' => [(string) ($before['vorname'] ?? ''), (string) $after['vorname']],
            'Nachname' => [(string) ($before['nachname'] ?? ''), (string) $after['nachname']],
            'Geburtsname' => [(string) ($before['geburtsname'] ?? ''), (string) $after['geburtsname']],
            'Anrede' => [$salutation((string) ($before['anrede'] ?? '')), $salutation((string) ($after['anrede'] ?? ''))],
            'Geburtstag' => [(string) ($before['geburtstag'] ?? ''), (string) $after['geburtstag']],
            'Beruf/Tätigkeit' => [(string) ($before['beruf'] ?? ''), (string) ($after['beruf'] ?? '')],
            'Webseite' => [(string) ($before['webseite'] ?? ''), (string) ($after['webseite'] ?? '')],
            'Kategorie' => [
                (string) ($before['category_name'] ?? '') ?: '—',
                $categoryName((string) $after['category_id']),
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
            'E-Mail' => [$rowText($before['emails'] ?? [], 'email'), $rowText($after['emails'], 'email')],
            'Telefon' => [$rowText($before['phones'] ?? [], 'phone'), $rowText($after['phones'], 'phone')],
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
}
