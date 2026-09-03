<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Personengruppen quer zu Kategorie/Tag. Mitgliedschaft hängt am Adressbuch-
 * Kontakt, damit Gruppen-Mail und -Abstimmung (Stufe C/D) direkt darauf
 * aufsetzen können. `is_open` = jede:r darf selbst beitreten.
 */
final class GroupRepository
{
    private static bool $schemaChecked = false;

    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    /** @return list<array<string, mixed>> alle Gruppen mit Mitgliederzahl */
    public function all(): array
    {
        return $this->pdo->query(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM contact_group_members m WHERE m.group_id = g.id) AS member_count
             FROM contact_groups g
             ORDER BY g.name ASC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, u.name AS creator_name
             FROM contact_groups g
             LEFT JOIN users u ON u.id = g.created_by
             WHERE g.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $group = $stmt->fetch();
        if (!$group) {
            return null;
        }

        $group['members'] = $this->membersOf($id);

        return $group;
    }

    /** @return list<array<string, mixed>> Mitglieder mit Name und erster Mailadresse */
    public function membersOf(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.contact_id, m.role, m.added_at,
                    c.vorname, c.nachname,
                    (SELECT email FROM contact_emails ce WHERE ce.contact_id = c.id ORDER BY ce.id LIMIT 1) AS email
             FROM contact_group_members m
             JOIN contacts c ON c.id = m.contact_id
             WHERE m.group_id = :group_id
             ORDER BY c.nachname ASC, c.vorname ASC'
        );
        $stmt->execute(['group_id' => $groupId]);

