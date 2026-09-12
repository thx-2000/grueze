<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * „Weitere Personen" (Instanz-Label z. B. „Lehrkräfte", siehe
 * `roster_label()`): eine zusätzliche Personenliste außerhalb des
 * Adressbuchs, für Menschen, die nie einen eigenen Kontakt-Eintrag hatten.
 * Bewusst komplett getrennt von Kontakten/Gruppen – landet nirgends in
 * Rundmails, Abstimmungen oder Geburtstagslisten.
 *
 * Ein Todesdatum spiegelt sich (über den Controller) als Eintrag auf der
 * Gedenkseite – die Person bleibt trotzdem hier in der Liste stehen, nur
 * mit einer Markierung. Anders als bei Kontakten braucht es dafür keinen
 * dritten „Ruhezustand": es gibt kein Adressbuch, aus dem die Person fallen
 * könnte.
 */
final class RosterRepository
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
                'CREATE TABLE IF NOT EXISTS roster_people (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(190) NOT NULL,
                    role_label VARCHAR(160) NULL,
                    email VARCHAR(190) NULL,
                    mobile VARCHAR(60) NULL,
                    born_on DATE NULL,
                    died_on DATE NULL,
                    strasse VARCHAR(190) NULL,
                    plz VARCHAR(20) NULL,
                    ort VARCHAR(120) NULL,
                    land VARCHAR(80) NULL,
                    note VARCHAR(500) NULL,
                    photo_path VARCHAR(255) NULL,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_roster_people_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            // Nicht darauf verlassen, dass MemorialRepository::ensureSchema() in
            // dieser Anfrage schon vorher lief (Konstruktions-Reihenfolge im
            // Container ist nicht garantiert) – die Spalte notfalls selbst nachziehen.
            $this->pdo->exec('ALTER TABLE memorials ADD COLUMN IF NOT EXISTS roster_person_id INT UNSIGNED NULL AFTER contact_id');
            $this->backfillFromFreeMemorials();
        } catch (\Throwable) {
            // Migration holt es nach.
        }
    }

    /**
     * Einmaliger Übernahme-Schritt: freie Gedenk-Einträge (kein Kontakt,
     * noch keine Person aus dieser Liste) gab es bisher nur, weil dafür
     * eigens der freie Eintrag auf der Gedenkseite gebaut wurde – meist für
     * Lehrkräfte. Die werden hier als Personen übernommen und zurückverlinkt,
     * damit nichts doppelt eingetragen werden muss. Läuft von selbst leer:
     * nach dem ersten Mal gibt es keine unverlinkten freien Einträge mehr.
     */
    private function backfillFromFreeMemorials(): void
    {
        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM memorials WHERE contact_id IS NULL AND roster_person_id IS NULL'
        )->fetchColumn();
        if ($count === 0) {
            return;
        }

        $rows = $this->pdo->query(
            'SELECT * FROM memorials WHERE contact_id IS NULL AND roster_person_id IS NULL'
        )->fetchAll();

        $insert = $this->pdo->prepare(
            'INSERT INTO roster_people (name, role_label, born_on, died_on, note, photo_path, created_by, created_at)
             VALUES (:name, :role, :born, :died, :note, :photo, :uid, :created)'
        );
        $link = $this->pdo->prepare('UPDATE memorials SET roster_person_id = :rid WHERE id = :id');

        foreach ($rows as $row) {
            $insert->execute([
                'name' => $row['display_name'],
                'role' => $row['role_label'],
                'born' => $row['born_on'],
                'died' => $row['died_on'],
                'note' => $row['note'],
                'photo' => $row['photo_path'],
                'uid' => $row['created_by'],
                'created' => $row['created_at'],
            ]);
            $link->execute(['rid' => (int) $this->pdo->lastInsertId(), 'id' => $row['id']]);
        }
    }

    /**
     * Alle Einträge, alphabetisch. Jeder Eintrag bekommt `age` (falls
     * Geburts-/Todesdatum bzw. -jahr bekannt) und `initial` (Platzhalter-
     * Buchstaben ohne Foto) wie bei den Gedenk-Einträgen.
     *
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT * FROM roster_people ORDER BY name ASC')->fetchAll();

        return array_map($this->decorate(...), $rows);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM roster_people WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->decorate($row) : null;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM roster_people')->fetchColumn();
    }

    /**
     * Vorschläge fürs Rollenfeld (Fach/Kurs o. Ä.) – bereits vergebene Werte
     * für die `<datalist>`, damit dieselbe Schreibweise wiederverwendet wird.
     *
     * @return list<string>
     */
    public function roleSuggestions(): array
    {
        $labels = $this->pdo->query(
            "SELECT DISTINCT role_label FROM roster_people WHERE role_label IS NOT NULL AND role_label <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);

        $labels = array_values(array_unique(array_map('strval', $labels)));
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return $labels;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO roster_people
                (name, role_label, email, mobile, born_on, died_on, strasse, plz, ort, land, note, photo_path, created_by)
             VALUES
                (:name, :role, :email, :mobile, :born, :died, :strasse, :plz, :ort, :land, :note, :photo, :uid)'
        );
        $stmt->execute($this->params($data) + ['uid' => $userId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = ['id' => $id];
        $map = $this->params($data);
        unset($map['photo']);
        foreach ($map as $key => $value) {
            $column = match ($key) {
                'role' => 'role_label',
                'born' => 'born_on',
                'died' => 'died_on',
                default => $key,
            };
            $fields[] = "$column = :$key";
            $params[$key] = $value;
        }
        if (array_key_exists('photo_path', $data)) {
            $fields[] = 'photo_path = :photo';
            $params['photo'] = $data['photo_path'] ?: null;
        }
        $stmt = $this->pdo->prepare('UPDATE roster_people SET ' . implode(', ', $fields) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM roster_people WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function params(array $data): array
    {
        $born = trim((string) ($data['born_on'] ?? ''));
        $died = trim((string) ($data['died_on'] ?? ''));

        return [
            'name' => mb_substr(trim((string) ($data['name'] ?? '')), 0, 190),
            'role' => ($r = mb_substr(trim((string) ($data['role_label'] ?? '')), 0, 160)) !== '' ? $r : null,
            'email' => ($e = mb_substr(trim((string) ($data['email'] ?? '')), 0, 190)) !== '' ? $e : null,
            'mobile' => ($m = mb_substr(trim((string) ($data['mobile'] ?? '')), 0, 60)) !== '' ? $m : null,
            'born' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $born) ? $born : null,
            'died' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $died) ? $died : null,
            'strasse' => ($s = mb_substr(trim((string) ($data['strasse'] ?? '')), 0, 190)) !== '' ? $s : null,
            'plz' => ($p = mb_substr(trim((string) ($data['plz'] ?? '')), 0, 20)) !== '' ? $p : null,
            'ort' => ($o = mb_substr(trim((string) ($data['ort'] ?? '')), 0, 120)) !== '' ? $o : null,
            'land' => ($l = mb_substr(trim((string) ($data['land'] ?? '')), 0, 80)) !== '' ? $l : null,
            'note' => ($n = mb_substr(trim((string) ($data['note'] ?? '')), 0, 500)) !== '' ? $n : null,
            'photo' => ($ph = trim((string) ($data['photo_path'] ?? ''))) !== '' ? $ph : null,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorate(array $row): array
    {
        $bornOn = trim((string) ($row['born_on'] ?? '')) ?: null;
        $diedOn = trim((string) ($row['died_on'] ?? '')) ?: null;

        $age = null;
        if ($bornOn !== null) {
            $b = \DateTimeImmutable::createFromFormat('Y-m-d', $bornOn) ?: null;
            $ref = $diedOn !== null ? \DateTimeImmutable::createFromFormat('Y-m-d', $diedOn) : new \DateTimeImmutable('today');
            if ($b !== null && $ref !== false && $ref !== null && $ref >= $b) {
                $age = $b->diff($ref)->y;
            }
        }

        $row['born_on'] = $bornOn;
        $row['died_on'] = $diedOn;
        $row['is_deceased'] = $diedOn !== null;
        $row['age'] = $age;
        $row['initial'] = person_initials(['name' => (string) $row['name']]);

        return $row;
    }
}
