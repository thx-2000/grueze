<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContactRepository;
use App\Repositories\LogRepository;
use PDO;
use RuntimeException;

final class ContactImportService
{
    public function __construct(
        private PDO $pdo,
        private ContactRepository $contacts,
        private LogRepository $logs,
        private XlsxReader $xlsx
    ) {
    }

    public function importRamaWorkbook(string $path, int $userId): array
    {
        $rows = $this->xlsx->readRows($path);
        if ($rows === []) {
            throw new RuntimeException('Die XLSX-Datei enthält keine importierbaren Zeilen.');
        }

        $requiredHeaders = ['Vorname', 'Geburtsname', 'Nachname akt.', 'Mail', 'Ort', 'Handy'];
        $availableHeaders = array_keys($rows[0]);
        foreach ($requiredHeaders as $header) {
            if (!in_array($header, $availableHeaders, true)) {
                throw new RuntimeException('In der XLSX-Datei fehlt die Spalte "' . $header . '".');
            }
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'without_email' => 0,
            'samples' => [],
        ];

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $normalized = $this->normalizeRow($row);
                if ($normalized === null) {
                    $summary['skipped']++;
                    continue;
                }

                if ($normalized['email'] === '') {
                    $summary['without_email']++;
                }

                $existing = $this->contacts->findImportMatch(
                    $normalized['vorname'],
                    $normalized['nachname'],
                    $normalized['geburtsname']
                );

                if ($existing) {
                    $this->contacts->update((int) $existing['id'], $this->mergedPayload($existing, $normalized), $userId);
                    $summary['updated']++;
                    continue;
                }

                $this->contacts->create($this->createPayload($normalized), $userId);
                $summary['created']++;
            }

            $details = sprintf(
                'XLSX-Import: %d Kontakte angelegt, %d Kontakte aktualisiert, %d Zeilen übersprungen, %d ohne Mailadresse.',
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
                $summary['without_email']
            );
            $this->logs->addAudit($userId, null, 'updated', $details);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $summary;
    }

    private function normalizeRow(array $row): ?array
    {
        $vorname = trim((string) ($row['Vorname'] ?? ''));
        $geburtsname = trim((string) ($row['Geburtsname'] ?? ''));
        $nachnameAktuell = trim((string) ($row['Nachname akt.'] ?? ''));
        $nachnameFallback = trim((string) ($row['Nachname'] ?? ''));
        $email = trim((string) (($row['Mail'] ?? '') !== '' ? $row['Mail'] : ($row['Mail '] ?? '')));
        $ort = trim((string) ($row['Ort'] ?? ''));
        $handy = trim((string) ($row['Handy'] ?? ''));

        if ($vorname === '' && $geburtsname === '' && $nachnameAktuell === '' && $email === '' && $ort === '' && $handy === '') {
            return null;
        }

        $nachname = $nachnameAktuell !== ''
            ? $nachnameAktuell
            : ($nachnameFallback !== '' ? $nachnameFallback : $geburtsname);

        if ($vorname === '' || $nachname === '') {
            return null;
        }

        return [
            'vorname' => $vorname,
            'geburtsname' => $geburtsname,
            'nachname' => $nachname,
            'nachname_aktuell' => $nachnameAktuell,
            'email' => $email,
            'ort' => $ort,
            'handy' => $handy,
        ];
    }

    private function createPayload(array $contact): array
    {
        return [
            'vorname' => $contact['vorname'],
            'nachname' => $contact['nachname'],
            'geburtsname' => $contact['geburtsname'],
            'anrede' => '',
            'category_id' => '',
            'geburtstag' => '',
            'strasse' => '',
            'plz' => '',
            'ort' => $contact['ort'],
            'land' => (string) config('defaults.country', 'Deutschland'),
            'notizen' => '',
            'photo_path' => '',
            'tag_ids' => [],
            'emails' => $contact['email'] !== '' ? [['email' => $contact['email'], 'label' => 'Import']] : [],
            'phones' => $contact['handy'] !== '' ? [['phone' => $contact['handy'], 'label' => 'Mobil']] : [],
        ];
    }

    private function mergedPayload(array $existing, array $imported): array
    {
        $emails = $existing['emails'] ?? [];
        if ($imported['email'] !== '' && !$this->containsEmail($emails, $imported['email'])) {
            array_unshift($emails, ['email' => $imported['email'], 'label' => 'Import']);
        }

        $phones = $existing['phones'] ?? [];
        if ($imported['handy'] !== '' && !$this->containsPhone($phones, $imported['handy'])) {
            array_unshift($phones, ['phone' => $imported['handy'], 'label' => 'Mobil']);
        }

        return [
            'vorname' => $imported['vorname'],
            'nachname' => $imported['nachname_aktuell'] !== '' || trim((string) ($existing['nachname'] ?? '')) === ''
                ? $imported['nachname']
                : (string) $existing['nachname'],
            'geburtsname' => $imported['geburtsname'] !== '' ? $imported['geburtsname'] : (string) ($existing['geburtsname'] ?? ''),
            'anrede' => (string) ($existing['anrede'] ?? ''),
            'category_id' => (string) ($existing['category_id'] ?? ''),
            'geburtstag' => (string) ($existing['geburtstag'] ?? ''),
            'strasse' => (string) ($existing['strasse'] ?? ''),
            'plz' => (string) ($existing['plz'] ?? ''),
            'ort' => $imported['ort'] !== '' ? $imported['ort'] : (string) ($existing['ort'] ?? ''),
            'land' => (string) ($existing['land'] ?: config('defaults.country', 'Deutschland')),
            'notizen' => (string) ($existing['notizen'] ?? ''),
            'photo_path' => (string) ($existing['photo_path'] ?? ''),
            'tag_ids' => array_map(static fn (array $tag): int => (int) $tag['id'], $existing['tags'] ?? []),
            'emails' => $emails,
            'phones' => $phones,
        ];
    }

    private function containsEmail(array $emails, string $candidate): bool
    {
        foreach ($emails as $email) {
            if (strcasecmp(trim((string) ($email['email'] ?? '')), $candidate) === 0) {
                return true;
            }
        }

        return false;
    }

    private function containsPhone(array $phones, string $candidate): bool
    {
        foreach ($phones as $phone) {
            if (trim((string) ($phone['phone'] ?? '')) === $candidate) {
                return true;
            }
        }

        return false;
    }
}
