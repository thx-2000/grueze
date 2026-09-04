<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;

/**
 * Gemeinsame Aufbereitung der Kontakt-Formularfelder. Wird von drei Stellen
 * genutzt, die dieselben Eingaben unterschiedlich weiterverarbeiten:
 *
 *  - `ContactController::sanitizePayload()`       – Verwaltung, volles Formular
 *  - `ContactController::sanitizeOwnProfilePayload()` – Selbst-Service „Mein Eintrag"
 *  - `DataCheckController::payload()`             – Daten-Check-Link ohne Login
 *
 * Hier liegt nur, was überall gleich ist: E-Mail-/Telefon-Zeilen säubern und
 * die Stammdaten-Skalare. Kategorie, Tags, Notizen und Login behandelt jede
 * Stelle selbst (Verwaltung aus dem Request, Selbst-Service aus dem Bestand).
 */
final class ContactInput
{
    /** „mailto:" und Steuerzeichen entfernen, trimmen. */
    public static function cleanEmail(string $value): string
    {
        $value = (string) preg_replace('/^\s*mailto:\s*/i', '', $value);
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $value);

        return trim($value);
    }

    /**
     * Webseite normalisieren: Steuerzeichen weg, trimmen, und eine bloße
     * Domain (ohne Schema) mit `https://` versehen, damit der gespeicherte
     * Wert immer ein benutzbarer Link ist.
     */
    public static function cleanWebsite(string $value): string
    {
        $value = trim((string) preg_replace('/[\x00-\x1F\x7F\s]+/', '', $value));
        if ($value === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return mb_substr($value, 0, 255);
    }

    /** Anrede-Code: `m`/`w` steuern „Lieber"/„Liebe", sonst leer (= „Hallo"). */
    public static function salutationCode(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['m', 'w'], true) ? $normalized : '';
    }

    /**
     * Wiederholbare E-Mail-Zeilen aus `emails[][email|label]`.
     *
     * @return list<array{email: string, label: string}>
     */
    public static function emails(Request $request): array
    {
        $out = [];
        foreach (($request->input('emails', []) ?: []) as $entry) {
            $email = self::cleanEmail((string) ($entry['email'] ?? ''));
            if ($email !== '') {
                $out[] = ['email' => $email, 'label' => trim((string) ($entry['label'] ?? ''))];
            }
        }

        return $out;
    }

    /**
     * Wiederholbare Telefon-Zeilen aus `phones[][phone|label]`.
     *
     * @return list<array{phone: string, label: string}>
     */
    public static function phones(Request $request): array
    {
        $out = [];
        foreach (($request->input('phones', []) ?: []) as $entry) {
            $phone = trim((string) preg_replace(['/^\s*tel:\s*/i', '/[\x00-\x1F\x7F]+/'], '', (string) ($entry['phone'] ?? '')));
            if ($phone !== '') {
                $out[] = ['phone' => $phone, 'label' => trim((string) ($entry['label'] ?? 'Sonstige'))];
            }
        }

        return $out;
    }

    /**
     * Die Stammdaten-Skalare, die in allen drei Formularen identisch sind.
     *
     * @return array<string,string>
     */
    public static function baseFields(Request $request): array
    {
        return [
            'vorname' => trim((string) $request->input('vorname')),
            'nachname' => trim((string) $request->input('nachname')),
            'geburtsname' => trim((string) $request->input('geburtsname')),
            'anrede' => self::salutationCode((string) $request->input('anrede')),
            'geburtstag' => (string) $request->input('geburtstag'),
            'beruf' => trim((string) $request->input('beruf')),
            'webseite' => self::cleanWebsite((string) $request->input('webseite')),
            'strasse' => trim((string) $request->input('strasse')),
            'plz' => trim((string) $request->input('plz')),
            'ort' => trim((string) $request->input('ort')),
            'land' => trim((string) $request->input('land', (string) config('defaults.country', 'Deutschland'))),
        ];
    }

    /**
     * Payload für den Selbst-Service (Mein Eintrag / Daten-Check-Link): nur die
     * selbst pflegbaren Felder aus dem Request, alles andere aus dem Bestand.
     *
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    public static function selfServiceFields(Request $request, array $existing): array
    {
        return self::baseFields($request) + [
            'category_id' => (string) ($existing['category_id'] ?? ''),
            'notizen' => (string) ($existing['notizen'] ?? ''),
            'photo_path' => (string) ($existing['photo_path'] ?? ''),
            'tag_ids' => array_map(static fn (array $tag): int => (int) $tag['id'], $existing['tags'] ?? []),
            'emails' => self::emails($request),
            'phones' => self::phones($request),
        ];
    }
}