        return $stmt->fetchAll();
    }

    /** @return list<int> */
    public function memberContactIds(int $groupId): array
    {
        $stmt = $this->pdo->prepare('SELECT contact_id FROM contact_group_members WHERE group_id = :group_id');
        $stmt->execute(['group_id' => $groupId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function isMember(int $groupId, int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM contact_group_members WHERE group_id = :g AND contact_id = :c'
        );
        $stmt->execute(['g' => $groupId, 'c' => $contactId]);

        return $stmt->fetchColumn() !== false;
    }

    /** Gruppenleitung: darf die eigene Gruppe verwalten, ohne globales Recht. */
    public function isLead(int $groupId, int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM contact_group_members WHERE group_id = :g AND contact_id = :c AND role = 'lead'"
        );
        $stmt->execute(['g' => $groupId, 'c' => $contactId]);

        return $stmt->fetchColumn() !== false;
    }

    public function setMemberRole(int $groupId, int $contactId, string $role): void
    {
        $role = $role === 'lead' ? 'lead' : 'member';
        $this->pdo->prepare(
            'UPDATE contact_group_members SET role = :r WHERE group_id = :g AND contact_id = :c'
        )->execute(['r' => $role, 'g' => $groupId, 'c' => $contactId]);
    }

    /**
     * Gruppen, in denen dieser Kontakt Mitglied ist – für „Meine Gruppen".
     *
     * @return list<array<string, mixed>>
     */
    public function forContact(int $contactId): array
    {
        if ($contactId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT g.*, m.role AS my_role,
                    (SELECT COUNT(*) FROM contact_group_members mm WHERE mm.group_id = g.id) AS member_count
             FROM contact_group_members m
             JOIN contact_groups g ON g.id = m.group_id
             WHERE m.contact_id = :contact_id
             ORDER BY g.name ASC'
        );
        $stmt->execute(['contact_id' => $contactId]);

        return $stmt->fetchAll();
    }

    /**
     * Offene Gruppen, in denen dieser Kontakt noch nicht ist – zum Beitreten.
     *
     * @return list<array<string, mixed>>
     */
    public function openGroupsToJoin(int $contactId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM contact_group_members mm WHERE mm.group_id = g.id) AS member_count
             FROM contact_groups g
             WHERE g.is_open = 1
               AND (:contact_id = 0 OR g.id NOT IN (
                   SELECT group_id FROM contact_group_members WHERE contact_id = :contact_id2
               ))
             ORDER BY g.name ASC'
        );
        $stmt->execute(['contact_id' => $contactId, 'contact_id2' => $contactId]);

        return $stmt->fetchAll();
    }

    /** Ob für diesen Kontakt überhaupt ein „Gruppen"-Menüpunkt sinnvoll ist. */
    public function navVisibleFor(int $contactId): bool
    {
        if ($contactId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT
                EXISTS(SELECT 1 FROM contact_group_members WHERE contact_id = :c)
                OR EXISTS(SELECT 1 FROM contact_groups WHERE is_open = 1)'
        );
        $stmt->execute(['c' => $contactId]);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contact_groups (name, description, is_open, created_by)
             VALUES (:name, :description, :is_open, :created_by)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'is_open' => !empty($data['is_open']) ? 1 : 0,
            'created_by' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE contact_groups SET name = :name, description = :description, is_open = :is_open WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'is_open' => !empty($data['is_open']) ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        // events.group_id hat keinen DB-Fremdschlüssel – hier von Hand lösen.
        try {
            $this->pdo->prepare('UPDATE events SET group_id = NULL WHERE group_id = :id')->execute(['id' => $id]);
        } catch (\Throwable) {
            // events-Tabelle existiert evtl. noch nicht – unkritisch.
        }
        $this->pdo->prepare('DELETE FROM contact_groups WHERE id = :id')->execute(['id' => $id]);
    }

    public function setMailLocked(int $id, bool $locked): void
    {
        $this->pdo->prepare('UPDATE contact_groups SET mail_locked = :v WHERE id = :id')
            ->execute(['v' => $locked ? 1 : 0, 'id' => $id]);
    }

    /** Anzahl Gruppen-Mails, die dieser Account heute schon verschickt hat. */
    public function senderMailsToday(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM group_mail_log
             WHERE sender_user_id = :uid AND created_at >= :midnight'
        );
        $stmt->execute(['uid' => $userId, 'midnight' => date('Y-m-d 00:00:00')]);

        return (int) $stmt->fetchColumn();
    }

    public function logGroupMail(array $data): void
    {
        $this->pdo->prepare(
            'INSERT INTO group_mail_log
                (group_id, sender_user_id, sender_name, subject, recipient_count, error_count, soft_limit_hit)
             VALUES (:group_id, :sender_user_id, :sender_name, :subject, :recipient_count, :error_count, :soft_limit_hit)'
        )->execute([
            'group_id' => $data['group_id'],
            'sender_user_id' => $data['sender_user_id'] ?: null,
            'sender_name' => $data['sender_name'],
            'subject' => mb_substr((string) $data['subject'], 0, 190),
            'recipient_count' => (int) $data['recipient_count'],
            'error_count' => (int) $data['error_count'],
            'soft_limit_hit' => !empty($data['soft_limit_hit']) ? 1 : 0,
        ]);
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM contact_groups WHERE name = :name AND id <> :id LIMIT 1'
        );
        $stmt->execute(['name' => $name, 'id' => $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    public function addMember(int $groupId, int $contactId, string $role = 'member'): void
    {
        if ($contactId <= 0) {
            return;
        }
        $role = $role === 'lead' ? 'lead' : 'member';
        $this->pdo->prepare(
            'INSERT INTO contact_group_members (group_id, contact_id, role) VALUES (:g, :c, :r)
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        )->execute(['g' => $groupId, 'c' => $contactId, 'r' => $role]);
    }

    public function removeMember(int $groupId, int $contactId): void
    {
        $this->pdo->prepare('DELETE FROM contact_group_members WHERE group_id = :g AND contact_id = :c')
            ->execute(['g' => $groupId, 'c' => $contactId]);
    }

    /**
     * Mitgliederkreis abgleichen. Rollen bereits vorhandener Mitglieder bleiben
     * erhalten; entfernte Mitglieder werden gelöscht.
     *
     * @param list<int> $contactIds
     */
    public function syncMembers(int $groupId, array $contactIds): void
    {
        $contactIds = array_values(array_unique(array_filter(
            array_map('intval', $contactIds),
            static fn (int $n): bool => $n > 0
        )));

        $current = $this->memberContactIds($groupId);

        foreach (array_diff($contactIds, $current) as $contactId) {
            $this->addMember($groupId, $contactId);
        }

        $remove = array_diff($current, $contactIds);
        if ($remove !== []) {
            $placeholders = implode(',', array_fill(0, count($remove), '?'));
            $this->pdo->prepare(
                "DELETE FROM contact_group_members WHERE group_id = ? AND contact_id IN ($placeholders)"
            )->execute(array_merge([$groupId], array_values($remove)));
        }
    }

    private function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS contact_groups (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(120) NOT NULL,
                    description VARCHAR(500) NULL,
                    is_open TINYINT(1) NOT NULL DEFAULT 0,
                    mail_locked TINYINT(1) NOT NULL DEFAULT 0,
                    created_by INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_contact_groups_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS contact_group_members (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    group_id INT UNSIGNED NOT NULL,
                    contact_id INT UNSIGNED NOT NULL,
                    role ENUM(\'member\', \'lead\') NOT NULL DEFAULT \'member\',
                    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_group_member (group_id, contact_id),
                    KEY idx_group_member_contact (contact_id),
                    CONSTRAINT fk_cgm_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
                    CONSTRAINT fk_cgm_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $this->pdo->exec(
                'ALTER TABLE contact_groups ADD COLUMN IF NOT EXISTS mail_locked TINYINT(1) NOT NULL DEFAULT 0'
            );
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS group_mail_log (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    group_id INT UNSIGNED NOT NULL,
                    sender_user_id INT UNSIGNED NULL,
                    sender_name VARCHAR(190) NOT NULL,
                    subject VARCHAR(190) NOT NULL,
                    recipient_count INT NOT NULL DEFAULT 0,
                    error_count INT NOT NULL DEFAULT 0,
                    soft_limit_hit TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_group_mail_log_sender (sender_user_id, created_at),
                    KEY idx_group_mail_log_group (group_id, created_at),
                    CONSTRAINT fk_gml_group FOREIGN KEY (group_id) REFERENCES contact_groups(id) ON DELETE CASCADE,
                    CONSTRAINT fk_gml_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // Migration holt es nach; Repository bleibt lauffähig.
        }
    }
}
