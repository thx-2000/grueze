<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ContactRepository;
use PDO;

/**
 * Dubletten-Finder und Zusammenführen. Beides gehörte lange in den
 * ContactRepository, ist aber eigenständige Logik (Clustering per Union-Find
 * bzw. eine mehrstufige Transaktion über etliche Tabellen) und liegt daher
 * hier. Für die Anzeige der Cluster wird der Repository nur zum Hydrieren
 * genutzt (`findManyByIds`).
 */
final class ContactMergeService
{
    public function __construct(
        private PDO $pdo,
        private ContactRepository $contacts,
    ) {
    }

    // -------------------------------------------------------- Dubletten

    /**
     * Mögliche Doppel-Einträge: Kontakte mit gleichem Namen ODER gleicher
     * Mailadresse werden zu Clustern zusammengefasst. Nur „lebende" Kontakte.
     *
     * @return list<array{reason: string, contacts: list<array<string,mixed>>}>
     */
    public function duplicateClusters(): array
    {
        $out = [];
        foreach ($this->duplicateClusterIds() as $cluster) {
            $out[] = [
                'reason' => match ($cluster['flag']) {
                    1 => 'Gleicher Name',
                    2 => 'Gleiche Mailadresse',
                    default => 'Gleicher Name und gleiche Mailadresse',
                },
                'contacts' => $this->contacts->findManyByIds($cluster['ids']),
            ];
        }

        usort($out, static fn (array $a, array $b): int => count($b['contacts']) <=> count($a['contacts']));

        return $out;
    }

    public function duplicateClusterCount(): int
    {
        return count($this->duplicateClusterIds());
    }

    /**
     * Cluster nur als ID-Listen (ohne Hydrierung) – Basis für Anzeige und Zähler.
     *
     * @return list<array{ids: list<int>, flag: int}>
     */
    private function duplicateClusterIds(): array
    {
        // Kandidaten-Paare sammeln: [id1, id2] => Grund-Bitmaske (1 = Name, 2 = Mail).
        $links = [];
        $addLink = static function (int $a, int $b, int $flag) use (&$links): void {
            if ($a === $b) {
                return;
            }
            [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
            $links[$lo . '-' . $hi] = ($links[$lo . '-' . $hi] ?? 0) | $flag;
        };

        $nameRows = $this->pdo->query(
            "SELECT LOWER(TRIM(CONCAT(vorname, ' ', nachname))) AS k, GROUP_CONCAT(id) AS ids
             FROM contacts
             WHERE archived_at IS NULL AND deleted_at IS NULL
               AND TRIM(CONCAT(vorname, nachname)) <> ''
             GROUP BY k HAVING COUNT(*) > 1"
        )->fetchAll();
        foreach ($nameRows as $row) {
            $ids = array_map('intval', explode(',', (string) $row['ids']));
            foreach ($ids as $i => $a) {
                foreach (array_slice($ids, $i + 1) as $b) {
                    $addLink($a, $b, 1);
                }
            }
        }

        $mailRows = $this->pdo->query(
            "SELECT LOWER(TRIM(ce.email)) AS k, GROUP_CONCAT(DISTINCT ce.contact_id) AS ids
             FROM contact_emails ce
             JOIN contacts c ON c.id = ce.contact_id
             WHERE c.archived_at IS NULL AND c.deleted_at IS NULL
               AND TRIM(COALESCE(ce.email, '')) <> ''
             GROUP BY k HAVING COUNT(DISTINCT ce.contact_id) > 1"
        )->fetchAll();
        foreach ($mailRows as $row) {
            $ids = array_map('intval', explode(',', (string) $row['ids']));
            foreach ($ids as $i => $a) {
                foreach (array_slice($ids, $i + 1) as $b) {
                    $addLink($a, $b, 2);
                }
            }
        }

        if ($links === []) {
            return [];
        }

        // Union-Find über alle beteiligten IDs.
        $parent = [];
        $find = static function (int $x) use (&$parent): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }

            return $x;
        };
        foreach ($links as $key => $flag) {
            [$a, $b] = array_map('intval', explode('-', $key));
            $parent[$a] ??= $a;
            $parent[$b] ??= $b;
        }
        foreach ($links as $key => $flag) {
            [$a, $b] = array_map('intval', explode('-', $key));
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        }
        $clusterFlags = [];
        foreach ($links as $key => $flag) {
            [$a] = array_map('intval', explode('-', $key));
            $root = $find($a);
            $clusterFlags[$root] = ($clusterFlags[$root] ?? 0) | $flag;
        }

