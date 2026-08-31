<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ContactRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function search(array $filters = []): array
    {
        $sql = 'SELECT contacts.*, categories.name AS category_name
                FROM contacts
                LEFT JOIN categories ON categories.id = contacts.category_id
                WHERE 1=1';
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

        $allowedSorts = ['nachname', 'vorname', 'category_name', 'ort', 'geburtstag', 'created_at'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'vorname';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sortSql = match ($sort) {
            'vorname' => "contacts.vorname {$direction}, contacts.nachname ASC",
            'category_name' => "categories.name {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'ort' => "contacts.ort {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'geburtstag' => "contacts.geburtstag {$direction}, contacts.nachname ASC, contacts.vorname ASC",
            'created_at' => "contacts.created_at {$direction}, contacts.nachname ASC, contacts.vorname ASC",
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
             WHERE contacts.vorname LIKE :term_vorname
                OR contacts.nachname LIKE :term_nachname
                OR contacts.geburtsname LIKE :term_geburtsname
                OR contacts.ort LIKE :term_ort
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
             FROM contacts'
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
                 ORDER BY contacts.vorname ASC, contacts.nachname ASC'
            )->fetchAll(\PDO::FETCH_COLUMN)
        );
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

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute(['id' => $id]);
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
