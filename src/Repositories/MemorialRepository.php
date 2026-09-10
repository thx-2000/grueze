<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Gedenk-Einträge für die „In Memoriam"-Seite. Ein Eintrag ist entweder mit
 * einem Kontakt verknüpft (`contact_id` – Name, Foto, Geburtsjahr kommen dann
 * aus dem Kontakt) oder frei (Person stand nie im Adressbuch, z. B. Lehrkräfte).
 */
final class MemorialRepository
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
                'CREATE TABLE IF NOT EXISTS memorials (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    contact_id INT UNSIGNED NULL,
                    display_name VARCHAR(190) NOT NULL,
                    role_label VARCHAR(120) NULL,
                    born_year SMALLINT UNSIGNED NULL,
                    born_on DATE NULL,
                    died_year SMALLINT UNSIGNED NULL,
                    died_on DATE NULL,
                    note VARCHAR(500) NULL,
                    photo_path VARCHAR(255) NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_memorials_contact (contact_id),
                    KEY idx_memorials_year (died_year)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec('ALTER TABLE memorials ADD COLUMN IF NOT EXISTS born_on DATE NULL AFTER born_year');
            $this->pdo->exec('ALTER TABLE contacts ADD COLUMN IF NOT EXISTS deceased_at DATE NULL');
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    private const SELECT = 'SELECT m.*,
                   c.vorname AS c_vorname, c.nachname AS c_nachname, c.geburtsname AS c_geburtsname,
                   c.photo_path AS c_photo_path, c.geburtstag AS c_geburtstag,
                   cat.name AS c_category,
                   c.archived_at AS c_archived_at, c.deleted_at AS c_deleted_at
            FROM memorials m
            LEFT JOIN contacts c ON c.id = m.contact_id
            LEFT JOIN categories cat ON cat.id = c.category_id';

    /**
     * Alle Einträge, nach Bereichs-Label gruppiert (ohne Label → Schlüssel "").
     * Innerhalb: nach Sterbejahr absteigend, dann Name.
     *
     * @return array<string, list<array<string,mixed>>>
     */
    public function grouped(): array
    {
        $rows = $this->pdo->query(
            self::SELECT . ' ORDER BY m.died_year DESC, m.died_on DESC, m.display_name ASC'
        )->fetchAll();

        $groups = [];
        foreach ($rows as $row) {
            $entry = $this->decorate($row);
            $groups[$entry['group_key']][] = $entry;
        }

        // Gruppen alphabetisch, „ohne Label" ans Ende.
        uksort($groups, static function (string $a, string $b): int {
            if ($a === '') {
                return 1;
            }
            if ($b === '') {
                return -1;
            }

            return strcasecmp($a, $b);
        });

        return $groups;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE m.id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->decorate($row) : null;
    }

    /** @return array<string,mixed>|null */
    public function forContact(int $contactId): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE m.contact_id = :cid LIMIT 1');
        $stmt->execute(['cid' => $contactId]);
        $row = $stmt->fetch();

        return $row ? $this->decorate($row) : null;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM memorials')->fetchColumn();
    }

    /**
     * Vorschläge fürs Bereichs-Label: bereits vergebene Labels plus die
     * Kontakt-Kategorien (damit „Stufe 95" & Co. konsistent geschrieben werden).
     *
     * @return list<string>
     */
    public function roleSuggestions(): array
    {
        $labels = $this->pdo->query(
            "SELECT role_label FROM memorials WHERE role_label IS NOT NULL AND role_label <> ''
             UNION
             SELECT name FROM categories WHERE name IS NOT NULL AND name <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);

        $labels = array_values(array_unique(array_map('strval', $labels)));
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /**
     * Eintrag aus einem Kontakt anlegen (idempotent – vorhandenen aktualisieren).
     *
     * @param array<string,mixed> $contact  Datensatz aus ContactRepository::find()
     */
    public function upsertFromContact(array $contact, ?string $diedOn, string $note, int $userId): int
    {
        $existing = $this->forContact((int) $contact['id']);

        $bornYear = null;
        $bornOn = null;
        $geb = trim((string) ($contact['geburtstag'] ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $geb, $mm)) {
            $bornYear = (int) $mm[1];
            // Nur ein vollständiges Datum (kein 0000er-Platzhalter) übernehmen.
            if ($mm[2] !== '00' && $mm[3] !== '00' && (int) $mm[1] >= 1900) {
                $bornOn = $mm[1] . '-' . $mm[2] . '-' . $mm[3];
            }
        }

        $diedOn = $diedOn !== null && $diedOn !== '' ? $diedOn : null;
        $diedYear = $diedOn !== null && preg_match('/^(\d{4})/', $diedOn, $dm)
            ? (int) $dm[1]
            : (int) date('Y');

        $name = trim(($contact['vorname'] ?? '') . ' ' . ($contact['nachname'] ?? ''));
        $note = mb_substr(trim($note), 0, 500);

        if ($existing !== null) {
            $this->update((int) $existing['id'], [
                'display_name' => $name,
                'role_label' => $existing['role_label'] ?? ($contact['category_name'] ?? null),
                'born_year' => $bornYear,
                'born_on' => $bornOn,
                'died_year' => $diedYear,
                'died_on' => $diedOn,
                'note' => $note !== '' ? $note : ($existing['note'] ?? null),
            ]);

            return (int) $existing['id'];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO memorials (contact_id, display_name, role_label, born_year, born_on, died_year, died_on, note, created_by)
             VALUES (:cid, :name, :role, :born, :bornon, :dyear, :don, :note, :uid)'
        );
        $stmt->execute([
            'cid' => (int) $contact['id'],
            'name' => $name,
            'role' => trim((string) ($contact['category_name'] ?? '')) ?: null,
            'born' => $bornYear,
            'bornon' => $bornOn,
            'dyear' => $diedYear,
            'don' => $diedOn,
            'note' => $note !== '' ? $note : null,
            'uid' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Freier Eintrag (kein Kontakt). @param array<string,mixed> $data */
    public function createFree(array $data, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO memorials (contact_id, display_name, role_label, born_year, born_on, died_year, died_on, note, photo_path, created_by)
             VALUES (NULL, :name, :role, :born, :bornon, :dyear, :don, :note, :photo, :uid)'
        );
        $stmt->execute($this->params($data) + ['uid' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id];
        foreach (['display_name', 'role_label', 'born_year', 'born_on', 'died_year', 'died_on', 'note', 'photo_path'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[] = "$key = :$key";
                $params[$key] = $data[$key] === '' ? null : $data[$key];
            }
        }
        if ($fields === []) {
            return;
        }
        $stmt = $this->pdo->prepare('UPDATE memorials SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM memorials WHERE id = :id')->execute(['id' => $id]);
    }

    public function deleteForContact(int $contactId): void
    {
        $this->pdo->prepare('DELETE FROM memorials WHERE contact_id = :cid')->execute(['cid' => $contactId]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function params(array $data): array
    {
        $born = (int) ($data['born_year'] ?? 0);
        $died = (int) ($data['died_year'] ?? 0);
        $diedOn = trim((string) ($data['died_on'] ?? ''));
        $bornOn = trim((string) ($data['born_on'] ?? ''));
        $bornOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $bornOn) ? $bornOn : null;

        // Volles Geburtsdatum eingetragen, aber kein Jahr → Jahr daraus ableiten.
        if ($born < 1900 && $bornOn !== null) {
            $born = (int) substr($bornOn, 0, 4);
        }

        return [
            'name' => mb_substr(trim((string) ($data['display_name'] ?? '')), 0, 190),
            'role' => ($r = mb_substr(trim((string) ($data['role_label'] ?? '')), 0, 120)) !== '' ? $r : null,
            'born' => $born >= 1900 && $born <= 2100 ? $born : null,
            'bornon' => $bornOn,
            'dyear' => $died >= 1900 && $died <= 2100 ? $died : ($diedOn !== '' ? (int) substr($diedOn, 0, 4) : null),
            'don' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $diedOn) ? $diedOn : null,
            'note' => ($n = mb_substr(trim((string) ($data['note'] ?? '')), 0, 500)) !== '' ? $n : null,
            'photo' => ($p = trim((string) ($data['photo_path'] ?? ''))) !== '' ? $p : null,
        ];
    }

    /**
     * Ergänzt einen Roh-Datensatz um Anzeigewerte: Name/Foto/Jahr aus dem
     * Kontakt ziehen, wenn nicht am Eintrag selbst gesetzt.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorate(array $row): array
    {
        $linked = $row['contact_id'] !== null;

        $name = trim((string) $row['display_name']);
        if ($linked) {
            $name = trim(($row['c_vorname'] ?? '') . ' ' . ($row['c_nachname'] ?? '')) ?: $name;
        }

        $bornYear = $row['born_year'] !== null ? (int) $row['born_year'] : null;
        $diedYear = $row['died_year'] !== null ? (int) $row['died_year'] : null;
        $diedOn = trim((string) ($row['died_on'] ?? '')) ?: null;
        if ($diedYear === null && $diedOn !== null) {
            $diedYear = (int) substr($diedOn, 0, 4);
        }

        // Volles Geburtsdatum: am Eintrag selbst, sonst (bei Kontakt) aus dem Kontakt.
        $bornOn = trim((string) ($row['born_on'] ?? ''));
        if ($bornOn === '' && $linked) {
            $bornOn = trim((string) ($row['c_geburtstag'] ?? ''));
        }
        $bornOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $bornOn) && strpos($bornOn, '-00') === false
            ? $bornOn
            : null;
        if ($bornOn !== null && $bornYear === null) {
            $bornYear = (int) substr($bornOn, 0, 4);
        }

        // Lebensalter: exakt bei zwei vollen Daten, sonst grobe Jahresdifferenz.
        $age = null;
        $ageExact = false;
        if ($bornOn !== null && $diedOn !== null) {
            $b = \DateTimeImmutable::createFromFormat('Y-m-d', $bornOn) ?: null;
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $diedOn) ?: null;
            if ($b !== null && $d !== null && $d >= $b) {
                $age = $b->diff($d)->y;
                $ageExact = true;
            }
        }
        if ($age === null && $bornYear !== null && $diedYear !== null && $diedYear >= $bornYear) {
            $age = $diedYear - $bornYear;
        }

        $photo = trim((string) ($row['photo_path'] ?? ''));
        if ($photo === '' && $linked) {
            $photo = trim((string) ($row['c_photo_path'] ?? ''));
        }

        $birthName = '';
        if ($linked) {
            $gn = trim((string) ($row['c_geburtsname'] ?? ''));
            $nn = trim((string) ($row['c_nachname'] ?? ''));
            if ($gn !== '' && $gn !== $nn) {
                $birthName = '(ehem. ' . $gn . ')';
            }
        }

        $roleLabel = trim((string) ($row['role_label'] ?? ''));
        if ($roleLabel === '' && $linked) {
            $roleLabel = trim((string) ($row['c_category'] ?? ''));
        }

        // Platzhalter-Initialen (Vorname + Nachname), s. person_initials().
        $initial = $linked
            ? person_initials(['vorname' => $row['c_vorname'] ?? '', 'nachname' => $row['c_nachname'] ?? ''])
            : person_initials(['name' => $name]);

        return [
            'id' => (int) $row['id'],
            'contact_id' => $linked ? (int) $row['contact_id'] : null,
            'contact_reachable' => $linked && $row['c_archived_at'] === null && $row['c_deleted_at'] === null,
            'name' => $name,
            'initial' => $initial,
            'birth_name' => $birthName,
            'role_label' => $roleLabel,
            'group_key' => $roleLabel,
            'born_year' => $bornYear,
            'born_on' => $bornOn,
            'died_year' => $diedYear,
            'died_on' => $diedOn,
            'age' => $age,
            'age_exact' => $ageExact,
            'note' => trim((string) ($row['note'] ?? '')) ?: null,
            'photo_path' => $photo ?: null,
        ];
    }
}
