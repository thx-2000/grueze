<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Feldweiser Vergleich alt → neu für den Änderungsverlauf eines „Weitere
 * Personen"-Eintrags (siehe RosterController) – analog zu ContactDiff.
 * Anders als bei Kontakten läuft `audit_log.contact_id` hier immer auf NULL
 * (roster_people hat einen eigenen ID-Raum, ein FK auf contacts wäre falsch),
 * daher steht der Name hier zusätzlich im Klartext in der Zusammenfassung.
 */
final class RosterDiff
{
    /**
     * @param array<string,mixed> $before Eintrag aus RosterRepository::find()
     * @param array<string,mixed> $after  bereinigte Formulardaten (RosterController::sanitize())
     * @return array<string,array{from:string,to:string}>
     */
    public static function describe(array $before, array $after): array
    {
        $yesNo = static fn (mixed $value): string => $value ? 'ja' : 'nein';

        $pairs = [
            'Name' => [(string) ($before['name'] ?? ''), (string) $after['name']],
            'Rolle' => [(string) ($before['role_label'] ?? ''), (string) $after['role_label']],
            'Fächer' => [(string) ($before['subjects'] ?? ''), (string) $after['subjects']],
            'E-Mail' => [(string) ($before['email'] ?? ''), (string) $after['email']],
            'Handy' => [(string) ($before['mobile'] ?? ''), (string) $after['mobile']],
            'Geburtsdatum' => [
                format_date((string) ($before['born_on'] ?? '')),
                format_date((string) $after['born_on']),
            ],
            'Todesdatum' => [
                format_date((string) ($before['died_on'] ?? '')),
                format_date((string) $after['died_on']),
            ],
            'Todesjahr' => [(string) ($before['died_year'] ?? ''), (string) ($after['died_year'] ?? '')],
            'Verstorben, Datum/Jahr unbekannt' => [
                $yesNo(!empty($before['deceased_unknown'])),
                $yesNo(!empty($after['deceased_unknown'])),
            ],
            'Straße' => [(string) ($before['strasse'] ?? ''), (string) $after['strasse']],
            'PLZ' => [(string) ($before['plz'] ?? ''), (string) $after['plz']],
            'Ort' => [(string) ($before['ort'] ?? ''), (string) $after['ort']],
            'Land' => [(string) ($before['land'] ?? ''), (string) $after['land']],
            'Notiz' => [(string) ($before['note'] ?? ''), (string) $after['note']],
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
