<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Sammelt alle Dateien ein, auf die die Datenbank verweist – Grundlage der
 * Dateiprüfung (`StorageCheckService`). Bereiche:
 *  - `uploads`   Profilbilder (Kontakte, Weitere Personen, Gedenkseiten) und
 *                Logo, Pfad relativ zu `public/`
 *  - `media`     Galerie-Medien inkl. Vorschau/Webversion, relativ zu `storage/media`
 *  - `documents` Dokumente inkl. Vorschau, relativ zu `storage/documents`
 *
 * Jede Quelle einzeln in try/catch: Instanzen ohne Galerie-/Dokument-
 * Migration haben die Tabellen schlicht nicht.
 *
 * Medien und Dokumente ohne existierende Galerie bzw. Ordner zählen nicht:
 * Wo die Tabellen per `ensureSchema()` ohne Fremdschlüssel entstanden sind,
 * bleiben nach dem endgültigen Löschen verwaiste Zeilen zurück, deren Dateien
 * absichtlich weg sind – sonst gäbe es Fehlalarme.
 */
final class StorageReferenceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{area: string, label: string, path: string}> */
    public function fileReferences(): array
    {
        $refs = [];

        $this->collect($refs, 'uploads',
            "SELECT TRIM(CONCAT(vorname, ' ', nachname)) AS label, photo_path AS path
             FROM contacts WHERE photo_path IS NOT NULL AND photo_path <> ''",
            static fn (array $r): string => 'Profilbild: ' . $r['label']);

        $this->collect($refs, 'uploads',
            "SELECT name AS label, photo_path AS path
             FROM roster_people WHERE photo_path IS NOT NULL AND photo_path <> ''",
            static fn (array $r): string => 'Weitere Personen: ' . $r['label']);

        $this->collect($refs, 'uploads',
            "SELECT display_name AS label, photo_path AS path
             FROM memorials WHERE photo_path IS NOT NULL AND photo_path <> ''",
            static fn (array $r): string => 'Gedenkseite: ' . $r['label']);

        $this->collect($refs, 'uploads',
            "SELECT '' AS label, setting_value AS path
             FROM app_settings WHERE setting_key = 'branding_logo_path' AND setting_value <> ''",
            static fn (array $r): string => 'Logo');

        foreach (['stored_path' => 'Original', 'thumb_path' => 'Vorschau', 'web_path' => 'Webversion'] as $column => $variant) {
            $this->collect($refs, 'media',
                "SELECT COALESCE(g.title, 'Auffangraum') AS label,
                        COALESCE(m.original_name, CONCAT('Medium ', m.id)) AS file_name,
                        m.{$column} AS path
                 FROM gallery_media m
                 LEFT JOIN galleries g ON g.id = m.gallery_id
                 WHERE (m.gallery_id IS NULL OR g.id IS NOT NULL)
                   AND m.{$column} IS NOT NULL AND m.{$column} <> ''",
                static fn (array $r): string => 'Galerie „' . $r['label'] . '": ' . $r['file_name'] . ' (' . $variant . ')');
        }

        foreach (['stored_path' => 'Datei', 'preview_path' => 'Vorschau'] as $column => $variant) {
            $this->collect($refs, 'documents',
                "SELECT d.title AS label, d.{$column} AS path
                 FROM documents d
                 JOIN document_folders f ON f.id = d.folder_id
                 WHERE d.{$column} IS NOT NULL AND d.{$column} <> ''",
                static fn (array $r): string => 'Dokument „' . $r['label'] . '" (' . $variant . ')');
        }

        return $refs;
    }

    /** @param list<array{area: string, label: string, path: string}> $refs */
    private function collect(array &$refs, string $area, string $sql, callable $label): void
    {
        try {
            foreach ($this->pdo->query($sql)->fetchAll() as $row) {
                $refs[] = ['area' => $area, 'label' => $label($row), 'path' => (string) $row['path']];
            }
        } catch (\Throwable) {
            // Tabelle/Spalte fehlt auf dieser Instanz – Bereich überspringen.
        }
    }
}