        $groups = [];
        foreach (array_keys($parent) as $id) {
            $groups[$find((int) $id)][] = (int) $id;
        }

        $out = [];
        foreach ($groups as $root => $ids) {
            sort($ids);
            $out[] = ['ids' => $ids, 'flag' => $clusterFlags[$root] ?? 0];
        }

        return $out;
    }

    // ------------------------------------------------------ Zusammenführen

    /**
     * Zwei Kontakte zu einem verschmelzen: alles vom „secondary" wandert in den
     * „primary", danach wird der secondary gelöscht. Skalarfelder gewinnt der
     * primary; leere Felder werden aus dem secondary aufgefüllt, Notizen
     * zusammengeführt. Läuft in einer Transaktion.
     *
     * @return array{filled: list<string>, note: string}
     */
    public function merge(int $primaryId, int $secondaryId, int $userId): array
    {
        if ($primaryId === $secondaryId) {
            return ['filled' => [], 'note' => ''];
        }

        $primary = $this->rawRow($primaryId);
        $secondary = $this->rawRow($secondaryId);
        if ($primary === null || $secondary === null) {
            return ['filled' => [], 'note' => ''];
        }

        $this->pdo->beginTransaction();
        try {
            // 1) Skalarfelder auffüllen.
            $fillable = [
                'geburtsname' => 'Geburtsname', 'anrede' => 'Anrede', 'category_id' => 'Kategorie',
                'geburtstag' => 'Geburtstag', 'beruf' => 'Beruf/Tätigkeit', 'webseite' => 'Webseite',
                'strasse' => 'Straße', 'plz' => 'PLZ', 'ort' => 'Ort', 'land' => 'Land', 'photo_path' => 'Foto',
            ];
            $set = [];
            $params = ['id' => $primaryId];
            $filled = [];
            foreach ($fillable as $col => $label) {
                if (trim((string) ($primary[$col] ?? '')) === '' && trim((string) ($secondary[$col] ?? '')) !== '') {
                    $set[] = "$col = :$col";
                    $params[$col] = $secondary[$col];
                    $filled[] = $label;
                }
            }
            $primaryNotes = trim((string) ($primary['notizen'] ?? ''));
            $secondaryNotes = trim((string) ($secondary['notizen'] ?? ''));
            if ($secondaryNotes !== '' && $secondaryNotes !== $primaryNotes) {
                $set[] = 'notizen = :notizen';
                $params['notizen'] = $primaryNotes === '' ? $secondaryNotes : $primaryNotes . "\n\n" . $secondaryNotes;
                $filled[] = 'Notizen';
            }
            $set[] = 'updated_by = :updated_by';
            $params['updated_by'] = $userId;
            $this->pdo->prepare('UPDATE contacts SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);

            // 2) Kontaktwege – Dubletten überspringen, Rest übernehmen.
            $this->mergeChildRows('contact_emails', 'email', $primaryId, $secondaryId);
            $this->mergeChildRows('contact_phones', 'phone', $primaryId, $secondaryId);

            // 3) Tags (PK contact_id+tag_id).
            $this->pdo->prepare('INSERT IGNORE INTO contact_tags (contact_id, tag_id) SELECT :p, tag_id FROM contact_tags WHERE contact_id = :s')
                ->execute(['p' => $primaryId, 's' => $secondaryId]);

            // 4) Gruppen – Mitgliedschaft vereinen, Leitung behalten.
            $this->safeExec(
                'INSERT IGNORE INTO contact_group_members (group_id, contact_id, role, added_at)
                 SELECT group_id, :p, role, added_at FROM contact_group_members WHERE contact_id = :s',
                ['p' => $primaryId, 's' => $secondaryId]
            );
            $this->safeExec(
                "UPDATE contact_group_members m
                 JOIN contact_group_members s ON s.group_id = m.group_id AND s.contact_id = :s AND s.role = 'lead'
                 SET m.role = 'lead' WHERE m.contact_id = :p",
                ['p' => $primaryId, 's' => $secondaryId]
            );
            $this->safeExec(
                'INSERT IGNORE INTO contact_group_join_requests (group_id, contact_id, message, created_at)
                 SELECT group_id, :p, message, created_at FROM contact_group_join_requests WHERE contact_id = :s',
                ['p' => $primaryId, 's' => $secondaryId]
            );

            // 5) Termin-Teilnahmen – nur übernehmen, wo der primary noch nicht dabei ist.
            $this->pdo->prepare(
                'DELETE FROM event_participants
                 WHERE contact_id = :s
                   AND event_id IN (SELECT event_id FROM (SELECT event_id FROM event_participants WHERE contact_id = :p) t)'
            )->execute(['p' => $primaryId, 's' => $secondaryId]);
            $this->pdo->prepare('UPDATE event_participants SET contact_id = :p WHERE contact_id = :s')
                ->execute(['p' => $primaryId, 's' => $secondaryId]);

            // 6) Verknüpfter Login – nur übernehmen, wenn der primary keinen hat.
            $note = '';
            $primaryHasUser = (bool) $this->pdo->query('SELECT 1 FROM users WHERE contact_id = ' . $primaryId . ' LIMIT 1')->fetchColumn();
            $secondaryHasUser = (bool) $this->pdo->query('SELECT 1 FROM users WHERE contact_id = ' . $secondaryId . ' LIMIT 1')->fetchColumn();
            if ($secondaryHasUser && !$primaryHasUser) {
                $this->pdo->prepare('UPDATE users SET contact_id = :p WHERE contact_id = :s')
                    ->execute(['p' => $primaryId, 's' => $secondaryId]);
            } elseif ($secondaryHasUser && $primaryHasUser) {
                $note = 'Beide Kontakte hatten einen Zugang – der des zusammengeführten Kontakts ist jetzt ohne Verknüpfung und kann unter „Zugänge" neu verbunden oder deaktiviert werden.';
            }

            // 7) Verlauf & sonstige Verweise auf den primary umbiegen.
            $this->pdo->prepare('UPDATE audit_log SET contact_id = :p WHERE contact_id = :s')->execute(['p' => $primaryId, 's' => $secondaryId]);
            $this->pdo->prepare('UPDATE mail_log SET contact_id = :p WHERE contact_id = :s')->execute(['p' => $primaryId, 's' => $secondaryId]);
            $this->safeExec('UPDATE registration_invites SET contact_id = :p WHERE contact_id = :s', ['p' => $primaryId, 's' => $secondaryId]);
            $this->safeExec('DELETE FROM contact_data_checks WHERE contact_id = :s', ['s' => $secondaryId]);

            // 8) secondary entfernen (Reste per FK-CASCADE).
            $this->pdo->prepare('DELETE FROM contacts WHERE id = :id')->execute(['id' => $secondaryId]);

            $this->pdo->commit();

            return ['filled' => $filled, 'note' => $note];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed>|null */
    private function rawRow(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /** Kind-Zeilen (E-Mail/Telefon) übernehmen, exakte Dubletten weglassen. */
    private function mergeChildRows(string $table, string $valueCol, int $primaryId, int $secondaryId): void
    {
        $this->pdo->prepare(
            "DELETE FROM {$table}
             WHERE contact_id = :s
               AND LOWER(TRIM({$valueCol})) IN (
                   SELECT v FROM (SELECT LOWER(TRIM({$valueCol})) v FROM {$table} WHERE contact_id = :p) t
               )"
        )->execute(['p' => $primaryId, 's' => $secondaryId]);
        $this->pdo->prepare("UPDATE {$table} SET contact_id = :p WHERE contact_id = :s")
            ->execute(['p' => $primaryId, 's' => $secondaryId]);
    }

    /** Statement ausführen, das auf noch nicht migrierten Instanzen fehlen darf. */
    private function safeExec(string $sql, array $params): void
    {
        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (\Throwable) {
            // Tabelle (z. B. Gruppen) auf dieser Instanz noch nicht vorhanden.
        }
    }
}
