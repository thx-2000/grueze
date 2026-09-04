<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Termine: reine Ankündigungsseite (Titel, Zeitraum, Freitext-Info, Links).
 * Sichtbarkeit „alle angemeldeten Personen" (Standard) oder eingeschränkt auf
 * bestimmte Personen/Gruppen/Tags (`announcement_audience`). Links (extern,
 * Dokument, Abstimmung) stehen in `announcement_links` mit vorberechneter URL.
 */
final class AnnouncementRepository
{
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS announcements (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(190) NOT NULL,
                    info TEXT NULL,
                    location VARCHAR(190) NULL,
                    starts_at DATE NULL,
                    ends_at DATE NULL,
                    audience_mode ENUM(\'all\', \'restricted\') NOT NULL DEFAULT \'all\',
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_announcements_starts (starts_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS announcement_audience (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    announcement_id INT UNSIGNED NOT NULL,
                    kind ENUM(\'contact\', \'group\', \'tag\') NOT NULL,
                    ref_id INT UNSIGNED NOT NULL,
                    KEY idx_announcement_audience_announcement (announcement_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS announcement_links (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    announcement_id INT UNSIGNED NOT NULL,
                    label VARCHAR(190) NOT NULL,
                    kind ENUM(\'extern\', \'dokument\', \'abstimmung\') NOT NULL DEFAULT \'extern\',
                    url VARCHAR(500) NOT NULL,
                    position INT NOT NULL DEFAULT 0,
                    KEY idx_announcement_links_announcement (announcement_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(bool $past = false): array
    {
        $sql = 'SELECT * FROM announcements
                WHERE ' . ($past
                    ? 'COALESCE(ends_at, starts_at) < CURDATE()'
                    : '(starts_at IS NULL OR COALESCE(ends_at, starts_at) >= CURDATE())')
            . ' ORDER BY starts_at IS NULL, starts_at ' . ($past ? 'DESC' : 'ASC') . ', id DESC';

        return $this->pdo->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $row['links'] = $this->linksFor($id);

        return $row;
    }

    public function create(array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO announcements (title, info, location, starts_at, ends_at, audience_mode, created_by)
             VALUES (:title, :info, :location, :starts_at, :ends_at, :audience_mode, :created_by)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'info' => ($data['info'] ?? '') !== '' ? $data['info'] : null,
            'location' => ($data['location'] ?? '') !== '' ? $data['location'] : null,
            'starts_at' => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            'ends_at' => ($data['ends_at'] ?? '') !== '' ? $data['ends_at'] : null,
            'audience_mode' => $data['audience_mode'] ?? 'all',
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE announcements
             SET title = :title, info = :info, location = :location, starts_at = :starts_at,
                 ends_at = :ends_at, audience_mode = :audience_mode
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'info' => ($data['info'] ?? '') !== '' ? $data['info'] : null,
            'location' => ($data['location'] ?? '') !== '' ? $data['location'] : null,
            'starts_at' => ($data['starts_at'] ?? '') !== '' ? $data['starts_at'] : null,
            'ends_at' => ($data['ends_at'] ?? '') !== '' ? $data['ends_at'] : null,
            'audience_mode' => $data['audience_mode'] ?? 'all',
        ]);
    }

    /** Endgültiges Löschen – kein Papierkorb (wie bei Gruppen/Dokumenten). */
    public function delete(int $id): void
    {
        // Kein DB-FK von galleries/document_folders auf announcements
        // (Tabellenreihenfolge in schema.sql, wie bei events.group_id) –
        // die Verlinkung hier von Hand lösen, sonst bliebe eine tote ID stehen.
        $this->pdo->prepare('UPDATE galleries SET announcement_id = NULL WHERE announcement_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('UPDATE document_folders SET announcement_id = NULL WHERE announcement_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM announcement_audience WHERE announcement_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM announcement_links WHERE announcement_id = :id')->execute(['id' => $id]);
        $this->pdo->prepare('DELETE FROM announcements WHERE id = :id')->execute(['id' => $id]);
    }

    // ------------------------------------------------------------ Sichtbarkeit

    /** @return list<array{kind:string,ref_id:int}> */
    public function audienceFor(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT kind, ref_id FROM announcement_audience WHERE announcement_id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    /**
     * Menschenlesbare Beschreibung der Einschränkung – für den Verwaltungs-
     * Hinweis („sichtbar für: …"), Admin sieht ja trotzdem immer alles.
     *
     * @return list<string>
     */
    public function audienceLabels(int $id): array
    {
        // Named Platzhalter dürfen bei nativen Prepared Statements
        // (PDO::ATTR_EMULATE_PREPARES = false) nicht mehrfach vorkommen –
        // daher drei eigene Platzhalter statt dreimal :id.
        $stmt = $this->pdo->prepare(
            "SELECT 'Person' AS kind, TRIM(CONCAT(c.vorname, ' ', c.nachname)) AS name
             FROM announcement_audience aa
             JOIN contacts c ON c.id = aa.ref_id AND aa.kind = 'contact'
             WHERE aa.announcement_id = :id1
             UNION ALL
             SELECT 'Gruppe', cg.name
             FROM announcement_audience aa
             JOIN contact_groups cg ON cg.id = aa.ref_id AND aa.kind = 'group'
             WHERE aa.announcement_id = :id2
             UNION ALL
             SELECT 'Tag', t.name
             FROM announcement_audience aa
             JOIN tags t ON t.id = aa.ref_id AND aa.kind = 'tag'
             WHERE aa.announcement_id = :id3"
        );
        $stmt->execute(['id1' => $id, 'id2' => $id, 'id3' => $id]);

        return array_map(
            static fn (array $r): string => $r['kind'] . ' „' . $r['name'] . '"',
            $stmt->fetchAll()
        );
    }

    /**
     * Sichtbarkeit ersetzen (immer alle löschen, dann neu einfügen – einfacher
     * als ein Diff bei maximal ein paar Dutzend Zeilen).
     *
     * @param list<array{kind:string,ref_id:int}> $rows
     */
    public function replaceAudience(int $id, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM announcement_audience WHERE announcement_id = :id')->execute(['id' => $id]);
        if ($rows === []) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO announcement_audience (announcement_id, kind, ref_id) VALUES (:id, :kind, :ref_id)'
        );
        foreach ($rows as $row) {
            $stmt->execute(['id' => $id, 'kind' => $row['kind'], 'ref_id' => $row['ref_id']]);
        }
    }

    /**
     * Prüft, ob eine Person diese Ankündigung sehen darf (Admin/Verwaltung
     * prüft das nicht – die sehen ohnehin immer alles).
     *
     * @param list<int> $memberGroupIds
     * @param list<int> $tagIds
     */
    public function isVisibleTo(array $announcement, int $contactId, array $memberGroupIds, array $tagIds): bool
    {
        if (($announcement['audience_mode'] ?? 'all') !== 'restricted') {
            return true;
        }
        foreach ($this->audienceFor((int) $announcement['id']) as $row) {
            if ($row['kind'] === 'contact' && (int) $row['ref_id'] === $contactId) {
                return true;
            }
            if ($row['kind'] === 'group' && in_array((int) $row['ref_id'], $memberGroupIds, true)) {
                return true;
            }
            if ($row['kind'] === 'tag' && in_array((int) $row['ref_id'], $tagIds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Live-Vorschau für den Sichtbarkeits-Picker: wie viele (lebende) Personen
     * träfe die aktuelle Auswahl? Leere Auswahl = „alle" (siehe sanitize()).
     *
     * @param list<int> $contactIds
     * @param list<int> $groupIds
     * @param list<int> $tagIds
     */
    public function matchingContactCount(array $contactIds, array $groupIds, array $tagIds): int
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn (int $id): bool => $id > 0)));
        $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn (int $id): bool => $id > 0)));
        $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn (int $id): bool => $id > 0)));

        if ($contactIds === [] && $groupIds === [] && $tagIds === []) {
            return (int) $this->pdo->query(
                'SELECT COUNT(*) FROM contacts WHERE archived_at IS NULL AND deleted_at IS NULL'
            )->fetchColumn();
        }

        $conditions = [];
        $params = [];

        if ($contactIds !== []) {
            $conditions[] = 'c.id IN (' . implode(',', array_fill(0, count($contactIds), '?')) . ')';
            $params = array_merge($params, $contactIds);
        }
        if ($groupIds !== []) {
            $conditions[] = 'EXISTS (SELECT 1 FROM contact_group_members cgm WHERE cgm.contact_id = c.id AND cgm.group_id IN ('
                . implode(',', array_fill(0, count($groupIds), '?')) . '))';
            $params = array_merge($params, $groupIds);
        }
        if ($tagIds !== []) {
            $conditions[] = 'EXISTS (SELECT 1 FROM contact_tags ct WHERE ct.contact_id = c.id AND ct.tag_id IN ('
                . implode(',', array_fill(0, count($tagIds), '?')) . '))';
            $params = array_merge($params, $tagIds);
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contacts c
             WHERE c.archived_at IS NULL AND c.deleted_at IS NULL AND (' . implode(' OR ', $conditions) . ')'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // ------------------------------------------------------------------ Links

    /** @return list<array<string,mixed>> */
    public function linksFor(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM announcement_links WHERE announcement_id = :id ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    /** @param list<array{label:string,kind:string,url:string}> $rows */
    public function replaceLinks(int $id, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM announcement_links WHERE announcement_id = :id')->execute(['id' => $id]);
        if ($rows === []) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO announcement_links (announcement_id, label, kind, url, position)
             VALUES (:id, :label, :kind, :url, :position)'
        );
        $position = 0;
        foreach ($rows as $row) {
            $stmt->execute([
                'id' => $id,
                'label' => $row['label'],
                'kind' => $row['kind'],
                'url' => $row['url'],
                'position' => $position++,
            ]);
        }
    }
}
