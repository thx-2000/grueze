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
            $sql .= ' AND (contacts.vorname LIKE :q OR contacts.nachname LIKE :q OR contacts.geburtsname LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND contacts.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        $allowedSorts = ['nachname', 'category_name'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'nachname';
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $sql .= $sort === 'category_name'
            ? " ORDER BY categories.name {$direction}, contacts.nachname ASC, contacts.vorname ASC"
            : " ORDER BY contacts.nachname {$direction}, contacts.vorname ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll();

        foreach ($contacts as &$contact) {
            $contact['emails'] = $this->emailsForContact((int) $contact['id']);
            $contact['phones'] = $this->phonesForContact((int) $contact['id']);
        }

        return $contacts;
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

        $contact['emails'] = $this->emailsForContact($id);
        $contact['phones'] = $this->phonesForContact($id);

        return $contact;
    }

    public function create(array $data, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contacts
            (vorname, nachname, geburtsname, category_id, geburtstag, strasse, plz, ort, land, notizen, photo_path, created_by, updated_by)
            VALUES
            (:vorname, :nachname, :geburtsname, :category_id, :geburtstag, :strasse, :plz, :ort, :land, :notizen, :photo_path, :created_by, :updated_by)'
        );
        $stmt->execute([
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'],
            'geburtsname' => $data['geburtsname'] ?: null,
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

        return $contactId;
    }

    public function update(int $id, array $data, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contacts SET
             vorname = :vorname,
             nachname = :nachname,
             geburtsname = :geburtsname,
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
            $contact['emails'] = $this->emailsForContact((int) $contact['id']);
            $contact['phones'] = $this->phonesForContact((int) $contact['id']);
        }

        return $contacts;
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
}

