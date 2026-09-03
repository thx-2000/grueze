<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Exportiert Kontakte als vCard (.vcf, Version 3.0 – von Apple Kontakte,
 * Google Kontakte, Outlook und Android/iOS gleichermaßen verstanden).
 */
final class VCardService
{
    /**
     * @param list<array<string,mixed>> $contacts
     */
    public function stream(array $contacts, string $filename = 'kontakte.vcf'): never
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'kontakte.vcf';

        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Robots-Tag: noindex, nofollow');

        foreach ($contacts as $contact) {
            echo $this->card($contact);
        }
        exit;
    }

    /** Eine einzelne Karte als String (auch für „nur dieser Kontakt"). */
    public function card(array $contact): string
    {
        $vorname = trim((string) ($contact['vorname'] ?? ''));
        $nachname = trim((string) ($contact['nachname'] ?? ''));
        $full = trim($vorname . ' ' . $nachname);

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . $this->esc($nachname) . ';' . $this->esc($vorname) . ';;;',
            'FN:' . $this->esc($full !== '' ? $full : 'Unbenannt'),
        ];

        $geburtsname = trim((string) ($contact['geburtsname'] ?? ''));
        if ($geburtsname !== '' && $geburtsname !== $nachname) {
            // Als „maiden name" gibt es kein Standardfeld – X-Property nutzen.
            $lines[] = 'X-MAIDENNAME:' . $this->esc($geburtsname);
        }

        $bday = trim((string) ($contact['geburtstag'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) {
            $lines[] = 'BDAY:' . $bday;
        }

        foreach (($contact['emails'] ?? []) as $email) {
            $value = trim((string) ($email['email'] ?? ''));
            if ($value !== '') {
                $lines[] = 'EMAIL;TYPE=INTERNET:' . $this->esc($value);
            }
        }

        foreach (($contact['phones'] ?? []) as $phone) {
            $value = trim((string) ($phone['phone'] ?? ''));
            if ($value === '') {
                continue;
            }
            $lines[] = 'TEL;TYPE=' . $this->telType((string) ($phone['label'] ?? '')) . ':' . $this->esc($value);
        }

        $strasse = trim((string) ($contact['strasse'] ?? ''));
        $plz = trim((string) ($contact['plz'] ?? ''));
        $ort = trim((string) ($contact['ort'] ?? ''));
        $land = trim((string) ($contact['land'] ?? ''));
        if ($strasse !== '' || $plz !== '' || $ort !== '') {
            // ADR: PostOfficeBox;ExtendedAddress;Street;Locality;Region;PostalCode;Country
            $lines[] = 'ADR;TYPE=home:;;' . $this->esc($strasse) . ';' . $this->esc($ort) . ';;' . $this->esc($plz) . ';' . $this->esc($land);
        }

        $category = trim((string) ($contact['category_name'] ?? ''));
        $tagNames = array_map(static fn (array $t): string => (string) ($t['name'] ?? ''), $contact['tags'] ?? []);
        $categories = array_values(array_filter(array_merge([$category], $tagNames), static fn (string $s): bool => $s !== ''));
        if ($categories !== []) {
            $lines[] = 'CATEGORIES:' . implode(',', array_map([$this, 'esc'], $categories));
        }

        $notes = trim((string) ($contact['notizen'] ?? ''));
        if ($notes !== '') {
            $lines[] = 'NOTE:' . $this->esc($notes);
        }

        $lines[] = 'REV:' . gmdate('Ymd\THis\Z');
        $lines[] = 'END:VCARD';

        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    private function telType(string $label): string
    {
        $l = mb_strtolower(trim($label));

        return match (true) {
            str_contains($l, 'mobil'), str_contains($l, 'handy'), str_contains($l, 'cell') => 'CELL',
            str_contains($l, 'arbeit'), str_contains($l, 'büro'), str_contains($l, 'work'), str_contains($l, 'geschäft') => 'WORK',
            str_contains($l, 'privat'), str_contains($l, 'home') => 'HOME',
            str_contains($l, 'fax') => 'FAX',
            default => 'VOICE',
        };
    }

    private function esc(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\n', '\\,', '\\;'],
            $value
        );
    }

    /** Zeilenfaltung bei 75 Oktetten (RFC 6350). */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $chunk = 75;
        while (strlen($line) > $chunk) {
            $out .= substr($line, 0, $chunk) . "\r\n ";
            $line = substr($line, $chunk);
            $chunk = 74;
        }

        return $out . $line;
    }
}
