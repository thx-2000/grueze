<?php

declare(strict_types=1);

namespace App\Services;

final class CsvExportService
{
    public function stream(array $contacts): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="kontakte.csv"');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Vorname', 'Nachname', 'Geburtsname', 'Kategorie', 'Tags', 'Geburtstag',
            'Straße', 'PLZ', 'Ort', 'Land', 'E-Mails', 'Telefonnummern', 'Notizen'
        ], ';');

        foreach ($contacts as $contact) {
            $emails = implode(' | ', array_map(
                static fn (array $email): string => trim(($email['label'] ? $email['label'] . ': ' : '') . $email['email']),
                $contact['emails']
            ));
            $phones = implode(' | ', array_map(
                static fn (array $phone): string => $phone['label'] . ': ' . $phone['phone'],
                $contact['phones']
            ));
            $tags = implode(' | ', array_map(
                static fn (array $tag): string => $tag['name'],
                $contact['tags'] ?? []
            ));

            fputcsv($out, [
                $contact['vorname'],
                $contact['nachname'],
                $contact['geburtsname'],
                $contact['category_name'],
                $tags,
                $contact['geburtstag'],
                $contact['strasse'],
                $contact['plz'],
                $contact['ort'],
                $contact['land'],
                $emails,
                $phones,
                $contact['notizen'],
            ], ';');
        }

        fclose($out);
        exit;
    }
}
