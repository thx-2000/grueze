<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ContactRepository
{
    /** „Lebende" Kontakte: weder archiviert noch im Papierkorb. */
    private const LIVE = ' AND contacts.archived_at IS NULL AND contacts.deleted_at IS NULL';

    /** Tage, die ein Kontakt im Papierkorb bleibt, bevor er endgültig gelöscht wird. */
    public const TRASH_DAYS = 30;

    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /**
     * Archiv-/Papierkorb-Spalten notfalls selbst nachziehen – falls neuer Code
     * hochgeladen wurde, bevor „Verwaltung → Aktualisieren" gelaufen ist. Die
     * eigentliche Migration `2026-09-19-kontakt-papierkorb` bleibt maßgeblich;
     * das hier verhindert nur den 500er im Zeitfenster dazwischen.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'ALTER TABLE contacts
                    ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL,
                    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL,
                    ADD COLUMN IF NOT EXISTS retired_by INT UNSIGNED NULL'
            );
        } catch (\Throwable) {
            // Migration holt es nach; Repository bleibt lauffähig.
        }
    }

    /**
     * Baut die WHERE-Bedingungen für die Kontaktfilter (q, Kategorie, Tags,
     * ohne-Mail/ohne-Telefon). Rückgabe: ['sql' => ' AND …', 'params' => [...]].
     */
    private function filterClause(array $filters): array
    {
        $sql = '';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (contacts.vorname LIKE :q_vorname OR contacts.nachname LIKE :q_nachname OR contacts.geburtsname LIKE :q_geburtsname)';
            $query = '%' . $filters['q'] . '%';
            $params['q_vorname'] = $query;
            $params['q_nachname'] = $query;
            $params['q_geburtsname'] = $query;
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND contacts.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        $tagIds = array_values(array_filter(
            array_map('intval', (array) ($filters['tag_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        if ($tagIds !== []) {
            $placeholders = [];
            foreach ($tagIds as $index => $tagId) {
                $placeholder = 'tag_id_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $tagId;
            }
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM contact_tags
                WHERE contact_tags.contact_id = contacts.id
                AND contact_tags.tag_id IN (' . implode(', ', $placeholders) . ')
            )';
        }

        $groupIds = array_values(array_filter(
            array_map('intval', (array) ($filters['group_ids'] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        if ($groupIds !== []) {
            $placeholders = [];
            foreach ($groupIds as $index => $groupId) {
                $placeholder = 'group_id_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $groupId;
            }
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM contact_group_members
                WHERE contact_group_members.contact_id = contacts.id
                AND contact_group_members.group_id IN (' . implode(', ', $placeholders) . ')
            )';
        }

        if (!empty($filters['without_email'])) {
            $sql .= ' AND NOT EXISTS (
                SELECT 1 FROM contact_emails
                WHERE contact_emails.contact_id = contacts.id
                AND TRIM(COALESCE(contact_emails.email, "")) <> ""
            )';
        }

        if (!empty($filters['without_phone'])) {
            $sql .= ' AND NOT EXISTS (
                SELECT 1 FROM contact_phones
                WHERE contact_phones.contact_id = contacts.id
                AND TRIM(COALESCE(contact_phones.phone, "")) <> ""
            )';
        }

        return ['sql' => self::LIVE . $sql, 'params' => $params];
    }

    /**
     * Kontakt-IDs, die zum Filter passen UND mindestens eine Mailadresse haben.
     * Für den Rundmail-Empfängerkreis.
     */
    public function recipientIds(array $filters = []): array
    {
        $clause = $this->filterClause($filters);
        $sql = 'SELECT contacts.id
                FROM contacts
                LEFT JOIN categories ON categories.id = contacts.category_id
                WHERE 1=1' . $clause['sql'] . '
                AND EXISTS (
                    SELECT 1 FROM contact_emails
                    WHERE contact_emails.contact_id = contacts.id
                    AND TRIM(COALESCE(contact_emails.email, "")) <> ""
                )
                ORDER BY contacts.vorname ASC, contacts.nachname ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($clause['params']);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function search(array $filters = []): array
    {
        $clause = $this->filterClause($filters);
        $sql = 'SELECT contacts.*, categories.name AS category_name
                FROM contacts
                LEFT JOIN categories ON categories.id = contacts.category_id
                WHERE 1=1' . $clause['sql'];
        $params = $clause['params'];

        $allowedSorts = ['nachname', 'vorname', 'category_name', 'ort', 'geburtstag', 'created_at', 'tags', 'groups'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'vorname';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $firstTag = '(SELECT MIN(t.name) FROM contact_tags ct JOIN tags t ON t.id = ct.tag_id WHERE ct.contact_id = contacts.id)';
        $firstGroup = '(SELECT MIN(g.name) FROM contact_group_members m JOIN contact_groups g ON g.id = m.group_id WHERE m.contact_id = contacts.id)';
        $sortSql = match ($sort) {
            'vorname' => "contacts.vorname {$direction}, contacts.nachname ASC",
            'category_name' => "categories.name {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'ort' => "contacts.ort {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'geburtstag' => "contacts.geburtstag {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'created_at' => "contacts.created_at {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            // Kontakte ohne Tag/Gruppe (NULL) sortieren ans Ende.
            'tags' => "{$firstTag} IS NULL, {$firstTag} {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'groups' => "{$firstGroup} IS NULL, {$firstGroup} {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            default => "contacts.nachname {$direction}, contacts.vorname ASC",
        };
        $sql .= " ORDER BY {$sortSql}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll();

        foreach ($contacts as &$contact) {
            $this->hydrateContact($contact);
        }

        return $contacts;
    }

    public function globalSearch(string $query, int $limit = 12): array
    {
        $term = '%' . trim($query) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT contacts.id, contacts.vorname, contacts.nachname, contacts.geburtsname, contacts.ort,
                    categories.name AS category_name,
                    EXISTS(
                        SELECT 1 FROM contact_emails WHERE contact_emails.contact_id = contacts.id
                    ) AS has_email
             FROM contacts
             LEFT JOIN categories ON categories.id = contacts.category_id
             WHERE (contacts.vorname LIKE :term_vorname
                OR contacts.nachname LIKE :term_nachname
                OR contacts.geburtsname LIKE :term_geburtsname
                OR contacts.ort LIKE :term_ort)
                AND contacts.archived_at IS NULL AND contacts.deleted_at IS NULL
             ORDER BY contacts.vorname ASC, contacts.nachname ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([
            'term_vorname' => $term,
            'term_nachname' => $term,
            'term_geburtsname' => $term,
            'term_ort' => $term,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Kennzahlen für die Startseite.
     *
     * @return array{total:int, without_email:int, without_phone:int}
     */
    public function stats(): array
    {
        $row = $this->pdo->query(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM contact_emails ce
                    WHERE ce.contact_id = contacts.id AND TRIM(COALESCE(ce.email, "")) <> ""
                ) THEN 1 ELSE 0 END) AS without_email,
                SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM contact_phones cp
                    WHERE cp.contact_id = contacts.id AND TRIM(COALESCE(cp.phone, "")) <> ""
                ) THEN 1 ELSE 0 END) AS without_phone
             FROM contacts
             WHERE contacts.archived_at IS NULL AND contacts.deleted_at IS NULL'
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'without_email' => (int) ($row['without_email'] ?? 0),
            'without_phone' => (int) ($row['without_phone'] ?? 0),
        ];
    }

    public function mailingContactIds(): array
    {
        return array_map(
            'intval',
            $this->pdo->query(
                'SELECT DISTINCT contacts.id
                 FROM contacts
                 JOIN contact_emails ON contact_emails.contact_id = contacts.id
                 WHERE contact_emails.email IS NOT NULL AND contact_emails.email <> ""
                   AND contacts.archived_at IS NULL AND contacts.deleted_at IS NULL
                 ORDER BY contacts.vorname ASC, contacts.nachname ASC'
            )->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    /** Kontakt-ID zu einer Mailadresse (erste Übereinstimmung), sonst null. */
    public function findIdByEmail(string $email): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT ce.contact_id
             FROM contact_emails ce
             JOIN contacts c ON c.id = ce.contact_id
             WHERE LOWER(TRIM(ce.email)) = LOWER(TRIM(:email)) AND c.deleted_at IS NULL
             ORDER BY ce.id LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Kontakte mit hinterlegtem Geburtstag (für die Geburtstagsgrüße).
     *
     * @return list<array{id:int,vorname:string,nachname:string,geburtstag:string,email:?string}>
     */
    public function withBirthdays(): array
    {
        return $this->pdo->query(
            'SELECT contacts.id, contacts.vorname, contacts.nachname, contacts.geburtstag,
                    (SELECT email FROM contact_emails WHERE contact_emails.contact_id = contacts.id ORDER BY contact_emails.id LIMIT 1) AS email
             FROM contacts
             WHERE contacts.geburtstag IS NOT NULL
               AND contacts.archived_at IS NULL AND contacts.deleted_at IS NULL'
        )->fetchAll();
    }

    /**
     * Kontakte, die heute Geburtstag haben UND eine Mailadresse hinterlegt
     * haben – für den automatischen Geburtstagsversand.
     *
     * @return list<array{id:int, vorname:string, nachname:string, email:string}>
     */
    public function birthdaysToday(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.id, c.vorname, c.nachname,
                    (SELECT email FROM contact_emails ce WHERE ce.contact_id = c.id ORDER BY ce.id LIMIT 1) AS email
             FROM contacts c
             WHERE c.geburtstag IS NOT NULL
               AND c.archived_at IS NULL AND c.deleted_at IS NULL
               AND DATE_FORMAT(c.geburtstag, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d')"
        );

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '') {
                $out[] = [
                    'id' => (int) $row['id'],
                    'vorname' => (string) $row['vorname'],
                    'nachname' => (string) $row['nachname'],
                    'email' => $email,
                ];
            }
        }

        return $out;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contacts.*, categories.name AS category_name
             FROM contacts
             LEFT JOIN categories ON categories.id = contacts.category_id
             WHERE contacts.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $contact = $stmt->fetch();

        if (!$contact) {
            return null;
        }

        $this->hydrateContact($contact);

        return $contact;
    }

    public function findImportMatch(string $vorname, string $nachname, string $geburtsname): ?array
    {
        $variants = [
            [
                'sql' => 'SELECT id FROM contacts
                          WHERE vorname = :vorname AND nachname = :nachname AND COALESCE(geburtsname, \'\') = :geburtsname
                          AND deleted_at IS NULL
                          LIMIT 1',
                'params' => [
                    'vorname' => $vorname,
                    'nachname' => $nachname,
                    'geburtsname' => $geburtsname,
                ],
            ],
            [
                'sql' => 'SELECT id FROM contacts
                          WHERE vorname = :vorname AND nachname = :nachname
                          AND deleted_at IS NULL
                          LIMIT 1',
                'params' => [
                    'vorname' => $vorname,
                    'nachname' => $nachname,
                ],
            ],
        ];

        if ($geburtsname !== '') {
            $variants[] = [
                'sql' => 'SELECT id FROM contacts
                          WHERE vorname = :vorname AND geburtsname = :geburtsname
                          AND deleted_at IS NULL
                          LIMIT 1',
                'params' => [
                    'vorname' => $vorname,
                    'geburtsname' => $geburtsname,
                ],
            ];
        }

        foreach ($variants as $variant) {
            $stmt = $this->pdo->prepare($variant['sql']);
            $stmt->execute($variant['params']);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                return $this->find($id);
            }
        }

        return null;
    }

    public function create(array $data, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contacts
            (vorname, nachname, geburtsname, geschlecht, category_id, geburtstag, strasse, plz, ort, land, notizen, photo_path, created_by, updated_by)
            VALUES
            (:vorname, :nachname, :geburtsname, :geschlecht, :category_id, :geburtstag, :strasse, :plz, :ort, :land, :notizen, :photo_path, :created_by, :updated_by)'
        );
        $stmt->execute([
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'],
            'geburtsname' => $data['geburtsname'] ?: null,
            'geschlecht' => $data['geschlecht'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'geburtstag' => $data['geburtstag'] ?: null,
            'strasse' => $data['strasse'],
            'plz' => $data['plz'],
            'ort' => $data['ort'],
            'land' => $data['land'] ?: null,
            'notizen' => $data['notizen'] ?: null,
            'photo_path' => $data['photo_path'] ?: null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $contactId = (int) $this->pdo->lastInsertId();
        $this->syncEmails($contactId, $data['emails'] ?? []);
        $this->syncPhones($contactId, $data['phones'] ?? []);
        $this->syncTags($contactId, $data['tag_ids'] ?? []);

        return $contactId;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contacts SET
             vorname = :vorname,
             nachname = :nachname,
             geburtsname = :geburtsname,
             geschlecht = :geschlecht,
             category_id = :category_id,
             geburtstag = :geburtstag,
             strasse = :strasse,
             plz = :plz,
             ort = :ort,
             land = :land,
             notizen = :notizen,
             photo_path = :photo_path,
             updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'],
            'geburtsname' => $data['geburtsname'] ?: null,
            'geschlecht' => $data['geschlecht'] ?: null,
            'category_id' => $data['category_id'] ?: null,
            'geburtstag' => $data['geburtstag'] ?: null,
            'strasse' => $data['strasse'],
            'plz' => $data['plz'],
            'ort' => $data['ort'],
            'land' => $data['land'] ?: null,
            'notizen' => $data['notizen'] ?: null,
            'photo_path' => $data['photo_path'] ?: null,
            'updated_by' => $userId,
        ]);

        $this->syncEmails($id, $data['emails'] ?? []);
        $this->syncPhones($id, $data['phones'] ?? []);
        $this->syncTags($id, $data['tag_ids'] ?? []);
    }

    /** Kontakt ins Archiv legen – ruht dauerhaft, wird nie automatisch gelöscht. */
    public function archive(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contacts SET archived_at = NOW(), deleted_at = NULL, retired_by = :uid WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /** Kontakt in den Papierkorb legen – nach TRASH_DAYS Tagen endgültig weg. */
    public function trash(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contacts SET deleted_at = NOW(), archived_at = NULL, retired_by = :uid WHERE id = :id'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    /** Kontakt aus Archiv oder Papierkorb zurückholen. */
    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contacts SET archived_at = NULL, deleted_at = NULL, retired_by = NULL WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /** Kontakt endgültig aus der Datenbank entfernen. */
    public function purge(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Archivierte und im Papierkorb liegende Kontakte – für die Übersicht
     * „Archiv & Papierkorb".
     *
     * @return array{archived: list<array<string,mixed>>, trashed: list<array<string,mixed>>}
     */
    public function retired(): array
    {
        $rows = $this->pdo->query(
            'SELECT contacts.*, categories.name AS category_name,
                    u.name AS retired_by_name,
                    DATEDIFF(DATE_ADD(contacts.deleted_at, INTERVAL ' . self::TRASH_DAYS . ' DAY), NOW()) AS purge_in_days
             FROM contacts
             LEFT JOIN categories ON categories.id = contacts.category_id
             LEFT JOIN users u ON u.id = contacts.retired_by
             WHERE contacts.archived_at IS NOT NULL OR contacts.deleted_at IS NOT NULL
             ORDER BY COALESCE(contacts.deleted_at, contacts.archived_at) DESC'
        )->fetchAll();

        $out = ['archived' => [], 'trashed' => []];
        foreach ($rows as &$row) {
            $this->hydrateContact($row);
            $out[$row['deleted_at'] !== null ? 'trashed' : 'archived'][] = $row;
        }

        return $out;
    }

    /** IDs, die lange genug im Papierkorb liegen und endgültig gelöscht werden. */
    public function idsToPurge(int $days = self::TRASH_DAYS): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM contacts WHERE deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL :days DAY)'
        );
        $stmt->execute(['days' => $days]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Papierkorb aufräumen: alles endgültig löschen, was älter als TRASH_DAYS ist. */
    public function pruneTrashedContacts(int $days = self::TRASH_DAYS): int
    {
        $ids = $this->idsToPurge($days);
        foreach ($ids as $id) {
            $this->purge($id);
        }

        return count($ids);
    }

    /** @return array{archived:int, trashed:int} */
    public function retiredCounts(): array
    {
        $row = $this->pdo->query(
            'SELECT
                SUM(archived_at IS NOT NULL) AS archived,
                SUM(deleted_at IS NOT NULL) AS trashed
             FROM contacts'
        )->fetch();

        return [
            'archived' => (int) ($row['archived'] ?? 0),
            'trashed' => (int) ($row['trashed'] ?? 0),
        ];
    }

    public function findManyByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT contacts.*, categories.name AS category_name
             FROM contacts
             LEFT JOIN categories ON categories.id = contacts.category_id
             WHERE contacts.id IN ({$placeholders})
             ORDER BY contacts.nachname, contacts.vorname"
        );
        $stmt->execute(array_map('intval', $ids));
        $contacts = $stmt->fetchAll();

        foreach ($contacts as &$contact) {
            $this->hydrateContact($contact);
        }

        return $contacts;
    }

    public function applyBulkUpdate(
        array $contactIds,
        bool $changeCategory,
        ?int $categoryId,
        bool $categoryOnlyIfEmpty,
        array $tagIdsToAdd,
        array $tagIdsToRemove,
        int $userId
    ): int
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn (int $id): bool => $id > 0)));
        $tagIdsToAdd = array_values(array_unique(array_filter(array_map('intval', $tagIdsToAdd), static fn (int $id): bool => $id > 0)));
        $tagIdsToRemove = array_values(array_unique(array_filter(array_map('intval', $tagIdsToRemove), static fn (int $id): bool => $id > 0)));

        if ($contactIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($contactIds), '?'));
        $touched = false;

        $this->pdo->beginTransaction();
        try {
            if ($changeCategory) {
                $sql = "UPDATE contacts
                        SET category_id = ?, updated_by = ?
                        WHERE id IN ({$placeholders})";
                $params = [$categoryId, $userId, ...$contactIds];

                if ($categoryOnlyIfEmpty && $categoryId !== null) {
                    $sql .= ' AND category_id IS NULL';
                }

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                $touched = true;
            }

            if ($tagIdsToAdd !== []) {
                $tagStmt = $this->pdo->prepare(
                    'INSERT IGNORE INTO contact_tags (contact_id, tag_id) VALUES (:contact_id, :tag_id)'
                );

                foreach ($contactIds as $contactId) {
                    foreach ($tagIdsToAdd as $tagId) {
                        $tagStmt->execute([
                            'contact_id' => $contactId,
                            'tag_id' => $tagId,
                        ]);
                    }
                }
                $touched = true;
            }

            if ($tagIdsToRemove !== []) {
                $tagPlaceholders = implode(',', array_fill(0, count($tagIdsToRemove), '?'));
                $deleteStmt = $this->pdo->prepare(
                    "DELETE FROM contact_tags
                     WHERE contact_id IN ({$placeholders})
                     AND tag_id IN ({$tagPlaceholders})"
                );
                $deleteStmt->execute([
                    ...$contactIds,
                    ...$tagIdsToRemove,
                ]);
                $touched = true;
            }

            if ($touched) {
                $touchStmt = $this->pdo->prepare(
                    "UPDATE contacts
                     SET updated_by = ?
                     WHERE id IN ({$placeholders})"
                );
                $touchStmt->execute([
                    $userId,
                    ...$contactIds,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return count($contactIds);
    }

    private function hydrateContact(array &$contact): void
    {
        $contactId = (int) $contact['id'];
        $contact['emails'] = $this->emailsForContact($contactId);
        $contact['phones'] = $this->phonesForContact($contactId);
        $contact['tags'] = $this->tagsForContact($contactId);
        $contact['groups'] = $this->groupsForContact($contactId);
        $contact['linked_user'] = $this->linkedUserForContact($contactId);
    }

    private function emailsForContact(int $contactId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contact_emails WHERE contact_id = :contact_id ORDER BY id');
        $stmt->execute(['contact_id' => $contactId]);
        return $stmt->fetchAll();
    }

    private function phonesForContact(int $contactId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contact_phones WHERE contact_id = :contact_id ORDER BY id');
        $stmt->execute(['contact_id' => $contactId]);
        return $stmt->fetchAll();
    }

    private function tagsForContact(int $contactId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tags.*
             FROM tags
             JOIN contact_tags ON contact_tags.tag_id = tags.id
             WHERE contact_tags.contact_id = :contact_id
             ORDER BY tags.name'
        );
        $stmt->execute(['contact_id' => $contactId]);

        return $stmt->fetchAll();
    }

    /** @return list<array{id:int,name:string}> Gruppen dieses Kontakts */
    private function groupsForContact(int $contactId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT g.id, g.name
                 FROM contact_groups g
                 JOIN contact_group_members m ON m.group_id = g.id
                 WHERE m.contact_id = :contact_id
                 ORDER BY g.name'
            );
            $stmt->execute(['contact_id' => $contactId]);

            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    private function linkedUserForContact(int $contactId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT users.id, users.email, users.role_id, users.is_active, roles.name AS role_name
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.contact_id = :contact_id
             LIMIT 1'
        );
        $stmt->execute(['contact_id' => $contactId]);

        return $stmt->fetch() ?: null;
    }

    private function syncEmails(int $contactId, array $emails): void
    {
        $this->pdo->prepare('DELETE FROM contact_emails WHERE contact_id = :contact_id')->execute(['contact_id' => $contactId]);
        $stmt = $this->pdo->prepare('INSERT INTO contact_emails (contact_id, email, label) VALUES (:contact_id, :email, :label)');

        foreach ($emails as $email) {
            if (trim((string) ($email['email'] ?? '')) === '') {
                continue;
            }
            $stmt->execute([
                'contact_id' => $contactId,
                'email' => $email['email'],
                'label' => $email['label'] ?: null,
            ]);
        }
    }

    private function syncPhones(int $contactId, array $phones): void
    {
        $this->pdo->prepare('DELETE FROM contact_phones WHERE contact_id = :contact_id')->execute(['contact_id' => $contactId]);
        $stmt = $this->pdo->prepare('INSERT INTO contact_phones (contact_id, phone, label) VALUES (:contact_id, :phone, :label)');

        foreach ($phones as $phone) {
            if (trim((string) ($phone['phone'] ?? '')) === '') {
                continue;
            }
            $stmt->execute([
                'contact_id' => $contactId,
                'phone' => $phone['phone'],
                'label' => $phone['label'] ?: 'Sonstige',
            ]);
        }
    }

    private function syncTags(int $contactId, array $tagIds): void
    {
        $this->pdo->prepare('DELETE FROM contact_tags WHERE contact_id = :contact_id')->execute(['contact_id' => $contactId]);
        $stmt = $this->pdo->prepare('INSERT INTO contact_tags (contact_id, tag_id) VALUES (:contact_id, :tag_id)');

        $tagIds = array_unique(array_filter(array_map('intval', $tagIds), static fn (int $id): bool => $id > 0));
        foreach ($tagIds as $tagId) {
            $stmt->execute([
                'contact_id' => $contactId,
                'tag_id' => $tagId,
            ]);
        }
    }
}
