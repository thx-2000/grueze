<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Termine / Terminfindung. Ein Termin hat Datumsoptionen, einen Teilnehmerkreis
 * (Kontakte aus dem Adressbuch, je mit personengebundenem Token) und
 * Antworten (ja/vielleicht/nein je Teilnehmer und Option).
 */
final class EventRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(bool $includeArchived = false): array
    {
        $sql = 'SELECT events.*, users.name AS creator_name,
                    (SELECT COUNT(*) FROM event_participants WHERE event_participants.event_id = events.id) AS participant_count,
                    (SELECT MIN(option_date) FROM event_options WHERE event_options.event_id = events.id AND option_date IS NOT NULL) AS earliest_date
                FROM events
                JOIN users ON users.id = events.created_by';
        if (!$includeArchived) {
            $sql .= " WHERE events.status <> 'archived'";
        }
        $sql .= ' ORDER BY events.status = \'decided\' ASC, COALESCE(earliest_date, \'9999-12-31\') ASC, events.created_at DESC';

        $events = $this->pdo->query($sql)->fetchAll();
        foreach ($events as &$event) {
            $event['options'] = $this->optionsForEvent((int) $event['id']);
            $event['tally'] = $this->tally((int) $event['id']);
            $event['answered_count'] = $this->answeredParticipantCount((int) $event['id']);
        }

        return $events;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT events.*, users.name AS creator_name
             FROM events JOIN users ON users.id = events.created_by
             WHERE events.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $event = $stmt->fetch();
        if (!$event) {
            return null;
        }

        $event['options'] = $this->optionsForEvent($id);
        $event['participants'] = $this->participantsForEvent($id);
        $event['tally'] = $this->tally($id);
        $event['answered_count'] = $this->answeredParticipantCount($id);
        $event['token_stats'] = $this->tokenHitStats($id);
        $event['response_log'] = $this->responseLog($id);

        return $event;
    }

    /** @return array<int, string> contact_id → token */
    public function tokensForEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare('SELECT contact_id, token FROM event_participants WHERE event_id = :event_id');
        $stmt->execute(['event_id' => $eventId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['contact_id']] = (string) $row['token'];
        }

        return $out;
    }

    /**
     * Kontakt-IDs des Teilnehmerkreises nach Filter:
     * - „all": alle Teilnehmer
     * - „confirmed": „Ja" beim festgelegten Termin (sonst: mindestens einmal geantwortet)
     * - „pending": noch keine Rückmeldung
     *
     * @return list<int>
     */
    public function participantContactIds(int $eventId, string $filter = 'all'): array
    {
        $event = $this->pdo->prepare('SELECT decided_option_id FROM events WHERE id = :id');
        $event->execute(['id' => $eventId]);
        $decidedOptionId = (int) ($event->fetchColumn() ?: 0);

        $participants = $this->participantsForEvent($eventId);
        $ids = [];
        foreach ($participants as $participant) {
            $keep = match ($filter) {
                'confirmed' => $decidedOptionId > 0
                    ? ($participant['answers'][$decidedOptionId] ?? '') === 'yes'
                    : $participant['has_answered'],
                'pending' => !$participant['has_answered'],
                default => true,
            };
            if ($keep) {
                $ids[] = (int) $participant['contact_id'];
            }
        }

        return $ids;
    }

    /** @return list<array<string, mixed>> Verlauf, neueste zuerst */
    public function responseLog(int $eventId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.answer, l.via, l.created_at,
                    c.vorname, c.nachname,
                    eo.option_date, eo.option_time
             FROM event_response_log l
             JOIN event_participants ep ON ep.id = l.participant_id
             JOIN contacts c ON c.id = ep.contact_id
             JOIN event_options eo ON eo.id = l.option_id
             WHERE ep.event_id = :event_id
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['event_id' => $eventId]);

        return $stmt->fetchAll();
    }

    public function create(array $data, int $userId): int
    {
        $kind = in_array($data['kind'] ?? '', ['date_poll', 'fixed_date', 'poll'], true) ? $data['kind'] : 'date_poll';
        $stmt = $this->pdo->prepare(
            'INSERT INTO events (title, kind, description, location, time_note, cost_note, bring_note,
                 closes_at, result_recipients, created_by)
             VALUES (:title, :kind, :description, :location, :time_note, :cost_note, :bring_note,
                 :closes_at, :result_recipients, :created_by)'
        );
        $stmt->execute([
            'title' => $data['title'],
            'kind' => $kind,
            'description' => $data['description'] ?: null,
            'location' => $data['location'] ?: null,
            'time_note' => $data['time_note'] ?: null,
            'cost_note' => $data['cost_note'] ?: null,
            'bring_note' => $data['bring_note'] ?: null,
            'closes_at' => $this->normalizeClosesAt($data['closes_at'] ?? null),
            'result_recipients' => $this->normalizeResultRecipients($data['result_recipients'] ?? null),
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateDetails(int $id, array $data): void
    {
        $current = $this->pdo->prepare('SELECT closes_at FROM events WHERE id = :id');
        $current->execute(['id' => $id]);
        $oldClosesAt = (string) ($current->fetchColumn() ?: '');
        $newClosesAt = $this->normalizeClosesAt($data['closes_at'] ?? null);

        $stmt = $this->pdo->prepare(
            'UPDATE events SET title = :title, description = :description, location = :location,
                 time_note = :time_note, cost_note = :cost_note, bring_note = :bring_note,
                 closes_at = :closes_at, result_recipients = :result_recipients
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'location' => $data['location'] ?: null,
            'time_note' => $data['time_note'] ?: null,
            'cost_note' => $data['cost_note'] ?: null,
            'bring_note' => $data['bring_note'] ?: null,
            'closes_at' => $newClosesAt,
            'result_recipients' => $this->normalizeResultRecipients($data['result_recipients'] ?? null),
        ]);

        // Neue Frist gesetzt oder verschoben: anstehende Automatik-Mails wieder
        // scharf schalten (Erinnerung + Ergebnisversand).
        if ($newClosesAt !== null && $newClosesAt !== $oldClosesAt) {
            $this->pdo->prepare(
                'UPDATE events SET reminder_sent_at = NULL, result_mail_sent_at = NULL WHERE id = :id'
            )->execute(['id' => $id]);
        }
    }

    /**
     * Datumsoptionen abgleichen. Bestehende Optionen, die weiter vorkommen
     * (gleiches Datum + Uhrzeit-Text), bleiben samt Antworten erhalten.
     *
     * @param list<array{date: string, time: string}> $options
     */
    public function syncDateOptions(int $eventId, array $options): void
    {
        $existing = $this->pdo->prepare('SELECT id, option_date, COALESCE(option_time, \'\') AS option_time FROM event_options WHERE event_id = :event_id');
        $existing->execute(['event_id' => $eventId]);
        $byKey = [];
        foreach ($existing->fetchAll() as $row) {
            $byKey[$row['option_date'] . '|' . $row['option_time']] = (int) $row['id'];
        }

        $keep = [];
        $order = 0;
        foreach ($options as $option) {
            $date = trim($option['date']);
            if ($date === '') {
                continue;
            }
            $time = trim($option['time'] ?? '');
            $key = $date . '|' . $time;
            if (isset($byKey[$key])) {
                $keep[] = $byKey[$key];
                $this->pdo->prepare('UPDATE event_options SET sort_order = :sort WHERE id = :id')
                    ->execute(['sort' => $order, 'id' => $byKey[$key]]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO event_options (event_id, option_date, option_time, sort_order)
                     VALUES (:event_id, :option_date, :option_time, :sort_order)'
                );
                $stmt->execute([
                    'event_id' => $eventId,
                    'option_date' => $date,
                    'option_time' => $time !== '' ? $time : null,
                    'sort_order' => $order,
                ]);
                $keep[] = (int) $this->pdo->lastInsertId();
            }
            $order++;
        }

        // Entfernte Optionen löschen (Antworten hängen per Cascade dran).
        $placeholders = $keep === [] ? 'NULL' : implode(',', array_fill(0, count($keep), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM event_options WHERE event_id = ? AND id NOT IN ($placeholders)");
        $stmt->execute(array_merge([$eventId], $keep));

        // Falls die festgelegte Option wegfiel: Festlegung aufheben.
        $this->pdo->prepare(
            'UPDATE events SET decided_option_id = NULL, status = IF(status = \'decided\', \'open\', status)
             WHERE id = :id AND decided_option_id IS NOT NULL
               AND decided_option_id NOT IN (SELECT id FROM event_options WHERE event_id = :id2)'
        )->execute(['id' => $eventId, 'id2' => $eventId]);
    }

    /**
     * Freitext-Antwortoptionen abgleichen (Typ „poll"). Gleiche Labels behalten
     * ihre Stimmen.
     *
     * @param list<string> $labels
     */
    public function syncTextOptions(int $eventId, array $labels): void
    {
        $existing = $this->pdo->prepare("SELECT id, label FROM event_options WHERE event_id = :event_id AND label IS NOT NULL");
        $existing->execute(['event_id' => $eventId]);
        $byLabel = [];
        foreach ($existing->fetchAll() as $row) {
            $byLabel[$row['label']] = (int) $row['id'];
        }

        $keep = [];
        $order = 0;
        foreach ($labels as $label) {
            $label = trim($label);
            if ($label === '') {
                continue;
            }
            if (isset($byLabel[$label])) {
                $keep[] = $byLabel[$label];
                $this->pdo->prepare('UPDATE event_options SET sort_order = :sort WHERE id = :id')
                    ->execute(['sort' => $order, 'id' => $byLabel[$label]]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO event_options (event_id, label, sort_order) VALUES (:event_id, :label, :sort_order)'
                );
                $stmt->execute(['event_id' => $eventId, 'label' => $label, 'sort_order' => $order]);
                $keep[] = (int) $this->pdo->lastInsertId();
            }
            $order++;
        }

        $placeholders = $keep === [] ? 'NULL' : implode(',', array_fill(0, count($keep), '?'));
        $this->pdo->prepare("DELETE FROM event_options WHERE event_id = ? AND id NOT IN ($placeholders)")
            ->execute(array_merge([$eventId], $keep));
    }

    /**
     * Offene Termine/Abstimmungen, an denen dieser Kontakt teilnimmt – für die
     * „Mein Konto"-Ansicht. Enthält den persönlichen Token und ob schon
     * geantwortet wurde.
     *
     * @return list<array<string, mixed>>
     */
    public function openEventsForContact(int $contactId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.title, e.kind, e.status, e.closes_at, ep.token,
                    EXISTS(SELECT 1 FROM event_responses r WHERE r.participant_id = ep.id) AS has_answered,
                    (SELECT MIN(option_date) FROM event_options WHERE event_options.event_id = e.id AND option_date IS NOT NULL) AS earliest_date
             FROM event_participants ep
             JOIN events e ON e.id = ep.event_id
             WHERE ep.contact_id = :contact_id AND e.status <> \'archived\'
             ORDER BY COALESCE(earliest_date, \'9999-12-31\') ASC, e.created_at DESC'
        );
        $stmt->execute(['contact_id' => $contactId]);

        return $stmt->fetchAll();
    }

    /**
     * Teilnehmerkreis abgleichen. Neue bekommen einen Token, entfernte werden
     * mitsamt ihren Antworten gelöscht.
     *
     * @param list<int> $contactIds
     */
    public function syncParticipants(int $eventId, array $contactIds): void
    {
        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds), static fn (int $n): bool => $n > 0)));

        $current = $this->pdo->prepare('SELECT contact_id FROM event_participants WHERE event_id = :event_id');
        $current->execute(['event_id' => $eventId]);
        $currentIds = array_map('intval', $current->fetchAll(PDO::FETCH_COLUMN));

        foreach (array_diff($contactIds, $currentIds) as $contactId) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO event_participants (event_id, contact_id, token) VALUES (:event_id, :contact_id, :token)'
            );
            $stmt->execute([
                'event_id' => $eventId,
                'contact_id' => $contactId,
                'token' => bin2hex(random_bytes(16)),
            ]);
        }

        $remove = array_diff($currentIds, $contactIds);
        if ($remove !== []) {
            $placeholders = implode(',', array_fill(0, count($remove), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM event_participants WHERE event_id = ? AND contact_id IN ($placeholders)");
            $stmt->execute(array_merge([$eventId], array_values($remove)));
        }
    }

    public function setDecidedOption(int $eventId, ?int $optionId): void
    {
        if ($optionId === null) {
            $this->pdo->prepare('UPDATE events SET decided_option_id = NULL, status = \'open\' WHERE id = :id')
                ->execute(['id' => $eventId]);

            return;
        }

        $check = $this->pdo->prepare('SELECT 1 FROM event_options WHERE id = :oid AND event_id = :eid');
        $check->execute(['oid' => $optionId, 'eid' => $eventId]);
        if ($check->fetchColumn() === false) {
            return;
        }

        $this->pdo->prepare('UPDATE events SET decided_option_id = :oid, status = \'decided\' WHERE id = :eid')
            ->execute(['oid' => $optionId, 'eid' => $eventId]);
    }

    public function setStatus(int $eventId, string $status): void
    {
        if (!in_array($status, ['open', 'closed', 'decided', 'archived'], true)) {
            return;
        }
        $this->pdo->prepare('UPDATE events SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $eventId]);
    }

    /**
     * Frist verlängern / neu setzen. Reaktiviert eine bereits geschlossene
     * Abstimmung und schaltet Erinnerung + Ergebnisversand wieder scharf.
     */
    public function extendDeadline(int $eventId, ?string $closesAt): void
    {
        $normalized = $this->normalizeClosesAt($closesAt);
        $this->pdo->prepare(
            "UPDATE events
                SET closes_at = :closes_at,
                    status = IF(status = 'closed', 'open', status),
                    reminder_sent_at = NULL,
                    result_mail_sent_at = NULL
              WHERE id = :id"
        )->execute(['closes_at' => $normalized, 'id' => $eventId]);
    }

    // -------------------------------------------------------- Automatik / Cron

    /** @return list<int> offene Abstimmungen, deren Frist abgelaufen ist */
    public function idsDueForClose(): array
    {
        $rows = $this->pdo->query(
            "SELECT id FROM events
              WHERE status = 'open' AND closes_at IS NOT NULL AND closes_at <= NOW()"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    /**
     * @return list<int> offene Abstimmungen, deren Frist in den nächsten 48 h
     *   liegt und für die noch keine Erinnerung verschickt wurde
     */
    public function idsDueForReminder(): array
    {
        $rows = $this->pdo->query(
            "SELECT id FROM events
              WHERE status = 'open'
                AND reminder_sent_at IS NULL
                AND closes_at IS NOT NULL
                AND closes_at > NOW()
                AND closes_at <= DATE_ADD(NOW(), INTERVAL 48 HOUR)"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    /**
     * @return list<int> geschlossene Abstimmungen mit Ergebnis-Verteiler, für
     *   die noch keine Ergebnis-Mail raus ist
     */
    public function idsDueForResultMail(): array
    {
        // date_poll: erst wenn das Orga-Team den Termin festgelegt hat.
        // poll: sobald die Abstimmung geschlossen ist.
        $rows = $this->pdo->query(
            "SELECT id FROM events
              WHERE result_mail_sent_at IS NULL
                AND result_recipients IN ('voted', 'invited', 'orga', 'admin')
                AND (
                    (kind = 'date_poll' AND status = 'decided')
                    OR (kind = 'poll' AND status IN ('closed', 'decided', 'archived'))
                )"
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    public function markReminderSent(int $eventId): void
    {
        $this->pdo->prepare('UPDATE events SET reminder_sent_at = NOW() WHERE id = :id')
            ->execute(['id' => $eventId]);
    }

    public function markResultMailSent(int $eventId): void
    {
        $this->pdo->prepare('UPDATE events SET result_mail_sent_at = NOW() WHERE id = :id')
            ->execute(['id' => $eventId]);
    }

    /**
     * Teilnehmer mit Mailadresse, die noch keine Rückmeldung abgegeben haben.
     *
     * @return list<array{name: string, email: string, token: string}>
     */
    public function nonVoterRecipients(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.vorname, c.nachname, ep.token,
                    (SELECT email FROM contact_emails WHERE contact_emails.contact_id = c.id ORDER BY contact_emails.id LIMIT 1) AS email
               FROM event_participants ep
               JOIN contacts c ON c.id = ep.contact_id
              WHERE ep.event_id = :event_id
                AND NOT EXISTS (SELECT 1 FROM event_responses r WHERE r.participant_id = ep.id)
              ORDER BY c.nachname, c.vorname"
        );
        $stmt->execute(['event_id' => $eventId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $out[] = [
                'name' => trim($row['vorname'] . ' ' . $row['nachname']),
                'email' => $email,
                'token' => (string) $row['token'],
            ];
        }

        return $out;
    }

    /**
     * Kontakt-Empfänger für die Ergebnis-Mail.
     *
     * @param bool $onlyVoted true = nur Personen mit mindestens einer Rückmeldung
     * @return list<array{name: string, email: string}>
     */
    public function resultContactRecipients(int $eventId, bool $onlyVoted): array
    {
        $having = $onlyVoted
            ? 'AND EXISTS (SELECT 1 FROM event_responses r WHERE r.participant_id = ep.id)'
            : '';
        $stmt = $this->pdo->prepare(
            "SELECT c.vorname, c.nachname,
                    (SELECT email FROM contact_emails WHERE contact_emails.contact_id = c.id ORDER BY contact_emails.id LIMIT 1) AS email
               FROM event_participants ep
               JOIN contacts c ON c.id = ep.contact_id
              WHERE ep.event_id = :event_id {$having}
              ORDER BY c.nachname, c.vorname"
        );
        $stmt->execute(['event_id' => $eventId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $out[] = ['name' => trim($row['vorname'] . ' ' . $row['nachname']), 'email' => $email];
        }

        return $out;
    }

    /** Zulässige Werte für den Ergebnis-Verteiler. */
    private function normalizeResultRecipients(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, ['voted', 'invited', 'orga', 'admin'], true) ? $value : null;
    }

    /**
     * `datetime-local`-Wert (ohne Sekunden) auf `Y-m-d H:i:s` normalisieren.
     * Ungültige oder leere Werte werden zu NULL.
     */
    private function normalizeClosesAt(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(int $eventId): void
    {
        $this->pdo->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $eventId]);
    }

    // --------------------------------------------------------- Abstimmen (Token)

    /**
     * Teilnehmer über Token laden – mit Termin, Kontaktname, Optionen und den
     * bisherigen Antworten dieser Person.
     */
    public function participantByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ep.id AS participant_id, ep.token, ep.event_id,
                    c.vorname, c.nachname,
                    e.title, e.kind, e.description, e.location, e.time_note, e.cost_note, e.bring_note, e.status, e.closes_at, e.decided_option_id
             FROM event_participants ep
             JOIN contacts c ON c.id = ep.contact_id
             JOIN events e ON e.id = ep.event_id
             WHERE ep.token = :token'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['options'] = $this->optionsForEvent((int) $row['event_id']);
        $row['answers'] = $this->answersForParticipant((int) $row['participant_id']);
        $row['tally'] = $this->tally((int) $row['event_id']);

        return $row;
    }

    /**
     * Antworten eines Teilnehmers speichern. $answers = [optionId => yes|maybe|no].
     */
    public function saveResponses(int $participantId, array $answers, string $via): void
    {
        $validOptions = $this->pdo->prepare(
            'SELECT eo.id FROM event_options eo
             JOIN event_participants ep ON ep.event_id = eo.event_id
             WHERE ep.id = :pid'
        );
        $validOptions->execute(['pid' => $participantId]);
        $allowed = array_map('intval', $validOptions->fetchAll(PDO::FETCH_COLUMN));

        $currentStmt = $this->pdo->prepare('SELECT option_id, answer FROM event_responses WHERE participant_id = :pid');
        $currentStmt->execute(['pid' => $participantId]);
        $current = [];
        foreach ($currentStmt->fetchAll() as $row) {
            $current[(int) $row['option_id']] = $row['answer'];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO event_responses (participant_id, option_id, answer, via)
             VALUES (:pid, :oid, :answer, :via)
             ON DUPLICATE KEY UPDATE answer = VALUES(answer), via = VALUES(via)'
        );
        $log = $this->pdo->prepare(
            'INSERT INTO event_response_log (participant_id, option_id, answer, via) VALUES (:pid, :oid, :answer, :via)'
        );
        foreach ($answers as $optionId => $answer) {
            $optionId = (int) $optionId;
            if (!in_array($optionId, $allowed, true) || !in_array($answer, ['yes', 'maybe', 'no'], true)) {
                continue;
            }
            $insert->execute(['pid' => $participantId, 'oid' => $optionId, 'answer' => $answer, 'via' => $via]);
            if (($current[$optionId] ?? null) !== $answer) {
                $log->execute(['pid' => $participantId, 'oid' => $optionId, 'answer' => $answer, 'via' => $via]);
            }
        }
    }

    public function logTokenHit(int $participantId, string $sourceHash): void
    {
        $this->pdo->prepare(
            'INSERT INTO event_token_hits (participant_id, source_hash) VALUES (:pid, :hash)'
        )->execute(['pid' => $participantId, 'hash' => $sourceHash]);
    }

    /**
     * Pseudonyme Quell-Hashes sind nur solange interessant, wie eine Abstimmung
     * läuft. Alte Einträge (Standard: 120 Tage) werden entfernt.
     */
    public function pruneTokenHits(int $days = 120): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM event_token_hits WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)'
            );
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------ intern

    private function optionsForEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, option_date, option_time, label, sort_order
             FROM event_options WHERE event_id = :event_id
             ORDER BY sort_order ASC, option_date ASC'
        );
        $stmt->execute(['event_id' => $eventId]);

        return $stmt->fetchAll();
    }

    private function participantsForEvent(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ep.id, ep.contact_id, ep.token, ep.added_at,
                    c.vorname, c.nachname,
                    (SELECT email FROM contact_emails WHERE contact_emails.contact_id = c.id ORDER BY contact_emails.id LIMIT 1) AS email
             FROM event_participants ep
             JOIN contacts c ON c.id = ep.contact_id
             WHERE ep.event_id = :event_id
             ORDER BY c.nachname ASC, c.vorname ASC'
        );
        $stmt->execute(['event_id' => $eventId]);
        $participants = $stmt->fetchAll();

        $answers = $this->pdo->prepare(
            'SELECT r.participant_id, r.option_id, r.answer
             FROM event_responses r
             JOIN event_participants ep ON ep.id = r.participant_id
             WHERE ep.event_id = :event_id'
        );
        $answers->execute(['event_id' => $eventId]);
        $byParticipant = [];
        foreach ($answers->fetchAll() as $row) {
            $byParticipant[(int) $row['participant_id']][(int) $row['option_id']] = $row['answer'];
        }

        foreach ($participants as &$participant) {
            $participant['answers'] = $byParticipant[(int) $participant['id']] ?? [];
            $participant['has_answered'] = $participant['answers'] !== [];
        }

        return $participants;
    }

    private function answersForParticipant(int $participantId): array
    {
        $stmt = $this->pdo->prepare('SELECT option_id, answer FROM event_responses WHERE participant_id = :pid');
        $stmt->execute(['pid' => $participantId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['option_id']] = $row['answer'];
        }

        return $out;
    }

    /** @return array<int, array{yes:int, maybe:int, no:int}> option_id → Zähler */
    private function tally(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.option_id, r.answer, COUNT(*) AS n
             FROM event_responses r
             JOIN event_options eo ON eo.id = r.option_id
             WHERE eo.event_id = :event_id
             GROUP BY r.option_id, r.answer'
        );
        $stmt->execute(['event_id' => $eventId]);

        $result = [];
        foreach ($this->optionsForEvent($eventId) as $option) {
            $result[(int) $option['id']] = ['yes' => 0, 'maybe' => 0, 'no' => 0];
        }
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['option_id']][$row['answer']] = (int) $row['n'];
        }

        return $result;
    }

    private function answeredParticipantCount(int $eventId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT r.participant_id)
             FROM event_responses r
             JOIN event_participants ep ON ep.id = r.participant_id
             WHERE ep.event_id = :event_id'
        );
        $stmt->execute(['event_id' => $eventId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, array{submissions:int, sources:int}> participant_id → Stimmabgaben/Quellen */
    private function tokenHitStats(int $eventId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.participant_id, COUNT(*) AS submissions, COUNT(DISTINCT h.source_hash) AS sources
             FROM event_token_hits h
             JOIN event_participants ep ON ep.id = h.participant_id
             WHERE ep.event_id = :event_id
             GROUP BY h.participant_id'
        );
        $stmt->execute(['event_id' => $eventId]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['participant_id']] = [
                'submissions' => (int) $row['submissions'],
                'sources' => (int) $row['sources'],
            ];
        }

        return $out;
    }
}
